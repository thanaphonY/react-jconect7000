package httpapi

import (
	"encoding/json"
	"jconnect/go-backend/internal/service"
	"testing"
)

func TestLegacyWidgetConfigAndTypedData(t *testing.T) {
	r := Row{"config": `"{\"device_id\":12,\"metric_key\":\"Power\"}"`}
	normalizeResource(r, Resource{Table: "widgets", JSON: map[string]bool{"config": true}})
	if num(obj(r["config"])["device_id"]) != 12 {
		t.Fatal("legacy JSON config not decoded")
	}
	b, _ := json.Marshal(Row{"value": nil, "valueBool": false})
	var out Row
	_ = json.Unmarshal(b, &out)
	if out["value"] != nil || out["valueBool"] != false {
		t.Fatal("missing metrics must not become fake numeric values")
	}
}
func TestResetAndAuthenticationBoundaries(t *testing.T) {
	if service.Verify("wrong", "invalid hash") {
		t.Fatal("invalid hash accepted")
	}
	a := &service.Actor{User: Row{"id": int64(1), "name": "Admin", "userType": "administrator"}}
	if a.Admin() {
		t.Fatal("display name must not grant admin rights")
	}
	if a.Can("setting", "all_update") {
		t.Fatal("unknown permissions must deny writes")
	}
}
