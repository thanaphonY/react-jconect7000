package httpapi

import (
	"bytes"
	"context"
	"encoding/json"
	"github.com/joho/godotenv"
	"io"
	"jconnect/go-backend/internal/config"
	"jconnect/go-backend/internal/database"
	"net/http"
	"net/http/httptest"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestExistingDatabaseAndReadAPI(t *testing.T) {
	if os.Getenv("JCONNECT_INTEGRATION") != "1" {
		t.Skip("requires existing read-only database and local test credentials")
	}
	if err := godotenv.Load("../../.env"); err != nil {
		t.Fatal("local environment unavailable")
	}
	t.Setenv("DB_READ_ONLY", "true")
	cfg, err := config.Load()
	if err != nil {
		t.Fatal(err)
	}
	db, err := database.Open(context.Background(), cfg)
	if err != nil {
		t.Fatal(err)
	}
	pool, _ := db.DB()
	defer pool.Close()
	root, _ := filepath.Abs("../../..")
	app := New(db, root)
	defer app.Fiber.Shutdown()
	request := func(method, path, payload, token string) (int, []byte) {
		req := httptest.NewRequest(method, path, strings.NewReader(payload))
		if payload != "" {
			req.Header.Set("Content-Type", "application/json")
		}
		if token != "" {
			req.Header.Set("Authorization", "Bearer "+token)
		}
		r, err := app.Fiber.Test(req, 20000)
		if err != nil {
			t.Fatal(err)
		}
		defer r.Body.Close()
		b, err := io.ReadAll(r.Body)
		if err != nil {
			t.Fatal(err)
		}
		return r.StatusCode, b
	}
	status, _ := request("GET", "/api/v1/devices", "", "")
	if status != 401 {
		t.Fatal("unauthenticated data access allowed")
	}
	email, password := os.Getenv("TEST_LOGIN_EMAIL"), os.Getenv("TEST_LOGIN_PASSWORD")
	if email == "" || password == "" {
		t.Skip("TEST_LOGIN_EMAIL/PASSWORD not configured")
	}
	input, _ := json.Marshal(map[string]any{"email": email, "password": password})
	status, b := request("POST", "/api/v1/auth/login", string(input), "")
	if status != 200 {
		t.Fatalf("existing account login returned %d", status)
	}
	var login struct {
		Data struct {
			AccessToken string `json:"accessToken"`
		}
	}
	if json.Unmarshal(b, &login) != nil || login.Data.AccessToken == "" {
		t.Fatal("invalid login contract")
	}
	token := login.Data.AccessToken
	for _, path := range []string{"/auth/me", "/profile", "/home", "/dashboards", "/devices", "/devices/filters", "/devices/metric-sources", "/device-templates", "/device-categories", "/widgets", "/site-nodes", "/site-access", "/api-clients", "/site-home", "/alarms/active", "/alarm-logs", "/alarm-logs/statistics", "/alarm-logs/filters", "/event-logs", "/notification-channels", "/notification-channels/summary", "/notification-health-checks", "/report-templates", "/report-schedules", "/report-files", "/users", "/users/types", "/users/statistics", "/settings", "/settings/permissions", "/calculations", "/lorawan/applications", "/dcim-taxonomy", "/retention", "/config-backup", "/email-templates"} {
		t.Run(path, func(t *testing.T) {
			status, b := request("GET", "/api/v1"+path, "", token)
			if status != 200 {
				t.Fatalf("expected successful existing-data read, got HTTP %d", status)
			}
			var out map[string]json.RawMessage
			if json.Unmarshal(b, &out) != nil || out["data"] == nil {
				t.Fatal("data envelope missing")
			}
		})
	}
	for _, item := range []struct {
		format string
		prefix []byte
	}{
		{"csv", []byte{0xef, 0xbb, 0xbf}}, {"xlsx", []byte("PK")}, {"pdf", []byte("%PDF-")},
	} {
		t.Run("existing-users-export-"+item.format, func(t *testing.T) {
			status, output := request("GET", "/api/v1/users/export?format="+item.format, "", token)
			if status != 200 || !bytes.HasPrefix(output, item.prefix) {
				t.Fatalf("invalid %s export: HTTP %d", item.format, status)
			}
		})
	}
	status, b = request("GET", "/api/v1/settings/permissions", "", token)
	var permissions struct {
		Data struct {
			Roles []struct {
				ID int64 `json:"id"`
			} `json:"roles"`
		} `json:"data"`
	}
	if status != 200 || json.Unmarshal(b, &permissions) != nil {
		t.Fatal("permissions contract invalid")
	}
	for _, role := range permissions.Data.Roles {
		if role.ID < 1 {
			t.Fatal("permission role ID missing")
		}
	}

	status, _ = request("POST", "/api/v1/devices", `{"name":"invalid_without_template"}`, token)
	if status != 400 {
		t.Fatal("validation failed")
	}
	status, _ = request("POST", "/api/v1/auth/reset-password", `{"password":"not-used","password_confirmation":"not-used"}`, token)
	if status != 423 {
		t.Fatal("production password write was not blocked")
	}
	status, _ = request("POST", "/api/v1/auth/logout", "{}", token)
	if status != http.StatusOK {
		t.Fatal("logout failed")
	}
	status, _ = request("GET", "/api/v1/profile", "", token)
	if status != 401 {
		t.Fatal("revoked token accepted")
	}
}
