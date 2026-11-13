package httpapi

import (
	"github.com/gofiber/fiber/v2"
	"jconnect/go-backend/internal/repository"
	"strconv"
	"strings"
	"time"
)

var retentionRules = []struct {
	Table, Column string
	Days          int
	Unix          bool
}{{"device_data", "timestamp", 90, false}, {"event_logs", "created_at", 180, false}, {"alarm_logs", "created_at", 365, false}, {"alarm_log_dispatch", "created_at", 365, false}, {"health_check_logs", "sent_at", 30, false}, {"report_export_files", "exported_at", 30, false}, {"ia_sessions", "timestamp", 7, true}}

func (a *App) retention(c *fiber.Ctx) error {
	if err := a.require(c, "setting"); err != nil {
		return err
	}
	settings, err := a.Repo.Query(c.UserContext(), `SELECT keys,value FROM ia_setting WHERE keys LIKE 'retention_%'`)
	if err != nil {
		return err
	}
	values := map[string]string{}
	for _, r := range settings {
		values[text(r["keys"])] = text(r["value"])
	}
	policies := []Row{}
	for _, rule := range retentionRules {
		days := rule.Days
		if s := values["retention_"+rule.Table+"_days"]; s != "" {
			if n, e := strconv.Atoi(s); e == nil && n > 0 {
				days = n
			}
		}
		enabled := values["retention_"+rule.Table+"_enabled"] != "0"
		row := Row{"table": rule.Table, "column": rule.Column, "days": days, "enabled": enabled}
		if c.Query("preview") == "1" {
			var count int64
			if enabled {
				q := "SELECT count(*) AS total FROM " + rule.Table + " WHERE " + safeColumn(rule.Column) + " < now() - (? * interval '1 day')"
				if rule.Unix {
					q = "SELECT count(*) AS total FROM " + rule.Table + " WHERE " + safeColumn(rule.Column) + " < extract(epoch FROM (now() - (? * interval '1 day')))"
				}
				r, err := a.Repo.One(c.UserContext(), q, days)
				if err != nil {
					return err
				}
				count = num(r["total"])
			}
			row["rows"] = count
		}
		policies = append(policies, row)
	}
	return data(c, policies)
}
func (a *App) retentionSave(c *fiber.Ctx) error {
	if err := a.writable(c, "setting", actor(c).ID(), false); err != nil {
		return err
	}
	b, err := body(c)
	if err != nil {
		return err
	}
	policies := arr(b["policies"])
	valid := map[string]bool{}
	for _, r := range retentionRules {
		valid[r.Table] = true
	}
	for _, v := range policies {
		r := obj(v)
		if !valid[text(r["table"])] || num(r["days"]) < 1 {
			return invalid("Invalid retention policy")
		}
	}
	err = a.Repo.Transaction(c.UserContext(), func(repo *repository.Repository) error {
		for _, v := range policies {
			r := obj(v)
			en := "0"
			if r["enabled"] == true {
				en = "1"
			}
			for _, pair := range [][2]string{{"_days", strconv.FormatInt(num(r["days"]), 10)}, {"_enabled", en}} {
				key := "retention_" + text(r["table"]) + pair[0]
				if err := repo.Exec(c.UserContext(), `UPDATE ia_setting SET value=? WHERE keys=?`, pair[1], key); err != nil {
					return err
				}
				if err := repo.Exec(c.UserContext(), `INSERT INTO ia_setting(keys,value) SELECT ?,? WHERE NOT EXISTS(SELECT 1 FROM ia_setting WHERE keys=?)`, key, pair[1], key); err != nil {
					return err
				}
			}
		}
		return nil
	})
	if err != nil {
		return err
	}
	return a.retention(c)
}

var backupTables = []string{"device_categories", "device_templates", "devices", "widgets", "dashboard_layouts", "dashboard_layout_items", "notification_channels", "report_categories", "report_templates", "report_schedules", "lorawan_applications", "ia_menu", "ia_permission", "ia_setting"}

func (a *App) backupInfo(c *fiber.Ctx) error {
	if err := a.require(c, "setting"); err != nil {
		return err
	}
	counts := []Row{}
	for _, table := range backupTables {
		r, err := a.Repo.One(c.UserContext(), "SELECT count(*) AS total FROM "+table)
		if err != nil {
			return err
		}
		counts = append(counts, Row{"table": table, "rows": r["total"]})
	}
	return data(c, counts)
}
func (a *App) backupDownload(c *fiber.Ctx) error {
	if err := a.require(c, "setting"); err != nil {
		return err
	}
	tables := append([]string{}, backupTables...)
	include := c.Query("includeUsers") == "1"
	if include {
		tables = append(tables, "ia_users")
	}
	payload := Row{}
	for _, table := range tables {
		rows, err := a.Repo.QueryRaw(c.UserContext(), "SELECT * FROM "+table)
		if err != nil {
			return err
		}
		payload[table] = rows
	}
	c.Set("Content-Disposition", `attachment; filename="jconnect-config-`+time.Now().Format("20060102-150405")+`.json"`)
	return c.JSON(Row{"software": "JConnect", "kind": "config", "exported_at": time.Now().Format(time.RFC3339), "include_users": include, "tables": payload})
}
func (a *App) backupValidate(c *fiber.Ctx) error {
	if err := a.require(c, "setting"); err != nil {
		return err
	}
	b, err := body(c)
	if err != nil {
		return err
	}
	if b["software"] != "JConnect" || b["kind"] != "config" {
		return invalid("Invalid JConnect config backup")
	}
	tables := obj(b["tables"])
	for _, table := range backupTables {
		if _, ok := tables[table].([]any); !ok {
			return invalid("Missing config table: " + table)
		}
	}
	return data(c, Row{"valid": true, "tables": len(tables), "applied": false, "message": "Backup validated. No existing data was changed."})
}
func (a *App) emailTemplates(c *fiber.Ctx) error {
	if err := a.require(c, "setting"); err != nil {
		return err
	}
	return a.list(c, `SELECT id,module,code,template_name,html FROM ia_email_templates ORDER BY id`)
}
func (a *App) emailTemplateSave(c *fiber.Ctx) error {
	if err := a.writable(c, "setting", actor(c).ID(), false); err != nil {
		return err
	}
	i, err := id(c)
	if err != nil {
		return err
	}
	b, err := body(c)
	if err != nil {
		return err
	}
	if !nonempty(b["templateName"]) {
		return invalid("Template name is required")
	}
	r, err := a.row(c, `UPDATE ia_email_templates SET template_name=?,html=? WHERE id=? RETURNING id,module,code,template_name,html`, strings.TrimSpace(text(b["templateName"])), text(b["html"]), i)
	if err != nil {
		return err
	}
	return data(c, r)
}
