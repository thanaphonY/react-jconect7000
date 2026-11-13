package httpapi

import (
	"context"
	"fmt"
	"github.com/gofiber/fiber/v2"
	"jconnect/go-backend/internal/service"
	"regexp"
	"strconv"
	"strings"
	"time"
)

const defaultHealthTemplate = "{name}\n{datetime}\nStatus: {status}\n\n{alarms}"

func scheduleInput(b Row) (Row, time.Time, error) {
	kind := text(b["scheduleType"])
	if kind != "daily" && kind != "weekly" && kind != "monthly" {
		return nil, time.Time{}, invalid("Schedule type must be daily, weekly or monthly")
	}
	clock := text(b["scheduleTime"])
	parsed, err := time.Parse("15:04", clock)
	if err != nil {
		return nil, time.Time{}, invalid("Time must be HH:mm")
	}
	weekday := num(b["scheduleWeekday"])
	if weekday == 0 {
		weekday = 1
	}
	day := num(b["scheduleDay"])
	if day == 0 {
		day = 1
	}
	if weekday < 1 || weekday > 7 || day < 1 || day > 28 {
		return nil, time.Time{}, invalid("Invalid schedule day")
	}
	zone := text(b["timezone"])
	if zone == "" {
		zone = "Asia/Bangkok"
	}
	loc, err := time.LoadLocation(zone)
	if err != nil {
		return nil, time.Time{}, invalid("Invalid timezone")
	}
	now := time.Now().In(loc)
	next := time.Date(now.Year(), now.Month(), now.Day(), parsed.Hour(), parsed.Minute(), 0, 0, loc)
	switch kind {
	case "daily":
		if !next.After(now) {
			next = next.AddDate(0, 0, 1)
		}
	case "weekly":
		current := (int(next.Weekday())+6)%7 + 1
		diff := (int(weekday) - current + 7) % 7
		next = next.AddDate(0, 0, diff)
		if !next.After(now) {
			next = next.AddDate(0, 0, 7)
		}
	case "monthly":
		next = time.Date(now.Year(), now.Month(), int(day), parsed.Hour(), parsed.Minute(), 0, 0, loc)
		if !next.After(now) {
			next = next.AddDate(0, 1, 0)
		}
	}
	return Row{"time": clock, "weekday": weekday, "day": day}, next, nil
}
func normalizeReportSchedule(b Row) (Row, error) {
	conf, next, err := scheduleInput(b)
	if err != nil {
		return nil, err
	}
	out := Row{}
	for _, k := range []string{"templateId", "name", "isActive", "scheduleType", "timezone", "exportFormat", "recipients", "reportParams"} {
		if v, ok := b[k]; ok {
			out[k] = v
		}
	}
	if !nonempty(out["timezone"]) {
		out["timezone"] = "Asia/Bangkok"
	}
	out["scheduleConfig"] = conf
	out["nextRunAt"] = next
	return out, nil
}
func (a *App) saveHealth(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	if !nonempty(b["name"]) {
		return invalid("Name is required")
	}
	conf, next, err := scheduleInput(b)
	if err != nil {
		return err
	}
	i := num(b["id"])
	if err = a.writable(c, "notification", actor(c).ID(), i == 0); err != nil {
		return err
	}
	template := text(b["messageTemplate"])
	if template == "" {
		template = defaultHealthTemplate
	}
	channels := arr(b["channelIds"])
	for _, ch := range channels {
		if num(ch) < 1 {
			return invalid("Invalid notification channel")
		}
		if _, err = a.row(c, `SELECT id FROM notification_channels WHERE id=? AND is_deleted=false`, num(ch)); err != nil {
			return invalid("Notification channel not found")
		}
	}
	args := []any{b["name"], b["isActive"], b["scheduleType"], jsonValue(conf), jsonValue(channels), template, next}
	if i == 0 {
		r, err := a.row(c, `INSERT INTO health_checks(name,is_active,schedule_type,schedule_config,channel_ids,message_template,next_run_at,timezone,rooms) VALUES(?,?,?,?::jsonb,?::jsonb,?,?,'Asia/Bangkok','[]'::jsonb) RETURNING id`, args...)
		if err != nil {
			return err
		}
		i = num(r["id"])
	} else {
		if err = a.Repo.Exec(c.UserContext(), `UPDATE health_checks SET name=?,is_active=?,schedule_type=?,schedule_config=?::jsonb,channel_ids=?::jsonb,message_template=?,next_run_at=?,updated_at=now() WHERE id=?`, append(args, i)...); err != nil {
			return err
		}
	}
	return data(c, Row{"success": true, "id": i, "message": "Saved"})
}
func (a *App) renderHealth(ctx context.Context, b Row) (string, string, error) {
	alarms, err := a.Repo.Query(ctx, `SELECT a.message,a.metric_key,d.name,d.location FROM alarm_state a JOIN devices d ON d.id=a.device_id WHERE a.active=true AND d.is_deleted=false AND d.is_active=true ORDER BY a.last_triggered DESC LIMIT 20`)
	if err != nil {
		return "", "", err
	}
	count, err := a.Repo.One(ctx, `SELECT count(*) AS total FROM alarm_state a JOIN devices d ON d.id=a.device_id WHERE a.active=true AND d.is_deleted=false AND d.is_active=true`)
	if err != nil {
		return "", "", err
	}
	status, code := "System Normal", "normal"
	lines := []string{}
	if num(count["total"]) > 0 {
		status = "Attention"
		code = "attention"
		lines = append(lines, "Open alarms")
	}
	for _, r := range alarms {
		lines = append(lines, "- "+text(r["location"])+" / "+text(r["name"])+": "+text(r["message"]))
	}
	message := text(b["messageTemplate"])
	if message == "" {
		message = text(b["message_template"])
	}
	if message == "" {
		message = defaultHealthTemplate
	}
	now := time.Now().In(time.FixedZone("Asia/Bangkok", 7*3600))
	message = strings.NewReplacer("{name}", text(b["name"]), "{datetime}", now.Format("02/01/2006 15:04"), "{status}", status, "{alarms_count}", strconv.FormatInt(num(count["total"]), 10), "{alarms}", strings.Join(lines, "\n")).Replace(message)
	pattern := regexp.MustCompile(`\{(\d+)\.([^}]+)\}`)
	var queryErr error
	message = pattern.ReplaceAllStringFunc(message, func(match string) string {
		m := pattern.FindStringSubmatch(match)
		r, err := a.Repo.One(ctx, `SELECT value,value_text,value_bool,value_json FROM device_data WHERE device_id=? AND lower(key)=lower(?) ORDER BY timestamp DESC LIMIT 1`, m[1], m[2])
		if err != nil {
			queryErr = err
			return "-"
		}
		if r == nil {
			return "-"
		}
		for _, k := range []string{"value", "valueText", "valueBool", "valueJson"} {
			if v := r[k]; v != nil {
				return fmt.Sprint(v)
			}
		}
		return "-"
	})
	return message, code, queryErr
}
func (a *App) healthPreview(c *fiber.Ctx) error {
	if err := a.require(c, "notification"); err != nil {
		return err
	}
	b, err := body(c)
	if err != nil {
		return err
	}
	message, status, err := a.renderHealth(c.UserContext(), b)
	if err != nil {
		return err
	}
	return data(c, Row{"success": true, "message": message, "status": status})
}
func (a *App) healthSend(c *fiber.Ctx) error {
	if err := a.writable(c, "notification", actor(c).ID(), false); err != nil {
		return err
	}
	i, err := id(c)
	if err != nil {
		return err
	}
	r, err := a.row(c, `SELECT * FROM health_checks WHERE id=?`, i)
	if err != nil {
		return err
	}
	message, state, err := a.renderHealth(c.UserContext(), r)
	if err != nil {
		return err
	}
	results := []Row{}
	success := true
	channels := arr(r["channelIds"])
	if len(channels) == 0 {
		return invalid("No channel selected")
	}
	for _, ch := range channels {
		channel, err := a.Repo.One(c.UserContext(), `SELECT id,name,is_active FROM notification_channels WHERE id=? AND is_deleted=false`, num(ch))
		if err != nil {
			return err
		}
		ok := channel != nil && channel["isActive"] == true
		var problem string
		if ok {
			code, p, e := service.Call(c.UserContext(), service.EnvironmentURL("ALARM_ENGINE_URL", "http://127.0.0.1:3004"), "POST", "api/send-test", Row{"channelId": num(ch), "message": message}, "")
			ok = e == nil && code < 400 && obj(p)["success"] == true
			if !ok {
				problem = "Delivery failed"
			}
		} else {
			problem = "Channel inactive or missing"
		}
		if !ok {
			success = false
		}
		results = append(results, Row{"channel_id": num(ch), "success": ok, "error": problem})
	}
	if !success {
		state = "failed"
	}
	if err = a.Repo.Exec(c.UserContext(), `INSERT INTO health_check_logs(health_check_id,status,message,channel_results) VALUES(?,?,?,?::jsonb)`, i, state, message, jsonValue(results)); err != nil {
		return err
	}
	return data(c, Row{"success": success, "results": results, "message": message})
}
