package httpapi

import (
	"fmt"
	"github.com/gofiber/fiber/v2"
	"strings"
)

const activeAlarmSQL = `SELECT a.*,d.name AS device_name,COALESCE((SELECT m->>'label' FROM jsonb_array_elements(CASE WHEN jsonb_typeof(d.metrics)='array' THEN d.metrics ELSE '[]'::jsonb END) m WHERE m->>'key'=a.metric_key LIMIT 1),a.metric_key) AS metric_label FROM alarm_state a JOIN devices d ON d.id=a.device_id WHERE a.active=true AND d.is_active=true AND d.is_deleted=false`

func (a *App) activeAlarms(c *fiber.Ctx) error {
	if err := a.require(c, "alarmstate"); err != nil && !actor(c).Read("dashboard") && !actor(c).Regional {
		return err
	}
	return a.list(c, activeAlarmSQL+" AND "+scope(c, "d")+" ORDER BY a.last_triggered DESC")
}
func (a *App) acknowledge(c *fiber.Ctx) error {
	if err := a.require(c, "alarmstate"); err != nil {
		return err
	}
	if !a.Write {
		return fiber.NewError(423, "Existing database is read-only")
	}
	i, err := c.ParamsInt("deviceId")
	if err != nil || i < 1 {
		return invalid("Invalid device ID")
	}
	if _, err = a.row(c, `SELECT d.id FROM devices d WHERE d.id=? AND `+scope(c, "d"), i); err != nil {
		return err
	}
	r, err := a.row(c, `UPDATE alarm_state SET acknowledged=true,acknowledged_by=?,acknowledged_at=now() WHERE device_id=? AND metric_key=? RETURNING device_id`, actor(c).User["name"], i, c.Params("key"))
	if err != nil {
		return err
	}
	return data(c, r)
}
func (a *App) eventLogs(c *fiber.Ctx) error {
	if err := a.require(c, "eventlog"); err != nil {
		return err
	}
	p, size := page(c)
	where := "TRUE"
	args := []any{}
	if s := c.Query("search"); s != "" {
		where = "e.message ILIKE ?"
		args = append(args, "%"+s+"%")
	}
	count, err := a.Repo.One(c.UserContext(), `SELECT count(*) AS total FROM event_logs e WHERE `+where, args...)
	if err != nil {
		return err
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT e.*,COALESCE(u.name,'') AS user_name FROM event_logs e LEFT JOIN ia_users u ON u.ia_users_id=e.user_id WHERE `+where+` ORDER BY e.created_at DESC,e.id DESC LIMIT ? OFFSET ?`, append(args, size, (p-1)*size)...)
	if err != nil {
		return err
	}
	return data(c, Row{"logs": rows, "total": count["total"], "page": p, "pageSize": size})
}
func alarmFilter(c *fiber.Ctx) (string, []any) {
	where := []string{scope(c, "d")}
	args := []any{}
	for _, pair := range [][2]string{{"device_filter", "a.device_id"}, {"severity_filter", "a.severity"}, {"status_filter", "a.status"}} {
		s := c.Query(pair[0])
		if s != "" && s != "all" {
			where = append(where, pair[1]+"=?")
			args = append(args, s)
		}
	}
	if s := c.Query("search"); s != "" {
		where = append(where, "(a.message ILIKE ? OR d.name ILIKE ?)")
		args = append(args, "%"+s+"%", "%"+s+"%")
	}
	switch c.Query("range", "last_30_days") {
	case "today":
		where = append(where, "a.created_at >= (date_trunc('day',now() AT TIME ZONE 'Asia/Bangkok') AT TIME ZONE 'Asia/Bangkok')")
	case "yesterday":
		where = append(where, "a.created_at >= ((date_trunc('day',now() AT TIME ZONE 'Asia/Bangkok')-interval '1 day') AT TIME ZONE 'Asia/Bangkok') AND a.created_at < (date_trunc('day',now() AT TIME ZONE 'Asia/Bangkok') AT TIME ZONE 'Asia/Bangkok')")
	case "last_7_days":
		where = append(where, "a.created_at>=now()-interval '7 days'")
	case "last_30_days":
		where = append(where, "a.created_at>=now()-interval '30 days'")
	case "custom":
		if s := c.Query("startDate", c.Query("startRange")); s != "" {
			where = append(where, "a.created_at>=?::timestamptz")
			args = append(args, s)
		}
		if s := c.Query("endDate", c.Query("endRange")); s != "" {
			where = append(where, "a.created_at<=?::timestamptz")
			args = append(args, s)
		}
	}
	return strings.Join(where, " AND "), args
}
func (a *App) alarmLogs(c *fiber.Ctx) error {
	if err := a.require(c, "alarmlogs"); err != nil {
		return err
	}
	where, args := alarmFilter(c)
	p, size := page(c)
	count, err := a.Repo.One(c.UserContext(), `SELECT count(*) AS total FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id WHERE `+where, args...)
	if err != nil {
		return err
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT a.*,d.name AS device_name FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id WHERE `+where+` ORDER BY a.created_at DESC,a.id DESC LIMIT ? OFFSET ?`, append(args, size, (p-1)*size)...)
	if err != nil {
		return err
	}
	for _, r := range rows {
		snapshotURL(r)
	}
	return data(c, Row{"logs": rows, "total": count["total"], "page": p, "pageSize": size, "totalPages": (num(count["total"]) + int64(size) - 1) / int64(size)})
}
func (a *App) alarmStatistics(c *fiber.Ctx) error {
	if err := a.require(c, "alarmlogs"); err != nil {
		return err
	}
	r, err := a.row(c, `SELECT count(*) AS total,count(*) FILTER(WHERE a.status='active') AS active,count(*) FILTER(WHERE a.severity='critical') AS critical,count(*) FILTER(WHERE a.created_at>=now()-interval '24 hours') AS today FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id WHERE `+scope(c, "d"))
	if err != nil {
		return err
	}
	severity, err := a.Repo.Query(c.UserContext(), `SELECT a.severity,count(*) AS count FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id WHERE `+scope(c, "d")+` GROUP BY a.severity ORDER BY count DESC`)
	if err != nil {
		return err
	}
	devices, err := a.Repo.Query(c.UserContext(), `SELECT d.name AS device_name,count(*) AS count FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id WHERE `+scope(c, "d")+` GROUP BY d.id,d.name ORDER BY count DESC LIMIT 5`)
	if err != nil {
		return err
	}
	r["severityBreakdown"] = severity
	r["deviceBreakdown"] = devices
	r["totalAlarms"] = r["total"]
	r["activeAlarms"] = r["active"]
	r["criticalAlarms"] = r["critical"]
	r["todayAlarms"] = r["today"]
	return data(c, r)
}
func (a *App) alarmFilters(c *fiber.Ctx) error {
	if err := a.require(c, "alarmlogs"); err != nil {
		return err
	}
	devices, err := a.Repo.Query(c.UserContext(), `SELECT d.id,d.name FROM devices d WHERE d.is_deleted=false AND `+scope(c, "d")+` ORDER BY d.name`)
	if err != nil {
		return err
	}
	severity, err := a.Repo.Query(c.UserContext(), `SELECT DISTINCT a.severity FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id WHERE `+scope(c, "d"))
	if err != nil {
		return err
	}
	statuses, err := a.Repo.Query(c.UserContext(), `SELECT DISTINCT a.status FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id WHERE `+scope(c, "d"))
	if err != nil {
		return err
	}
	sv := []string{}
	st := []string{}
	for _, r := range severity {
		sv = append(sv, text(r["severity"]))
	}
	for _, r := range statuses {
		st = append(st, text(r["status"]))
	}
	return data(c, Row{"devices": devices, "severities": sv, "statuses": st})
}
func (a *App) alarmDetail(c *fiber.Ctx) error {
	if err := a.require(c, "alarmlogs"); err != nil {
		return err
	}
	i, err := id(c)
	if err != nil {
		return err
	}
	r, err := a.row(c, `SELECT a.*,d.name AS device_name FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id WHERE a.id=? AND `+scope(c, "d"), i)
	if err != nil {
		return err
	}
	related, err := a.Repo.Query(c.UserContext(), `SELECT id,created_at,severity,status,message FROM alarm_logs WHERE device_id=? AND metric_key=? AND id<>? ORDER BY created_at DESC LIMIT 5`, r["deviceId"], r["metricKey"], i)
	if err != nil {
		return err
	}
	dispatch, err := a.Repo.Query(c.UserContext(), `SELECT x.*,c.name AS channel_name FROM alarm_log_dispatch x LEFT JOIN notification_channels c ON c.id=x.channel_id WHERE x.alarm_log_id=? ORDER BY x.created_at DESC`, i)
	if err != nil {
		return err
	}
	snapshotURL(r)
	r["updatedAt"] = nil
	return data(c, Row{"alarm": r, "relatedAlarms": related, "dispatchHistory": dispatch})
}
func (a *App) notificationSummary(c *fiber.Ctx) error {
	if err := a.require(c, "notification"); err != nil {
		return err
	}
	channels, err := a.Repo.Query(c.UserContext(), `SELECT c.*,(SELECT count(*) FROM devices d WHERE d.is_deleted=false AND d.is_active=true AND EXISTS(SELECT 1 FROM jsonb_array_elements(CASE WHEN jsonb_typeof(d.metrics)='array' THEN d.metrics ELSE '[]'::jsonb END) metric CROSS JOIN LATERAL jsonb_array_elements(COALESCE(metric->'triggers','[]'::jsonb)) trigger CROSS JOIN LATERAL jsonb_array_elements(COALESCE(trigger->'channels','[]'::jsonb)) channel WHERE channel #>> '{}' = c.id::text)) AS devices_count FROM notification_channels c WHERE c.is_deleted=false ORDER BY c.name`)
	if err != nil {
		return err
	}
	counts, err := a.Repo.One(c.UserContext(), `SELECT (SELECT count(*) FROM devices WHERE is_deleted=false) AS total_devices,(SELECT count(*) FROM alarm_logs WHERE created_at>=date_trunc('day',now())) AS today_alarms`)
	if err != nil {
		return err
	}
	active := 0
	for _, r := range channels {
		if r["isActive"] == true {
			active++
		}
	}
	return data(c, Row{"totalChannels": len(channels), "activeChannels": active, "totalDevices": counts["totalDevices"], "todayAlarms": counts["todayAlarms"], "channels": channels})
}
func (a *App) channelMessages(c *fiber.Ctx) error {
	if err := a.require(c, "notification"); err != nil {
		return err
	}
	i, err := id(c)
	if err != nil {
		return err
	}
	return a.list(c, `SELECT l.id,l.status,l.sent_at,l.message,d.name AS device_name FROM alarm_log_dispatch x JOIN alarm_logs l ON l.id=x.alarm_log_id LEFT JOIN devices d ON d.id=l.device_id WHERE x.channel_id=? ORDER BY x.created_at DESC LIMIT 100`, i)
}
func (a *App) calculations(c *fiber.Ctx) error {
	if err := a.require(c, "calculation"); err != nil {
		return err
	}
	devices, err := a.Repo.Query(c.UserContext(), `SELECT id,name,is_active,metrics FROM devices WHERE is_deleted=false ORDER BY name`)
	if err != nil {
		return err
	}
	calculations := []Row{}
	for _, d := range devices {
		d["metrics"] = arr(d["metrics"])
		for _, v := range arr(d["metrics"]) {
			m := obj(v)
			if m["type"] == "calc" || len(obj(m["calc"])) > 0 {
				calculations = append(calculations, Row{"deviceId": d["id"], "deviceName": d["name"], "isActive": d["isActive"], "metric": m})
			}
		}
	}
	return data(c, Row{"calculations": calculations, "devices": devices})
}
func safeColumn(k string) string { return fmt.Sprintf("%q", k) }
