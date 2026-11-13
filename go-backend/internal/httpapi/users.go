package httpapi

import (
	"github.com/gofiber/fiber/v2"
	"golang.org/x/crypto/bcrypt"
	"jconnect/go-backend/internal/service"
	"net/mail"
	"strings"
)

func (a *App) profile(c *fiber.Ctx) error {
	u := actor(c).User
	safe := Row{}
	for _, k := range []string{"id", "name", "email", "userType", "profilePic", "status"} {
		safe[k] = u[k]
	}
	return data(c, safe)
}
func (a *App) updateProfile(c *fiber.Ctx) error {
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
	i := actor(c).ID()
	dup, err := a.Repo.One(c.UserContext(), `SELECT ia_users_id FROM ia_users WHERE lower(email)=lower(?) AND ia_users_id<>? AND is_deleted='0'`, b["email"], i)
	if err != nil {
		return err
	}
	if dup != nil {
		return fail(c, 409, "EMAIL_EXISTS", "Email already registered")
	}
	var hash string
	if nonempty(b["password"]) {
		if text(b["password"]) != text(b["confirmPassword"]) {
			return invalid("Password confirmation does not match")
		}
		r, err := a.row(c, `SELECT password FROM ia_users WHERE ia_users_id=?`, i)
		if err != nil {
			return err
		}
		if !service.Verify(text(b["currentpassword"]), text(r["password"])) {
			return fail(c, 400, "INVALID_CURRENT_PASSWORD", "Enter valid current password")
		}
		raw, err := bcrypt.GenerateFromPassword([]byte(text(b["password"])), bcrypt.DefaultCost)
		if err != nil {
			return err
		}
		hash = string(raw)
	}
	if !a.Write {
		return fiber.NewError(423, "Existing database is read-only")
	}
	photo, err := a.photo(c, "profile_pic")
	if err != nil {
		return err
	}
	if photo != "" {
		if err = a.Repo.Exec(c.UserContext(), `UPDATE ia_users SET profile_pic=? WHERE ia_users_id=?`, photo, i); err != nil {
			return err
		}
	}
	if hash != "" {
		err = a.Repo.Exec(c.UserContext(), `UPDATE ia_users SET name=?,email=?,password=? WHERE ia_users_id=?`, b["name"], b["email"], hash, i)
	} else {
		err = a.Repo.Exec(c.UserContext(), `UPDATE ia_users SET name=?,email=? WHERE ia_users_id=?`, b["name"], b["email"], i)
	}
	if err != nil {
		return err
	}
	u, err := a.Auth.User(c.UserContext(), i)
	if err != nil {
		return err
	}
	c.Locals("actor", u)
	return a.profile(c)
}
func (a *App) users(c *fiber.Ctx) error {
	if err := a.require(c, "user"); err != nil {
		return err
	}
	p, size := page(c)
	where := []string{"is_deleted='0'", "lower(user_type)<>'admin'"}
	args := []any{}
	if actor(c).Can("user", "all_read") {
		where = append(where, "ia_users_id<>?")
		args = append(args, actor(c).ID())
	} else {
		where = append(where, "user_id=?")
		args = append(args, text(actor(c).ID()))
	}
	for _, pair := range [][2]string{{"userType", "user_type"}, {"status", "status"}} {
		if s := c.Query(pair[0]); s != "" && s != "all" {
			where = append(where, pair[1]+"=?")
			args = append(args, s)
		}
	}
	if s := c.Query("search"); s != "" {
		where = append(where, "(name ILIKE ? OR email ILIKE ?)")
		args = append(args, "%"+s+"%", "%"+s+"%")
	}
	suffix := " WHERE " + strings.Join(where, " AND ")
	count, err := a.Repo.One(c.UserContext(), `SELECT count(*) AS total FROM ia_users`+suffix, args...)
	if err != nil {
		return err
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT ia_users_id AS id,name,email,user_type,status,profile_pic,create_date FROM ia_users`+suffix+` ORDER BY ia_users_id DESC LIMIT ? OFFSET ?`, append(args, size, (p-1)*size)...)
	if err != nil {
		return err
	}
	return data(c, Row{"users": rows, "items": rows, "total": count["total"], "page": p, "pageSize": size})
}
func (a *App) userTypes(c *fiber.Ctx) error {
	if err := a.require(c, "user"); err != nil && !actor(c).Read("setting") {
		return err
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT user_type FROM ia_permission ORDER BY user_type`)
	if err != nil {
		return err
	}
	out := []string{}
	for _, r := range rows {
		out = append(out, text(r["userType"]))
	}
	return data(c, Row{"userTypes": out})
}
func (a *App) settings(c *fiber.Ctx) error {
	if err := a.require(c, "setting"); err != nil {
		return err
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT keys,value FROM ia_setting`)
	if err != nil {
		return err
	}
	out := Row{}
	for _, r := range rows {
		out[text(r["keys"])] = r["value"]
	}
	return data(c, out)
}
func (a *App) permissions(c *fiber.Ctx) error {
	if err := a.require(c, "setting"); err != nil {
		return err
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT id,user_type,data FROM ia_permission WHERE lower(user_type)<>'admin' ORDER BY user_type`)
	if err != nil {
		return err
	}
	for _, r := range rows {
		r["data"] = obj(r["data"])
		r["user_type"] = r["userType"]
	}
	return data(c, Row{"roles": rows})
}
