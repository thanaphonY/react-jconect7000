package httpapi

import (
	"fmt"
	"github.com/gofiber/fiber/v2"
	"jconnect/go-backend/internal/repository"
	"os"
	"path/filepath"
	"strings"
)

func (a *App) alarmChange(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	action := c.Params("action")
	if action != "acknowledge" && action != "resolve" && action != "bulk-acknowledge" && action != "bulk-resolve" {
		return fiber.ErrNotFound
	}
	resolving := strings.Contains(action, "resolve")
	if resolving && !nonempty(b["resolution_note"]) {
		return invalid("Resolution note is required")
	}
	ids := []int64{}
	if strings.HasPrefix(action, "bulk-") {
		for _, v := range arr(b["alarm_ids"]) {
			if num(v) < 1 {
				return invalid("Positive alarm IDs are required")
			}
			ids = append(ids, num(v))
		}
	} else {
		ids = append(ids, num(b["alarm_id"]))
	}
	if len(ids) == 0 || len(ids) > 500 || ids[0] < 1 {
		return invalid("Alarm IDs are required")
	}
	if err = a.writable(c, "alarmlogs", nil, false); err != nil {
		return err
	}
	success, failed := 0, 0
	err = a.Repo.Transaction(c.UserContext(), func(repo *repository.Repository) error {
		for _, id := range ids {
			visible, err := repo.One(c.UserContext(), `SELECT a.id FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id WHERE a.id=? AND `+scope(c, "d"), id)
			if err != nil {
				return err
			}
			if visible == nil {
				failed++
				continue
			}
			status := "acknowledged"
			where := "status='active'"
			if resolving {
				status = "resolved"
				where = "status IN ('active','acknowledged')"
			}
			changed, err := repo.One(c.UserContext(), `UPDATE alarm_logs SET status=? WHERE id=? AND `+where+` RETURNING id`, status, id)
			if err != nil {
				return err
			}
			if changed == nil {
				failed++
				continue
			}
			metadata := Row{"alarm_id": id, "new_status": status, "note": b["note"], "resolution_note": b["resolution_note"], "bulk_operation": strings.HasPrefix(action, "bulk-")}
			if err = repo.Exec(c.UserContext(), `INSERT INTO event_logs(user_id,action_type,entity_type,entity_id,message,metadata) VALUES(?,?,'alarm_logs',?,?,?::jsonb)`, actor(c).ID(), action, id, "Alarm "+status, jsonValue(metadata)); err != nil {
				return err
			}
			success++
		}
		return nil
	})
	if err != nil {
		return err
	}
	return data(c, Row{"success": success > 0, "successCount": success, "failedCount": failed})
}
func (a *App) alarmPreview(c *fiber.Ctx) error {
	if err := a.require(c, "alarmlogs"); err != nil {
		return err
	}
	where, args := alarmFilter(c)
	rows, err := a.Repo.Query(c.UserContext(), `SELECT a.*,d.name AS device_name FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id WHERE `+where+` ORDER BY a.created_at DESC LIMIT 10000`, args...)
	if err != nil {
		return err
	}
	active := 0
	severities, devices := map[string]bool{}, map[string]bool{}
	for _, r := range rows {
		if r["status"] == "active" {
			active++
		}
		severities[text(r["severity"])] = true
		devices[text(r["deviceName"])] = true
	}
	return data(c, Row{"logs": rows, "filters": c.Queries(), "stats": Row{"totalRecords": len(rows), "activeAlarms": active, "severityLevels": len(severities), "devices": len(devices)}, "truncated": len(rows) == 10000})
}
func (a *App) alarmSnapshot(c *fiber.Ctx) error {
	if err := a.require(c, "alarmlogs"); err != nil {
		return err
	}
	i, err := id(c)
	if err != nil {
		return err
	}
	r, err := a.row(c, `SELECT a.snapshot_path FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id WHERE a.id=? AND `+scope(c, "d"), i)
	if err != nil {
		return err
	}
	name := filepath.Base(text(r["snapshotPath"]))
	if name == "." || name == "" {
		return fiber.ErrNotFound
	}
	ext := strings.ToLower(filepath.Ext(name))
	if ext != ".jpg" && ext != ".jpeg" && ext != ".png" {
		return fiber.ErrNotFound
	}
	path := filepath.Join(a.Root, "media/snapshots", name)
	if _, err = os.Stat(path); err != nil {
		return fiber.ErrNotFound
	}
	c.Set("X-Content-Type-Options", "nosniff")
	return c.SendFile(path)
}
func (a *App) reportDownload(c *fiber.Ctx) error {
	if err := a.require(c, "reports"); err != nil {
		return err
	}
	i, err := id(c)
	if err != nil {
		return err
	}
	r, err := a.row(c, `SELECT file_path,file_name FROM report_export_files WHERE id=?`, i)
	if err != nil {
		return err
	}
	base := filepath.Join(a.Root, "frontend/uploads/reports/export")
	path := filepath.Join(base, filepath.Clean(text(r["filePath"])))
	relative, err := filepath.Rel(base, path)
	if err != nil || strings.HasPrefix(relative, "..") || filepath.IsAbs(relative) {
		return fiber.ErrForbidden
	}
	real, err := filepath.EvalSymlinks(path)
	if err != nil {
		return fiber.ErrNotFound
	}
	relative, err = filepath.Rel(base, real)
	if err != nil || strings.HasPrefix(relative, "..") {
		return fiber.ErrForbidden
	}
	return c.Download(real, filepath.Base(text(r["fileName"])))
}
func snapshotURL(r Row) {
	if nonempty(r["snapshotPath"]) {
		r["snapshotPath"] = fmt.Sprintf("/api/v1/alarm-logs/%d/snapshot", num(r["id"]))
	}
}

func (a *App) alarmExport(c *fiber.Ctx) error {
	if err := a.require(c, "alarmlogs"); err != nil {
		return err
	}
	where, args := alarmFilter(c)
	rows, err := a.Repo.Query(c.UserContext(), `SELECT a.id,a.created_at,d.name AS device_name,a.metric_key,a.severity,a.message,a.status,a.value FROM alarm_logs a LEFT JOIN devices d ON d.id=a.device_id WHERE `+where+` ORDER BY a.created_at DESC LIMIT 10000`, args...)
	if err != nil {
		return err
	}
	return a.exportRows(c, rows, c.Query("format", "csv"), "Alarm Logs")
}
func (a *App) usersExport(c *fiber.Ctx) error {
	if err := a.require(c, "user"); err != nil {
		return err
	}
	q := `SELECT ia_users_id AS id,name,email,user_type,status,create_date FROM ia_users WHERE is_deleted='0' AND lower(user_type)<>'admin'`
	args := []any{}
	if !actor(c).Can("user", "all_read") {
		q += " AND user_id=?"
		args = append(args, fmt.Sprint(actor(c).ID()))
	}
	rows, err := a.Repo.Query(c.UserContext(), q+` ORDER BY ia_users_id LIMIT 10000`, args...)
	if err != nil {
		return err
	}
	return a.exportRows(c, rows, c.Query("format", "excel"), "Users")
}
