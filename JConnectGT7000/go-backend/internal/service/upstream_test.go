package service

import (
	"context"
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"testing"
)

func TestUpstreamContract(t *testing.T) {
	s := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.Method != "POST" || r.URL.Path != "/api/commands" || r.Header.Get("Content-Type") != "application/json" {
			t.Error("request contract changed")
		}
		var b map[string]any
		if json.NewDecoder(r.Body).Decode(&b) != nil || b["value"] != "on" {
			t.Error("payload was not preserved")
		}
		w.Header().Set("Content-Type", "application/json")
		w.WriteHeader(409)
		_, _ = w.Write([]byte(`{"ok":false,"error":"busy"}`))
	}))
	defer s.Close()
	status, p, err := Call(context.Background(), s.URL, "POST", "api/commands", map[string]any{"value": "on"}, "")
	if err != nil || status != 409 || p.(map[string]any)["error"] != "busy" {
		t.Fatal("upstream response/status not preserved")
	}
}
func TestPermissionAndSiteBoundaries(t *testing.T) {
	u := &Actor{User: Row{"id": int64(5), "userType": "administrator"}, Permission: map[string]map[string]any{"devices": {"own_read": "1", "all_read": "0", "own_update": true}}, Regional: true, Sites: []int64{9}}
	if u.Admin() {
		t.Fatal("administrator display role must not bypass PHP permission checks")
	}
	if !u.Read("devices") || u.Can("devices", "all_read") {
		t.Fatal("read permissions not preserved")
	}
	if !u.Own("devices", "update", int64(5)) || u.Own("devices", "update", int64(6)) {
		t.Fatal("ownership not enforced")
	}
	if !u.SiteAllowed(int64(9)) || u.SiteAllowed(int64(10)) || u.SiteAllowed(nil) {
		t.Fatal("site scope not enforced")
	}
	if u.Read("unknown") {
		t.Fatal("unknown module allowed")
	}
}
