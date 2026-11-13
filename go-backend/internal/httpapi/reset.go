package httpapi

import (
	"context"
	"crypto/rand"
	"crypto/tls"
	"fmt"
	"github.com/gofiber/fiber/v2"
	"golang.org/x/crypto/bcrypt"
	"jconnect/go-backend/internal/service"
	"math/big"
	"net"
	"net/mail"
	"net/smtp"
	"os"
	"strings"
	"sync"
	"time"
)

type resetEntry struct {
	ID                 int64
	Email, Hash, Token string
	Expires, Sent      time.Time
	Attempts           int
	Verified           bool
}
type resetState struct {
	mu      sync.Mutex
	entries map[string]resetEntry
}

func (a *App) forgotPassword(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	email := strings.TrimSpace(text(b["email"]))
	if _, err = mail.ParseAddress(email); err != nil {
		return invalid("Valid email is required")
	}
	u, err := a.Repo.One(c.UserContext(), `SELECT ia_users_id,name,email FROM ia_users WHERE lower(email)=lower(?) AND is_deleted='0' LIMIT 1`, email)
	if err != nil {
		return err
	}
	if u == nil {
		return fail(c, 404, "ACCOUNT_NOT_FOUND", "This account does not exist")
	}
	if !a.Write {
		return fiber.NewError(423, "Existing database is read-only; password reset email was not sent")
	}
	oldKey := c.Cookies("jconnect_reset")
	a.Reset.mu.Lock()
	old, exists := a.Reset.entries[oldKey]
	a.Reset.mu.Unlock()
	if exists && old.Email == email && time.Since(old.Sent) < time.Minute {
		return fail(c, 429, "RATE_LIMITED", "Please wait before resend")
	}
	n, err := rand.Int(rand.Reader, big.NewInt(1000000))
	if err != nil {
		return err
	}
	code := fmt.Sprintf("%06d", n.Int64())
	hash, err := bcrypt.GenerateFromPassword([]byte(code), bcrypt.DefaultCost)
	if err != nil {
		return err
	}
	entry := resetEntry{ID: num(u["iaUsersId"]), Email: email, Hash: string(hash), Expires: time.Now().Add(10 * time.Minute), Sent: time.Now()}
	if err = a.sendResetEmail(c.UserContext(), email, code); err != nil {
		return fail(c, 502, "EMAIL_FAILED", "Password reset email could not be sent")
	}
	if err = a.Repo.Exec(c.UserContext(), `UPDATE ia_users SET var_key=? WHERE ia_users_id=?`, jsonValue(Row{"t": "otp", "h": entry.Hash, "e": entry.Expires.Unix()}), entry.ID); err != nil {
		return err
	}
	key := service.Token()
	a.Reset.mu.Lock()
	for k, v := range a.Reset.entries {
		if time.Now().After(v.Expires) {
			delete(a.Reset.entries, k)
		}
	}
	delete(a.Reset.entries, oldKey)
	a.Reset.entries[key] = entry
	a.Reset.mu.Unlock()
	c.Cookie(&fiber.Cookie{Name: "jconnect_reset", Value: key, Path: "/", HTTPOnly: true, SameSite: "Strict", Secure: c.Protocol() == "https", MaxAge: 600})
	parts := strings.Split(email, "@")
	masked := "***@" + parts[1]
	return data(c, Row{"ok": true, "email": email, "maskedEmail": masked})
}
func (a *App) verifyOTP(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	key := c.Cookies("jconnect_reset")
	a.Reset.mu.Lock()
	defer a.Reset.mu.Unlock()
	entry, ok := a.Reset.entries[key]
	if !ok || entry.Email != text(b["email"]) {
		return fail(c, 400, "INVALID_OTP", "Invalid OTP")
	}
	if time.Now().After(entry.Expires) {
		delete(a.Reset.entries, key)
		return fail(c, 400, "OTP_EXPIRED", "OTP expired")
	}
	if entry.Attempts >= 5 {
		return fail(c, 429, "OTP_TOO_MANY_ATTEMPTS", "Too many attempts")
	}
	entry.Attempts++
	if !service.Verify(text(b["otp"]), entry.Hash) {
		a.Reset.entries[key] = entry
		return fail(c, 400, "INVALID_OTP", "Invalid OTP")
	}
	entry.Verified = true
	entry.Token = service.Token()
	a.Reset.entries[key] = entry
	return data(c, Row{"ok": true, "verified": true, "email": entry.Email, "resetToken": entry.Token})
}
func (a *App) resetPassword(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	if len(text(b["password"])) < 6 || text(b["password"]) != text(b["password_confirmation"]) {
		return invalid("Password confirmation must match and contain at least 6 characters")
	}
	if !a.Write {
		return fiber.NewError(423, "Existing database is read-only; password was not changed")
	}
	key := c.Cookies("jconnect_reset")
	a.Reset.mu.Lock()
	defer a.Reset.mu.Unlock()
	entry, ok := a.Reset.entries[key]
	if !ok || !entry.Verified || time.Now().After(entry.Expires) || entry.Email != text(b["email"]) || entry.Token != text(b["resetToken"]) {
		return fail(c, 400, "INVALID_RESET_TOKEN", "Verify OTP before resetting your password")
	}
	hash, err := bcrypt.GenerateFromPassword([]byte(text(b["password"])), bcrypt.DefaultCost)
	if err != nil {
		return err
	}
	if err = a.Repo.Exec(c.UserContext(), `UPDATE ia_users SET password=?,var_key=NULL WHERE ia_users_id=? AND is_deleted='0'`, string(hash), entry.ID); err != nil {
		return err
	}
	delete(a.Reset.entries, key)
	c.Cookie(&fiber.Cookie{Name: "jconnect_reset", Value: "", Path: "/", HTTPOnly: true, SameSite: "Strict", Expires: time.Unix(1, 0)})
	return data(c, Row{"ok": true})
}
func (a *App) sendResetEmail(ctx context.Context, to, otp string) error {
	settings, err := a.Repo.Query(ctx, `SELECT keys,value FROM ia_setting WHERE keys IN ('HOST','PORT','SMTP_EMAIL','SMTP_PASSWORD','SMTP_SECURE','EMAIL','website')`)
	if err != nil {
		return err
	}
	v := map[string]string{}
	for _, r := range settings {
		v[text(r["keys"])] = text(r["value"])
	}
	get := func(env, key string) string {
		if s := os.Getenv(env); s != "" {
			return s
		}
		return v[key]
	}
	host, port := get("SMTP_HOST", "HOST"), get("SMTP_PORT", "PORT")
	if host == "" || port == "" {
		return fmt.Errorf("SMTP not configured")
	}
	from := get("SMTP_FROM", "SMTP_EMAIL")
	if from == "" {
		from = v["EMAIL"]
	}
	address, err := mail.ParseAddress(from)
	if err != nil {
		return fmt.Errorf("invalid sender")
	}
	target, err := mail.ParseAddress(to)
	if err != nil {
		return err
	}
	config := &tls.Config{ServerName: host, MinVersion: tls.VersionTLS12}
	dialer := &net.Dialer{Timeout: 5 * time.Second}
	var conn net.Conn
	if port == "465" {
		conn, err = tls.DialWithDialer(dialer, "tcp", net.JoinHostPort(host, port), config)
	} else {
		conn, err = dialer.DialContext(ctx, "tcp", net.JoinHostPort(host, port))
	}
	if err != nil {
		return err
	}
	defer conn.Close()
	_ = conn.SetDeadline(time.Now().Add(10 * time.Second))
	client, err := smtp.NewClient(conn, host)
	if err != nil {
		return err
	}
	defer client.Close()
	if port != "465" {
		if ok, _ := client.Extension("STARTTLS"); ok {
			if err = client.StartTLS(config); err != nil {
				return err
			}
		}
	}
	password := get("SMTP_PASSWORD", "SMTP_PASSWORD")
	if password != "" {
		if err = client.Auth(smtp.PlainAuth("", address.Address, password, host)); err != nil {
			return err
		}
	}
	if err = client.Mail(address.Address); err != nil {
		return err
	}
	if err = client.Rcpt(target.Address); err != nil {
		return err
	}
	w, err := client.Data()
	if err != nil {
		return err
	}
	message := "From: " + address.Address + "\r\nTo: " + target.Address + "\r\nSubject: JConnect password reset\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=utf-8\r\n\r\nYour verification code is " + otp + ". It expires in 10 minutes.\r\n"
	if _, err = w.Write([]byte(message)); err != nil {
		return err
	}
	if err = w.Close(); err != nil {
		return err
	}
	return client.Quit()
}
