package httpapi

import (
	"fmt"
	"github.com/gofiber/fiber/v2"
	"jconnect/go-backend/internal/service"
	"net/url"
	"os"
	"strings"
)

func (a *App) controlHealth(c *fiber.Ctx) error {
	if err := a.require(c, "control"); err != nil {
		return err
	}
	status, p, err := service.Call(c.UserContext(), service.EnvironmentURL("CONTROL_LOGIC_URL", "http://127.0.0.1:3006"), "GET", "health", nil, "")
	if err != nil || status >= 400 {
		return data(c, Row{"ok": false, "message": "control-logic offline"})
	}
	return data(c, p)
}
func (a *App) controlBootstrap(c *fiber.Ctx) error {
	if err := a.require(c, "control"); err != nil {
		return err
	}
	base := service.EnvironmentURL("CONTROL_LOGIC_URL", "http://127.0.0.1:3006")
	status, health, err := service.Call(c.UserContext(), base, "GET", "health", nil, "")
	devices, dbErr := a.Repo.Query(c.UserContext(), `SELECT id,name,metrics,communication_config FROM devices WHERE is_deleted=false ORDER BY name`)
	if dbErr != nil {
		return dbErr
	}
	u := actor(c)
	out := Row{"engine": Row{"ok": false, "message": "control-logic offline"}, "devices": devices, "groups": []any{}, "commands": []any{}, "logs": []any{}, "schedules": []any{}, "sequences": []any{}, "rules": []any{}, "current_group": nil, "ungrouped_count": 0, "can_create": a.Write && u.Can("control", "create"), "can_update": a.Write && u.Can("control", "all_update"), "can_delete": a.Write && u.Can("control", "all_delete")}
	if err != nil || status >= 400 {
		return data(c, out)
	}
	out["engine"] = health
	for _, name := range []string{"groups", "commands", "logs", "schedules", "sequences", "rules"} {
		path := "api/" + name
		if q := c.Context().URI().QueryString(); len(q) > 0 {
			path += "?" + string(q)
		}
		code, p, err := service.Call(c.UserContext(), base, "GET", path, nil, "")
		if err != nil || code >= 400 {
			return fail(c, 502, "CONTROL_UNAVAILABLE", "control-logic API failed")
		}
		m := obj(p)
		if v, ok := m[name]; ok {
			out[name] = v
		} else if v, ok := obj(m["data"])[name]; ok {
			out[name] = v
		}
		if name == "groups" {
			out["ungrouped_count"] = m["ungrouped_count"]
		}
	}
	return data(c, out)
}
func (a *App) controlProxy(c *fiber.Ctx) error {
	if err := a.require(c, "control"); err != nil {
		return err
	}
	if c.Method() != "GET" {
		if !actor(c).Admin() {
			return fiber.ErrForbidden
		}
		if !a.Write {
			return fiber.NewError(423, "Existing system is read-only; device command was not sent")
		}
	}
	path := c.Params("*")
	first := strings.Split(path, "/")[0]
	switch first {
	case "groups", "commands", "logs", "schedules", "sequences", "rules":
	default:
		return fiber.ErrNotFound
	}
	if q := c.Context().URI().QueryString(); len(q) > 0 {
		path += "?" + string(q)
	}
	var b any
	if len(c.Body()) > 0 {
		var err error
		b, err = body(c)
		if err != nil {
			return err
		}
	}
	status, p, err := service.Call(c.UserContext(), service.EnvironmentURL("CONTROL_LOGIC_URL", "http://127.0.0.1:3006"), c.Method(), "api/"+path, b, "")
	if err != nil {
		return fail(c, 502, "CONTROL_UNAVAILABLE", "control-logic unavailable")
	}
	if status >= 400 {
		return fail(c, status, "CONTROL_ERROR", "control-logic rejected request")
	}
	m := obj(p)
	if v, ok := m["data"]; ok {
		return data(c, v)
	}
	return data(c, p)
}
func (a *App) lorawan(c *fiber.Ctx) error {
	if err := a.require(c, "lorawan"); err != nil {
		return err
	}
	return a.list(c, `SELECT id,application_id,name,description,tenant_id,created_at FROM lorawan_applications ORDER BY name`)
}
func (a *App) lorawanProfiles(c *fiber.Ctx) error {
	if err := a.require(c, "lorawan"); err != nil {
		return err
	}
	tenant := c.Query("tenantId")
	if tenant == "" {
		return invalid("Tenant ID is required")
	}
	key := os.Getenv("CHIRPSTACK_API_KEY")
	if key == "" {
		return fail(c, 503, "CHIRPSTACK_NOT_CONFIGURED", "Set CHIRPSTACK_API_KEY on the Go server")
	}
	if !strings.HasPrefix(key, "Bearer ") {
		key = "Bearer " + key
	}
	path := "device-profiles?limit=100&tenantId=" + url.QueryEscape(tenant)
	status, p, err := service.Call(c.UserContext(), service.EnvironmentURL("CHIRPSTACK_BASE_URL", "http://127.0.0.1/loraapi/rest/api"), "GET", path, nil, key)
	if err != nil || status >= 400 {
		return fail(c, 502, "CHIRPSTACK_UNAVAILABLE", "ChirpStack REST API unavailable")
	}
	return data(c, p)
}
func (a *App) notificationTest(c *fiber.Ctx) error {
	if err := a.require(c, "notification"); err != nil {
		return err
	}
	i, err := id(c)
	if err != nil {
		return err
	}
	if !a.Write {
		return fiber.NewError(423, "Read-only mode; no notification was sent")
	}
	_, err = a.row(c, `SELECT id FROM notification_channels WHERE id=? AND is_deleted=false`, i)
	if err != nil {
		return err
	}
	message := "JConnect notification test"
	if len(c.Body()) > 0 {
		input, e := body(c)
		if e != nil {
			return e
		}
		if nonempty(input["message"]) {
			message = text(input["message"])
		}
	}
	b := Row{"channelId": i, "message": message}
	status, p, err := service.Call(c.UserContext(), service.EnvironmentURL("ALARM_ENGINE_URL", "http://127.0.0.1:3004"), "POST", "api/send-test", b, "")
	if err != nil {
		return fail(c, 502, "ALARM_ENGINE_UNAVAILABLE", "Alarm engine unavailable; test failed")
	}
	if status >= 400 {
		return fail(c, 502, "NOTIFICATION_FAILED", fmt.Sprint(obj(p)["error"]))
	}
	return data(c, p)
}
