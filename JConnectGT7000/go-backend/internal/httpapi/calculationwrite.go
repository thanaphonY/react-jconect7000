package httpapi

import (
	"github.com/gofiber/fiber/v2"
	"jconnect/go-backend/internal/repository"
	"regexp"
	"strings"
)

func (a *App) saveCalculation(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	device := num(b["deviceId"])
	key := strings.TrimSpace(text(b["key"]))
	inputs := arr(b["inputs"])
	if device < 1 || key == "" || !nonempty(b["formula"]) || len(inputs) == 0 {
		return invalid("Device, metric key, formula and at least one input are required")
	}
	if err = a.writable(c, "calculation", nil, true); err != nil {
		return err
	}
	oldDevice, oldKey := num(b["originalDeviceId"]), text(b["originalKey"])
	if oldKey == "" {
		oldKey = key
	}
	target, err := a.row(c, `SELECT id,name,is_active,metrics FROM devices WHERE id=? AND is_deleted=false`, device)
	if err != nil {
		return err
	}
	metrics := arr(target["metrics"])
	var old Row
	for _, v := range metrics {
		m := obj(v)
		if m["key"] == key {
			if oldDevice == device && oldKey == key && m["type"] == "calc" {
				old = m
			} else {
				return fail(c, 409, "DUPLICATE_KEY", "Metric key already exists")
			}
		}
	}
	vars := map[string]bool{}
	for _, v := range inputs {
		input := obj(v)
		name := text(input["var"])
		if !regexp.MustCompile(`^[A-Za-z_][A-Za-z0-9_]*$`).MatchString(name) || vars[name] {
			return invalid("Input variables must be unique valid identifiers")
		}
		vars[name] = true
		if input["source"] == "random" {
			continue
		}
		source := num(input["deviceId"])
		if source == 0 {
			source = device
		}
		metric := text(input["metricKey"])
		if metric == "" || source == device && metric == key {
			return invalid("Invalid or self-referencing metric input")
		}
		r, err := a.row(c, `SELECT metrics FROM devices WHERE id=? AND is_deleted=false`, source)
		if err != nil {
			return invalid("Input device not found")
		}
		found := false
		for _, raw := range arr(r["metrics"]) {
			if obj(raw)["key"] == metric {
				found = true
				break
			}
		}
		if !found {
			return invalid("Input metric not found")
		}
	}
	mode := text(b["triggerMode"])
	if mode == "" {
		mode = "interval"
	}
	if mode != "interval" && mode != "on_pull" && mode != "schedule" {
		return invalid("Invalid calculation trigger mode")
	}
	interval := num(b["intervalSec"])
	if interval < 1 {
		interval = 60
	}
	decimals := num(b["decimals"])
	if decimals < 0 || decimals > 8 {
		return invalid("Decimals must be between 0 and 8")
	}
	datatype := text(b["datatype"])
	if datatype == "" {
		datatype = "float"
	}
	if datatype != "float" && datatype != "int" {
		return invalid("Invalid calculation data type")
	}
	metric := Row{"type": "calc", "key": key, "label": b["label"], "unit": b["unit"], "datatype": datatype, "enable": true, "store_policy": "always", "pull_rate": interval, "triggers": []any{}, "normal_condition": Row{}}
	for k, v := range old {
		metric[k] = v
	}
	metric["key"] = key
	metric["label"] = b["label"]
	metric["unit"] = b["unit"]
	metric["datatype"] = datatype
	metric["calc"] = Row{"formula": strings.TrimSpace(text(b["formula"])), "inputs": inputs, "trigger": Row{"mode": mode, "intervalSec": interval, "cron": b["cron"]}, "decimals": decimals, "maxAgeSec": b["maxAgeSec"]}
	err = a.Repo.Transaction(c.UserContext(), func(repo *repository.Repository) error {
		if oldDevice > 0 {
			r, err := repo.One(c.UserContext(), `SELECT metrics FROM devices WHERE id=? AND is_deleted=false FOR UPDATE`, oldDevice)
			if err != nil {
				return err
			}
			if r == nil {
				return fiber.ErrNotFound
			}
			remaining := []any{}
			for _, v := range arr(r["metrics"]) {
				if obj(v)["key"] == oldKey {
					if obj(v)["type"] != "calc" {
						return invalid("Original metric is not a calculation")
					}
				} else {
					remaining = append(remaining, v)
				}
			}
			if oldDevice == device {
				metrics = remaining
			} else if err = repo.Exec(c.UserContext(), `UPDATE devices SET metrics=?::jsonb,updated_at=now() WHERE id=?`, jsonValue(remaining), oldDevice); err != nil {
				return err
			}
		}
		metrics = append(metrics, metric)
		return repo.Exec(c.UserContext(), `UPDATE devices SET metrics=?::jsonb,updated_at=now() WHERE id=?`, jsonValue(metrics), device)
	})
	if err != nil {
		return err
	}
	return data(c, Row{"deviceId": device, "deviceName": target["name"], "isActive": target["isActive"], "metric": metric})
}
func (a *App) deleteCalculation(c *fiber.Ctx) error {
	i, err := id(c)
	if err != nil {
		return err
	}
	key := c.Params("key")
	if key == "" {
		return invalid("Metric key is required")
	}
	if err = a.writable(c, "calculation", nil, false); err != nil {
		return err
	}
	r, err := a.row(c, `SELECT metrics FROM devices WHERE id=? AND is_deleted=false`, i)
	if err != nil {
		return err
	}
	remaining := []any{}
	found := false
	for _, v := range arr(r["metrics"]) {
		m := obj(v)
		if m["key"] == key && m["type"] == "calc" {
			found = true
			continue
		}
		remaining = append(remaining, v)
	}
	if !found {
		return fiber.ErrNotFound
	}
	if err = a.Repo.Exec(c.UserContext(), `UPDATE devices SET metrics=?::jsonb,updated_at=now() WHERE id=?`, jsonValue(remaining), i); err != nil {
		return err
	}
	return data(c, Row{"success": true})
}
