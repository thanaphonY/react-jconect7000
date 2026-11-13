package httpapi

import (
	"context"
	"encoding/json"
	ws "github.com/fasthttp/websocket"
	fiberws "github.com/gofiber/contrib/websocket"
	"github.com/gofiber/fiber/v2"
	"jconnect/go-backend/internal/service"
	"strings"
	"sync"
	"time"
)

func (a *App) realtimeRoutes() {
	a.Fiber.Use("/ws", func(c *fiber.Ctx) error {
		u, err := a.Auth.Resolve(c.UserContext(), token(c))
		if err != nil {
			return err
		}
		if u == nil {
			return fail(c, 401, "UNAUTHORIZED", "Sign in required")
		}
		if !u.Regional && !u.Read("devices") && !u.Read("dashboard") {
			return fiber.ErrForbidden
		}
		if !fiberws.IsWebSocketUpgrade(c) {
			return fiber.ErrUpgradeRequired
		}
		c.Locals("wsActor", u)
		return c.Next()
	})
	a.Fiber.Get("/ws", fiberws.New(func(client *fiberws.Conn) {
		u, ok := client.Locals("wsActor").(*service.Actor)
		if !ok {
			return
		}
		upstream, _, err := ws.DefaultDialer.Dial(service.EnvironmentURL("DATAEDGE_WS_URL", "ws://127.0.0.1:3003"), nil)
		if err != nil {
			_ = client.WriteControl(ws.CloseMessage, ws.FormatCloseMessage(1013, "dataedge unavailable"), time.Now().Add(time.Second))
			return
		}
		defer upstream.Close()
		defer client.Close()
		client.SetReadLimit(65536)
		upstream.SetReadLimit(4 * 1024 * 1024)
		var once sync.Once
		stop := func() { once.Do(func() { _ = upstream.Close(); _ = client.Close() }) }
		done := make(chan struct{})
		go func() {
			defer close(done)
			defer stop()
			for {
				kind, message, err := upstream.ReadMessage()
				if err != nil {
					return
				}
				_ = client.SetWriteDeadline(time.Now().Add(10 * time.Second))
				if err = client.WriteMessage(kind, message); err != nil {
					return
				}
			}
		}()
		for {
			kind, message, err := client.ReadMessage()
			if err != nil {
				break
			}
			if kind != ws.TextMessage {
				break
			}
			var request struct {
				Type    string `json:"type"`
				Metrics []struct {
					DeviceID int64  `json:"device_id"`
					Key      string `json:"metric_key"`
				} `json:"metrics"`
			}
			if json.Unmarshal(message, &request) != nil {
				break
			}
			switch request.Type {
			case "subscribe":
				if len(request.Metrics) > 500 {
					stop()
					return
				}
				for _, m := range request.Metrics {
					if m.DeviceID < 1 || strings.TrimSpace(m.Key) == "" {
						stop()
						return
					}
					ctx, cancel := context.WithTimeout(context.Background(), 3*time.Second)
					d, err := a.Repo.One(ctx, `SELECT site_node_id FROM devices WHERE id=? AND is_deleted=false`, m.DeviceID)
					cancel()
					if err != nil || d == nil || !u.SiteAllowed(d["siteNodeId"]) {
						stop()
						return
					}
				}
			case "unsubscribe", "unsubscribe_all":
			default:
				stop()
				return
			}
			_ = upstream.SetWriteDeadline(time.Now().Add(10 * time.Second))
			if err = upstream.WriteMessage(kind, message); err != nil {
				break
			}
		}
		stop()
		<-done
	}, fiberws.Config{Origins: []string{"http://127.0.0.1:5173", "http://localhost:5173", "http://127.0.0.1:3000"}}))
}
