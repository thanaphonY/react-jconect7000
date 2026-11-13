package httpapi

import (
	"github.com/gofiber/fiber/v2"
	"strings"
)

func (a *App) reportSchedules(c *fiber.Ctx) error {
	if err := a.require(c, "reports"); err != nil {
		return err
	}
	return a.list(c, `SELECT s.*,t.name AS template_name FROM report_schedules s LEFT JOIN report_templates t ON t.id=s.template_id ORDER BY s.name`)
}
func (a *App) reportFiles(c *fiber.Ctx) error {
	if err := a.require(c, "reports"); err != nil {
		return err
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT f.*,t.name AS template_name FROM report_export_files f LEFT JOIN report_templates t ON t.id=f.template_id ORDER BY f.exported_at DESC LIMIT 500`)
	if err != nil {
		return err
	}
	return data(c, rows)
}
func (a *App) reportLookups(c *fiber.Ctx) error {
	if err := a.require(c, "reports"); err != nil {
		return err
	}
	switch c.Params("kind") {
	case "devices":
		return a.list(c, `SELECT d.id,d.name,d.location FROM devices d WHERE d.is_deleted=false AND `+scope(c, "d")+` ORDER BY d.name`)
	case "metrics":
		return a.list(c, `SELECT DISTINCT m->>'key' AS key,m->>'label' AS label FROM devices d CROSS JOIN LATERAL jsonb_array_elements(CASE WHEN jsonb_typeof(d.metrics)='array' THEN d.metrics ELSE '[]'::jsonb END) m WHERE d.is_deleted=false AND `+scope(c, "d"))
	case "rooms":
		rows, err := a.Repo.Query(c.UserContext(), `SELECT DISTINCT d.location FROM devices d WHERE d.is_deleted=false AND `+scope(c, "d")+` ORDER BY d.location`)
		if err != nil {
			return err
		}
		out := []string{}
		for _, r := range rows {
			if nonempty(r["location"]) {
				out = append(out, text(r["location"]))
			}
		}
		return data(c, out)
	case "action-types":
		return a.list(c, `SELECT DISTINCT action_type FROM event_logs ORDER BY action_type`)
	case "entity-types":
		return a.list(c, `SELECT DISTINCT entity_type FROM event_logs ORDER BY entity_type`)
	}
	return fiber.ErrNotFound
}
func (a *App) reportRows(c *fiber.Ctx) ([]Row, string, error) {
	if err := a.require(c, "reports"); err != nil {
		return nil, "", err
	}
	i, err := id(c)
	if err != nil {
		return nil, "", err
	}
	t, err := a.row(c, `SELECT config FROM report_templates WHERE id=?`, i)
	if err != nil {
		return nil, "", err
	}
	b, err := body(c)
	if err != nil {
		return nil, "", err
	}
	config := obj(t["config"])
	filters := obj(b["filters"])
	stored := obj(config["filters"])
	source := text(config["data_source"])
	var q, ts string
	where := []string{scope(c, "d")}
	args := []any{}
	switch source {
	case "device_data":
		q = `SELECT m.timestamp,m.device_id,d.name AS device_name,m.key,m.key AS metric_label,m.value,m.value_text,m.value_bool,m.value_json FROM device_data m JOIN devices d ON d.id=m.device_id`
		ts = "m.timestamp"
		where = append(where, "d.is_deleted=false")
		if dev := num(filters["device_id"]); dev > 0 {
			where = append(where, "m.device_id=?")
			args = append(args, dev)
		}
		if key := text(filters["metric"]); key != "" {
			where = append(where, "m.key=?")
			args = append(args, key)
		}
		pairs := arr(stored["selected_device_metrics"])
		if len(pairs) > 0 {
			ors := []string{}
			for _, p := range pairs {
				m := obj(p)
				if num(m["device_id"]) > 0 && nonempty(m["metric"]) {
					ors = append(ors, "(m.device_id=? AND m.key=?)")
					args = append(args, num(m["device_id"]), m["metric"])
				}
			}
			if len(ors) > 0 {
				where = append(where, "("+strings.Join(ors, " OR ")+")")
			}
		}
	case "alarm_logs":
		q = `SELECT a.*,d.name AS device_name FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id`
		ts = "a.created_at"
	case "event_logs":
		if actor(c).Regional {
			return nil, "", fiber.ErrForbidden
		}
		q = `SELECT e.*,u.name AS user_name FROM event_logs e LEFT JOIN ia_users u ON u.ia_users_id=e.user_id`
		ts = "e.created_at"
		where = []string{"TRUE"}
	default:
		return nil, "", invalid("Unsupported configured report data source")
	}
	if s := text(filters["start_date"]); s != "" {
		where = append(where, ts+">=?::timestamptz")
		args = append(args, s)
	}
	if s := text(filters["end_date"]); s != "" {
		where = append(where, ts+"<=?::timestamptz")
		args = append(args, s)
	}
	if s := text(filters["date"]); s != "" {
		where = append(where, "("+ts+" AT TIME ZONE 'Asia/Bangkok')::date=?::date")
		args = append(args, s)
	}
	if room := text(filters["room"]); room != "" && source != "event_logs" {
		where = append(where, "d.location=?")
		args = append(args, room)
	}
	order := "DESC"
	if stored["sort_order"] == "asc" {
		order = "ASC"
	}
	rows, err := a.Repo.Query(c.UserContext(), q+" WHERE "+strings.Join(where, " AND ")+" ORDER BY "+ts+" "+order+" LIMIT 10000", args...)
	if err != nil {
		return nil, "", err
	}
	for _, r := range rows {
		for _, pair := range [][2]string{{"deviceName", "device_name"}, {"deviceId", "device_id"}, {"metricLabel", "metric_label"}, {"valueText", "value_text"}, {"valueBool", "value_bool"}, {"valueJson", "value_json"}, {"createdAt", "created_at"}} {
			if v, ok := r[pair[0]]; ok {
				r[pair[1]] = v
				delete(r, pair[0])
			}
		}
	}
	return rows, source, nil
}
func (a *App) reportFetch(c *fiber.Ctx) error {
	rows, source, err := a.reportRows(c)
	if err != nil {
		return err
	}
	return data(c, Row{"rows": rows, "count": len(rows), "dataSource": source, "truncated": len(rows) == 10000})
}
