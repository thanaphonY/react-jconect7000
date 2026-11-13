package httpapi

import (
	"encoding/json"
	"github.com/gofiber/fiber/v2"
	"sort"
	"strings"
)

// Resource identifiers and column mappings are compile-time allowlists from the live schema.
type Resource struct {
	Path, Table, Module string
	Fields              map[string]string
	Required            []string
	JSON                map[string]bool
	SoftDelete          bool
}

var resources = []Resource{
	{Path: "widgets", Table: "widgets", Module: "widgets", Fields: map[string]string{"name": "name", "widgetType": "widget_type", "config": "config"}, Required: []string{"name", "widgetType"}, JSON: map[string]bool{"config": true}},
	{Path: "device-categories", Table: "device_categories", Module: "device_categories", Fields: map[string]string{"name": "name", "description": "description", "icon": "icon", "color": "color", "isActive": "is_active"}, Required: []string{"name"}},
	{Path: "device-templates", Table: "device_templates", Module: "devicetemplate", Fields: map[string]string{"name": "name", "description": "description", "protocol": "protocol", "categoryId": "category_id", "isActive": "is_active", "communicationConfig": "communication_config", "deviceInformation": "device_information", "metrics": "metrics", "defaultDashboardLayout": "default_dashboard_layout", "image": "image"}, Required: []string{"name", "protocol", "categoryId"}, JSON: map[string]bool{"communicationConfig": true, "deviceInformation": true, "metrics": true, "defaultDashboardLayout": true}},
	{Path: "notification-channels", Table: "notification_channels", Module: "notification", Fields: map[string]string{"name": "name", "type": "type", "isActive": "is_active", "messageTemplate": "message_template", "description": "description", "config": "config"}, Required: []string{"name", "type"}, JSON: map[string]bool{"config": true}, SoftDelete: true},
	{Path: "health-checks", Table: "health_checks", Module: "notification", Fields: map[string]string{"name": "name", "isActive": "is_active", "scheduleType": "schedule_type", "scheduleConfig": "schedule_config", "timezone": "timezone", "channelIds": "channel_ids", "rooms": "rooms", "messageTemplate": "message_template"}, Required: []string{"name", "scheduleType"}, JSON: map[string]bool{"scheduleConfig": true, "channelIds": true, "rooms": true}},
	{Path: "report-templates", Table: "report_templates", Module: "reports", Fields: map[string]string{"name": "name", "description": "description", "templateType": "template_type", "config": "config", "htmlContent": "html_content"}, Required: []string{"name"}, JSON: map[string]bool{"config": true}},
	{Path: "report-schedules", Table: "report_schedules", Module: "reports", Fields: map[string]string{"templateId": "template_id", "name": "name", "isActive": "is_active", "scheduleType": "schedule_type", "scheduleConfig": "schedule_config", "timezone": "timezone", "exportFormat": "export_format", "recipients": "recipients", "reportParams": "report_params", "nextRunAt": "next_run_at"}, Required: []string{"name", "templateId", "scheduleType"}, JSON: map[string]bool{"scheduleConfig": true, "recipients": true, "reportParams": true}},
}

func (a *App) resourceRead(spec Resource) fiber.Handler {
	return func(c *fiber.Ctx) error {
		if err := a.require(c, spec.Module); err != nil {
			return err
		}
		suffix := ""
		args := []any{}
		if spec.SoftDelete {
			suffix = " WHERE is_deleted=false"
		}
		if c.Params("id") != "" {
			i, err := id(c)
			if err != nil {
				return err
			}
			if suffix == "" {
				suffix = " WHERE id=?"
			} else {
				suffix += " AND id=?"
			}
			args = append(args, i)
			r, err := a.row(c, "SELECT * FROM "+spec.Table+suffix, args...)
			if err != nil {
				return err
			}
			normalizeResource(r, spec)
			return data(c, r)
		}
		rows, err := a.Repo.Query(c.UserContext(), "SELECT * FROM "+spec.Table+suffix+" ORDER BY name", args...)
		if err != nil {
			return err
		}
		for _, r := range rows {
			normalizeResource(r, spec)
		}
		return data(c, rows)
	}
}
func (a *App) resourceWrite(spec Resource) fiber.Handler {
	return func(c *fiber.Ctx) error {
		b, err := body(c)
		if err != nil {
			return err
		}
		if spec.Table == "report_schedules" {
			b, err = normalizeReportSchedule(b)
			if err != nil {
				return err
			}
		}
		for _, k := range spec.Required {
			if !nonempty(b[k]) {
				return invalid(k + " is required")
			}
		}
		var i int64
		var existing Row
		if c.Params("id") != "" {
			i, err = id(c)
			if err != nil {
				return err
			}
			existing, err = a.row(c, "SELECT * FROM "+spec.Table+" WHERE id=?", i)
			if err != nil {
				return err
			}
		}
		var owner any
		if existing != nil {
			owner = existing["createdBy"]
		}
		if err = a.writable(c, spec.Module, owner, existing == nil); err != nil {
			return err
		}
		keys := []string{}
		for k := range b {
			if _, ok := spec.Fields[k]; !ok {
				return invalid("Unknown field: " + k)
			}
			keys = append(keys, k)
		}
		sort.Strings(keys)
		cols := []string{}
		marks := []string{}
		args := []any{}
		for _, k := range keys {
			cols = append(cols, spec.Fields[k])
			mark := "?"
			v := b[k]
			if spec.JSON[k] {
				mark = "?::jsonb"
				v = jsonValue(v)
			}
			marks = append(marks, mark)
			args = append(args, v)
		}
		var q string
		if existing == nil {
			if spec.Table == "report_templates" || spec.Table == "report_schedules" {
				cols = append(cols, "created_by")
				marks = append(marks, "?")
				args = append(args, actor(c).ID())
			}
			q = "INSERT INTO " + spec.Table + " (" + strings.Join(cols, ",") + ") VALUES (" + strings.Join(marks, ",") + ") RETURNING *"
		} else {
			sets := []string{}
			for n, col := range cols {
				sets = append(sets, col+"="+marks[n])
			}
			q = "UPDATE " + spec.Table + " SET " + strings.Join(sets, ",") + ",updated_at=now() WHERE id=? RETURNING *"
			args = append(args, i)
		}
		r, err := a.row(c, q, args...)
		if err != nil {
			return err
		}
		return data(c, r)
	}
}
func (a *App) resourceDelete(spec Resource) fiber.Handler {
	return func(c *fiber.Ctx) error {
		i, err := id(c)
		if err != nil {
			return err
		}
		r, err := a.row(c, "SELECT * FROM "+spec.Table+" WHERE id=?", i)
		if err != nil {
			return err
		}
		if err = a.writable(c, spec.Module, r["createdBy"], false); err != nil {
			return err
		}
		if spec.Table == "widgets" || spec.Table == "device_templates" || spec.Table == "device_categories" {
			q := map[string]string{"widgets": "SELECT count(*) AS total FROM dashboard_layout_items WHERE widget_id=?", "device_templates": "SELECT count(*) AS total FROM devices WHERE device_template_id=? AND is_deleted=false", "device_categories": "SELECT (SELECT count(*) FROM devices WHERE category_id=? AND is_deleted=false)+(SELECT count(*) FROM device_templates WHERE category_id=?) AS total"}[spec.Table]
			args := []any{i}
			if spec.Table == "device_categories" {
				args = append(args, i)
			}
			used, err := a.Repo.One(c.UserContext(), q, args...)
			if err != nil {
				return err
			}
			if num(used["total"]) > 0 {
				return fail(c, 409, "IN_USE", "Record is in use")
			}
		}
		q := "DELETE FROM " + spec.Table + " WHERE id=?"
		if spec.SoftDelete {
			q = "UPDATE " + spec.Table + " SET is_deleted=true,updated_at=now() WHERE id=?"
		}
		if err = a.Repo.Exec(c.UserContext(), q, i); err != nil {
			return err
		}
		return data(c, Row{"id": i, "success": true})
	}
}

func normalizeResource(row Row, spec Resource) {
	for key := range spec.JSON {
		v := row[key]
		for i := 0; i < 3; i++ {
			s, ok := v.(string)
			if !ok {
				break
			}
			var d any
			if json.Unmarshal([]byte(s), &d) != nil {
				break
			}
			v = d
		}
		row[key] = v
	}
	if spec.Table == "widgets" {
		row["config"] = obj(row["config"])
	}
}
