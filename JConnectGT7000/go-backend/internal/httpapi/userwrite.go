package httpapi

import (
	"github.com/gofiber/fiber/v2"
	"golang.org/x/crypto/bcrypt"
	"jconnect/go-backend/internal/repository"
	"jconnect/go-backend/internal/service"
	"net/mail"
	"strconv"
	"strings"
)

func (a *App) userStatistics(c *fiber.Ctx) error {
	if err := a.require(c, "user"); err != nil {
		return err
	}
	q := `SELECT count(*) AS total,count(*) FILTER(WHERE status='active' OR status='1') AS active,count(*) FILTER(WHERE status<>'active' AND status<>'1') AS inactive,count(*) FILTER(WHERE status='pending') AS pending_users,count(DISTINCT user_type) AS user_types FROM ia_users WHERE is_deleted='0'`
	args := []any{}
	if !actor(c).Can("user", "all_read") {
		q += " AND user_id=?"
		args = append(args, strconv.FormatInt(actor(c).ID(), 10))
	}
	r, err := a.row(c, q, args...)
	if err != nil {
		return err
	}
	r["totalUsers"] = r["total"]
	r["activeUsers"] = r["active"]
	r["inactiveUsers"] = r["inactive"]
	return data(c, r)
}
func (a *App) saveUser(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	if !nonempty(b["name"]) {
		return invalid("Name is required")
	}
	if _, err = mail.ParseAddress(text(b["email"])); err != nil {
		return invalid("Valid email is required")
	}
	role := text(b["user_type"])
	if role == "" {
		role = text(b["userType"])
	}
	if role == "" {
		return invalid("User type is required")
	}
	if strings.EqualFold(role, "admin") {
		return fiber.ErrForbidden
	}
	validRole, err := a.Repo.One(c.UserContext(), `SELECT id FROM ia_permission WHERE user_type=?`, role)
	if err != nil {
		return err
	}
	if validRole == nil {
		return invalid("Unknown user type")
	}
	var existing Row
	var i int64
	if c.Params("id") != "" {
		i, err = id(c)
		if err != nil {
			return err
		}
		existing, err = a.row(c, `SELECT user_id,user_type,password FROM ia_users WHERE ia_users_id=? AND is_deleted='0'`, i)
		if err != nil {
			return err
		}
	}
	var owner any
	if existing != nil {
		owner = existing["userId"]
		if i == 1 || strings.EqualFold(text(existing["userType"]), "admin") {
			return fiber.ErrForbidden
		}
	}
	if err = a.writable(c, "user", owner, existing == nil); err != nil {
		return err
	}
	duplicate, err := a.Repo.One(c.UserContext(), `SELECT ia_users_id FROM ia_users WHERE lower(email)=lower(?) AND ia_users_id<>? AND is_deleted='0'`, b["email"], i)
	if err != nil {
		return err
	}
	if duplicate != nil {
		return fail(c, 409, "EMAIL_EXISTS", "Email already registered")
	}
	var hash string
	if nonempty(b["password"]) {
		if len(text(b["password"])) < 6 {
			return invalid("Password must contain at least 6 characters")
		}
		if existing != nil {
			if text(b["password"]) != text(b["confirmPassword"]) {
				return invalid("Password confirmation does not match")
			}
			if !service.Verify(text(b["currentpassword"]), text(existing["password"])) {
				return fail(c, 400, "INVALID_CURRENT_PASSWORD", "Enter valid current password")
			}
		}
		raw, err := bcrypt.GenerateFromPassword([]byte(text(b["password"])), bcrypt.DefaultCost)
		if err != nil {
			return err
		}
		hash = string(raw)
	}
	photo, err := a.photo(c, "profile_pic")
	if err != nil {
		return err
	}
	if existing == nil {
		if hash == "" {
			return invalid("Password is required")
		}
		approval, err := a.Repo.One(c.UserContext(), `SELECT value FROM ia_setting WHERE keys='admin_approval' LIMIT 1`)
		if err != nil {
			return err
		}
		status := "active"
		if approval != nil && approval["value"] == "1" {
			status = "pending"
		}
		if photo == "" {
			photo = "user.png"
		}
		r, err := a.row(c, `INSERT INTO ia_users(name,email,password,user_type,is_deleted,status,user_id,create_date,profile_pic) VALUES(?,?,?,?,'0',?,?,(now() AT TIME ZONE 'Asia/Bangkok')::text,?) RETURNING ia_users_id AS id`, b["name"], b["email"], hash, role, status, strconv.FormatInt(actor(c).ID(), 10), photo)
		if err != nil {
			return err
		}
		i = num(r["id"])
	} else {
		q := `UPDATE ia_users SET name=?,email=?,user_type=?`
		args := []any{b["name"], b["email"], role}
		if photo != "" {
			q += ",profile_pic=?"
			args = append(args, photo)
		}
		if hash != "" {
			q += ",password=?"
			args = append(args, hash)
		}
		q += " WHERE ia_users_id=?"
		args = append(args, i)
		if err = a.Repo.Exec(c.UserContext(), q, args...); err != nil {
			return err
		}
	}
	r, err := a.row(c, `SELECT ia_users_id AS id,name,email,user_type,status,profile_pic FROM ia_users WHERE ia_users_id=?`, i)
	if err != nil {
		return err
	}
	return data(c, r)
}
func (a *App) deleteUser(c *fiber.Ctx) error {
	i, err := id(c)
	if err != nil {
		return err
	}
	if i == actor(c).ID() || i == 1 {
		return fail(c, 409, "PROTECTED_USER", "Cannot delete this user")
	}
	r, err := a.row(c, `SELECT user_id FROM ia_users WHERE ia_users_id=? AND is_deleted='0'`, i)
	if err != nil {
		return err
	}
	if err = a.writable(c, "user", r["userId"], false); err != nil {
		return err
	}
	if err = a.Repo.Exec(c.UserContext(), `UPDATE ia_users SET is_deleted='1' WHERE ia_users_id=?`, i); err != nil {
		return err
	}
	return data(c, Row{"id": i})
}
func (a *App) settingsWrite(c *fiber.Ctx) error {
	if err := a.writable(c, "setting", actor(c).ID(), false); err != nil {
		return err
	}
	b, err := body(c)
	if err != nil {
		return err
	}
	if c.Params("kind") == "general" {
		logo, e := a.photo(c, "logo")
		if e != nil {
			return e
		}
		if logo != "" {
			b["logo"] = logo
		}
	}
	allowed := map[string]bool{}
	switch c.Params("kind") {
	case "general":
		for _, k := range []string{"website", "register_allowed", "admin_approval", "user_type", "logo"} {
			allowed[k] = true
		}
	case "email":
		for _, k := range []string{"mail_setting", "company_name", "SMTP_EMAIL", "HOST", "PORT", "SMTP_SECURE", "SMTP_PASSWORD", "EMAIL"} {
			allowed[k] = true
		}
	default:
		return fiber.ErrNotFound
	}
	if err = a.Repo.Transaction(c.UserContext(), func(r *repository.Repository) error {
		for k, v := range b {
			if !allowed[k] {
				continue
			}
			value := text(v)
			if _, ok := v.(string); !ok {
				value = jsonValue(v)
			}
			if err := r.Exec(c.UserContext(), `UPDATE ia_setting SET value=? WHERE keys=?`, value, k); err != nil {
				return err
			}
		}
		return nil
	}); err != nil {
		return err
	}
	return a.settings(c)
}
