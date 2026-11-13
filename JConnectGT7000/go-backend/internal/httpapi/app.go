package httpapi

import (
	"context"
	"encoding/json"
	"fmt"
	"github.com/gofiber/fiber/v2"
	"github.com/gofiber/fiber/v2/middleware/limiter"
	"github.com/gofiber/fiber/v2/middleware/recover"
	"gorm.io/gorm"
	"jconnect/go-backend/internal/repository"
	"jconnect/go-backend/internal/service"
	"log"
	"net/url"
	"os"
	"path/filepath"
	"strconv"
	"strings"
	"time"
)

type Row = repository.Row
type App struct {
	Fiber *fiber.App
	Repo  *repository.Repository
	Auth  *service.Auth
	Reset *resetState
	Root  string
	Write bool
}

func New(db *gorm.DB, root string) *App {
	a := &App{Repo: repository.New(db), Root: root, Write: os.Getenv("DB_READ_ONLY") == "false"}
	a.Auth = service.NewAuth(a.Repo)
	a.Reset = &resetState{entries: map[string]resetEntry{}}
	a.Fiber = fiber.New(fiber.Config{DisableStartupMessage: true, BodyLimit: 50 * 1024 * 1024, ReadTimeout: 15 * time.Second, WriteTimeout: 30 * time.Second, ErrorHandler: func(c *fiber.Ctx, err error) error {
		code := 500
		message := "Request could not be completed"
		if e, ok := err.(*fiber.Error); ok {
			code = e.Code
			message = e.Message
		}
		if code >= 500 {
			log.Printf("API failure %s %s", c.Method(), c.Path())
		}
		return fail(c, code, "REQUEST_FAILED", message)
	}})
	app := a.Fiber
	app.Use(recover.New())
	app.Get("/api/health", func(c *fiber.Ctx) error {
		pool, err := db.DB()
		if err != nil {
			return err
		}
		ctx, cancel := context.WithTimeout(c.UserContext(), 3*time.Second)
		defer cancel()
		if err = pool.PingContext(ctx); err != nil {
			return fail(c, 503, "DATABASE_UNAVAILABLE", "Existing database unavailable")
		}
		return c.JSON(fiber.Map{"status": "ok", "database": "connected", "readOnly": !a.Write})
	})
	app.Use("/assets", func(c *fiber.Ctx) error {
		if strings.HasSuffix(strings.ToLower(c.Path()), ".php") || strings.Contains(c.Path(), "/.") {
			return fiber.ErrNotFound
		}
		return c.Next()
	})
	app.Static("/assets", filepath.Join(root, "frontend/assets"))
	app.Get("/api/v1/branding", a.branding)
	app.Get("/api/v1/assets/images/:filename", func(c *fiber.Ctx) error { return a.asset(c, "assets/images", c.Params("filename")) })
	app.Get("/api/v1/uploads/:kind/:filename", func(c *fiber.Ctx) error {
		kind := map[string]string{"devices": "uploads/devices", "device-templates": "uploads/device_templates", "maps": "uploads/maps", "svg": "uploads/mimics", "mimics": "uploads/mimics"}[c.Params("kind")]
		if kind == "" {
			return fiber.ErrNotFound
		}
		return a.asset(c, kind, c.Params("filename"))
	})
	app.Post("/api/v1/auth/login", limiter.New(limiter.Config{Max: 20, Expiration: time.Minute}), a.login)
	app.Post("/api/v1/auth/forgot-password", limiter.New(limiter.Config{Max: 5, Expiration: time.Minute}), a.forgotPassword)
	app.Post("/api/v1/auth/resend-otp", limiter.New(limiter.Config{Max: 5, Expiration: time.Minute}), a.forgotPassword)
	app.Post("/api/v1/auth/verify-otp", limiter.New(limiter.Config{Max: 10, Expiration: time.Minute}), a.verifyOTP)
	app.Post("/api/v1/auth/reset-password", a.resetPassword)
	app.Post("/api/v1/auth/refresh", a.refresh)
	app.Post("/api/v1/auth/logout", a.logout)
	// All non-public API routes require a live existing user and current permissions.
	app.Use("/api/v1", func(c *fiber.Ctx) error {
		actor, err := a.Auth.Resolve(c.UserContext(), token(c))
		if err != nil {
			return err
		}
		if actor == nil {
			return fail(c, 401, "UNAUTHORIZED", "Sign in required")
		}
		c.Locals("actor", actor)
		return c.Next()
	})
	a.realtimeRoutes()
	a.routes()
	app.Use("/api", func(c *fiber.Ctx) error {
		return fail(c, 404, "NOT_IMPLEMENTED", "This endpoint has not been migrated")
	})
	if _, err := os.Stat(filepath.Join(root, "react-frontend/dist/index.html")); err == nil {
		app.Static("/", filepath.Join(root, "react-frontend/dist"))
		app.Get("/*", func(c *fiber.Ctx) error { return c.SendFile(filepath.Join(root, "react-frontend/dist/index.html")) })
	}
	return a
}
func fail(c *fiber.Ctx, status int, code, msg string) error {
	return c.Status(status).JSON(fiber.Map{"error": fiber.Map{"code": code, "message": msg}})
}
func data(c *fiber.Ctx, v any) error    { return c.JSON(fiber.Map{"data": v}) }
func actor(c *fiber.Ctx) *service.Actor { return c.Locals("actor").(*service.Actor) }
func token(c *fiber.Ctx) string {
	if s := strings.TrimPrefix(c.Get("Authorization"), "Bearer "); s != "" && s != c.Get("Authorization") {
		return s
	}
	return c.Cookies("jconnect_go_session")
}
func (a *App) require(c *fiber.Ctx, module string) error {
	u := actor(c)
	if u.Regional && module != "dashboard" && module != "site" && module != "alarmlogs" && module != "reports" {
		return fiber.ErrForbidden
	}
	if !u.Regional && module != "site" && module != "widgets" && module != "calculation" && module != "lorawan" && !u.Read(module) {
		return fiber.ErrForbidden
	}
	return nil
}
func (a *App) writable(c *fiber.Ctx, module string, owner any, create bool) error {
	if err := a.require(c, module); err != nil {
		return err
	}
	u := actor(c)
	verb := "update"
	if c.Method() == "DELETE" {
		verb = "delete"
	}
	if create {
		if !u.Can(module, "own_create") && !u.Can(module, "all_create") {
			return fiber.ErrForbidden
		}
	} else if !u.Own(module, verb, owner) {
		return fiber.ErrForbidden
	}
	if !a.Write {
		return fiber.NewError(423, "Existing database is read-only; no production changes applied")
	}
	return nil
}
func id(c *fiber.Ctx) (int64, error) {
	v, err := strconv.ParseInt(c.Params("id"), 10, 64)
	if err != nil || v < 1 {
		return 0, fiber.NewError(400, "Invalid positive ID")
	}
	return v, nil
}
func body(c *fiber.Ctx) (Row, error) {
	if strings.HasPrefix(c.Get("Content-Type"), "multipart/form-data") {
		form, err := c.MultipartForm()
		if err != nil {
			return nil, invalid("Invalid multipart form")
		}
		out := Row{}
		for key, values := range form.Value {
			if len(values) == 1 {
				var v any
				if (strings.HasPrefix(values[0], "{") || strings.HasPrefix(values[0], "[")) && json.Unmarshal([]byte(values[0]), &v) == nil {
					out[key] = v
				} else {
					out[key] = values[0]
				}
			}
		}
		return out, nil
	}

	var out Row
	if err := c.BodyParser(&out); err != nil || out == nil {
		return nil, fiber.NewError(400, "Invalid JSON object")
	}
	return out, nil
}
func page(c *fiber.Ctx) (int, int) {
	p := c.QueryInt("page", 1)
	size := c.QueryInt("pageSize", 10)
	if p < 1 {
		p = 1
	}
	if size < 1 {
		size = 10
	}
	if size > 100 {
		size = 100
	}
	return p, size
}
func text(v any) string      { return repository.Text(v) }
func num(v any) int64        { return repository.Number(v) }
func obj(v any) Row          { return repository.Object(v) }
func arr(v any) []any        { return repository.Array(v) }
func jsonValue(v any) string { return repository.JSON(v) }
func nonempty(v any) bool    { return strings.TrimSpace(text(v)) != "" }
func (a *App) asset(c *fiber.Ctx, folder, name string) error {
	decoded, err := url.PathUnescape(name)
	if err != nil || decoded != filepath.Base(decoded) || strings.ContainsAny(decoded, "/\\%") || strings.Contains(decoded, "..") {
		return fiber.ErrBadRequest
	}
	ext := strings.ToLower(filepath.Ext(decoded))
	switch ext {
	case ".png", ".jpg", ".jpeg", ".gif", ".webp", ".ico", ".svg":
	default:
		return fiber.ErrNotFound
	}
	c.Set("X-Content-Type-Options", "nosniff")
	if ext == ".svg" {
		c.Set("Content-Security-Policy", "sandbox; default-src 'none'; style-src 'unsafe-inline'; img-src data:")
	}
	return c.SendFile(filepath.Join(a.Root, "frontend", folder, decoded))
}
func (a *App) branding(c *fiber.Ctx) error {
	rows, err := a.Repo.Query(c.UserContext(), `SELECT keys,value FROM ia_setting WHERE keys IN ('website','logo','favicon','register_allowed')`)
	if err != nil {
		return err
	}
	out := Row{"website": "JCONNECT", "logo": "", "favicon": "", "register_allowed": "0"}
	for _, r := range rows {
		out[text(r["keys"])] = r["value"]
	}
	return data(c, out)
}
func (a *App) session(c *fiber.Ctx, t string, u *service.Actor) error {
	b, err := a.Repo.One(c.UserContext(), `SELECT value FROM ia_setting WHERE keys='website' LIMIT 1`)
	if err != nil {
		return err
	}
	site := "JCONNECT"
	if b != nil {
		site = text(b["value"])
	}
	safe := Row{}
	for _, k := range []string{"id", "name", "email", "userType", "profilePic", "status", "accessLevel"} {
		safe[k] = u.User[k]
	}
	return data(c, Row{"accessToken": t, "user": safe, "menu": u.Menu(), "website": site, "readOnly": !a.Write})
}
func (a *App) setCookie(c *fiber.Ctx, t string, remember bool) {
	cookie := &fiber.Cookie{Name: "jconnect_go_session", Value: t, Path: "/", HTTPOnly: true, SameSite: "Strict", Secure: c.Protocol() == "https"}
	if remember {
		cookie.Expires = time.Now().Add(30 * 24 * time.Hour)
	}
	c.Cookie(cookie)
}
func (a *App) login(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	if !nonempty(b["email"]) || !nonempty(b["password"]) {
		return fail(c, 400, "INVALID_INPUT", "Email and password are required")
	}
	remember, _ := b["remember"].(bool)
	t, u, err := a.Auth.Login(c.UserContext(), text(b["email"]), text(b["password"]), remember)
	if err != nil {
		return err
	}
	if u == nil {
		return fail(c, 401, "INVALID_CREDENTIALS", "Invalid details")
	}
	a.setCookie(c, t, remember)
	return a.session(c, t, u)
}
func (a *App) refresh(c *fiber.Ctx) error {
	t := c.Cookies("jconnect_go_session")
	u, err := a.Auth.Resolve(c.UserContext(), t)
	if err != nil {
		return err
	}
	if u == nil {
		return fail(c, 401, "UNAUTHORIZED", "Sign in required")
	}
	return a.session(c, t, u)
}
func (a *App) logout(c *fiber.Ctx) error {
	a.Auth.Revoke(token(c))
	c.Cookie(&fiber.Cookie{Name: "jconnect_go_session", Value: "", Path: "/", HTTPOnly: true, SameSite: "Strict", Expires: time.Unix(1, 0)})
	return data(c, Row{"success": true})
}
func (a *App) row(c *fiber.Ctx, q string, args ...any) (Row, error) {
	r, err := a.Repo.One(c.UserContext(), q, args...)
	if err != nil {
		return nil, err
	}
	if r == nil {
		return nil, fiber.ErrNotFound
	}
	return r, nil
}
func (a *App) list(c *fiber.Ctx, q string, args ...any) error {
	rows, err := a.Repo.Query(c.UserContext(), q, args...)
	if err != nil {
		return err
	}
	return data(c, rows)
}
func invalid(message string) error { return fiber.NewError(400, message) }
func missingService(name string, err error) error {
	return fiber.NewError(502, fmt.Sprintf("%s unavailable", name))
}
