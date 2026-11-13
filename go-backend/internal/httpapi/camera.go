package httpapi

import (
	"fmt"
	"github.com/gofiber/fiber/v2"
	"jconnect/go-backend/internal/service"
)

func (a *App) camera(c *fiber.Ctx) error {
	if err := a.require(c, "devices"); err != nil {
		return err
	}
	i, err := id(c)
	if err != nil {
		return err
	}
	if _, err = a.row(c, `SELECT d.id FROM devices d WHERE d.id=? AND d.is_deleted=false AND `+scope(c, "d"), i); err != nil {
		return err
	}
	action := c.Params("action")
	switch action {
	case "status":
		if c.Method() != "GET" {
			return fiber.ErrMethodNotAllowed
		}
	case "watch", "heartbeat", "unwatch", "snapshot", "probe":
		if c.Method() != "POST" {
			return fiber.ErrMethodNotAllowed
		}
		if !a.Write {
			return fiber.NewError(423, "Read-only verification: camera service was not changed")
		}
	default:
		return fiber.ErrNotFound
	}
	var b any
	if len(c.Body()) > 0 {
		b, err = body(c)
		if err != nil {
			return err
		}
	}
	status, p, err := service.Call(c.UserContext(), service.EnvironmentURL("DATAEDGE_URL", "http://127.0.0.1:3002"), c.Method(), fmt.Sprintf("stream/%d/%s", i, action), b, "")
	if err != nil {
		return fail(c, 502, "DATAEDGE_UNAVAILABLE", "Camera collector service unavailable")
	}
	if status >= 400 {
		return fail(c, status, "STREAM_FAILED", "Camera service rejected request")
	}
	return data(c, p)
}
