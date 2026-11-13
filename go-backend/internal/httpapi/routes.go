package httpapi

import (
	"github.com/gofiber/fiber/v2"
)

func (a *App) routes() {
	app := a.Fiber.Group("/api/v1")
	app.Get("/auth/me", func(c *fiber.Ctx) error { return a.session(c, token(c), actor(c)) })
	app.Post("/uploads/:kind", a.upload)
	app.Get("/profile", a.profile)
	app.Put("/profile", a.updateProfile)
	app.Get("/home", a.layoutView)
	app.Get("/home/:id", a.layoutView)
	app.Get("/dashboards", a.dashboards)
	app.Post("/dashboards", a.saveLayout)
	app.Put("/dashboards/sort-order", a.sortLayouts)
	app.Get("/dashboards/:id", a.layoutView)
	app.Put("/dashboards/:id", a.saveLayout)
	app.Patch("/dashboards/:id", a.saveLayout)
	app.Delete("/dashboards/:id", a.deleteLayout)
	app.Post("/dashboards/:id/home", a.setHome)
	app.Put("/dashboards/:id/layout", a.saveLayoutItems)
	app.Put("/dashboards/:id/items", a.saveLayoutItems)
	app.Post("/dashboards/:id/items", a.saveLayoutItems)
	app.Get("/devices", a.devices)
	app.Get("/devices/filters", a.deviceFilters)
	app.Get("/devices/filter-options", a.deviceFilters)
	app.Get("/devices/metric-sources", a.metricSources)
	app.Get("/devices/:id/metrics/latest", a.latest)
	app.Get("/devices/:id/metrics/:key/latest", a.latest)
	app.Get("/devices/:id/metrics/:key/timeseries", a.timeseries)
	app.Get("/devices/:id/metrics/keys", a.metricKeys)
	app.Get("/devices/:id/metrics", a.metricKeys)
	app.Get("/devices/:id/series", a.timeseries)
	app.Get("/devices/:id/timeseries", a.timeseries)
	app.All("/devices/:id/stream/:action", a.camera)
	app.Get("/devices/:id", a.device)
	app.Post("/devices", a.saveDevice)
	app.Patch("/devices/:id", a.saveDevice)
	app.Put("/devices/:id", a.saveDevice)
	app.Delete("/devices/:id", a.deleteDevice)
	app.Get("/device-categories", a.categories)
	app.Get("/dcim-taxonomy", a.taxonomy)
	app.Post("/device-templates", a.templateWrite)
	app.Patch("/device-templates/:id", a.templateWrite)
	app.Get("/device-templates", a.templates)
	app.Get("/device-templates/:id/preview", a.template)
	app.Get("/device-templates/:id", a.template)
	app.Get("/site-home", a.siteHome)
	app.Get("/site-nodes", a.sites)
	app.Get("/site-nodes/:id", a.site)
	app.Post("/site-nodes", a.saveSite)
	app.Put("/site-nodes/:id", a.saveSite)
	app.Delete("/site-nodes/:id", a.deleteSite)
	app.Get("/site-access", a.siteScopes)
	app.Get("/api-clients", a.apiClients)
	app.Get("/alarms/active", a.activeAlarms)
	app.Post("/alarms/active/:deviceId/:key/acknowledge", a.acknowledge)
	app.Get("/alarm-logs", a.alarmLogs)
	app.Get("/alarm-logs/statistics", a.alarmStatistics)
	app.Get("/alarm-logs/filters", a.alarmFilters)
	app.Post("/alarm-logs/:action", a.alarmChange)
	app.Get("/alarm-logs/export-preview", a.alarmPreview)
	app.Get("/alarm-logs/export", a.alarmExport)
	app.Get("/alarm-logs/:id/snapshot", a.alarmSnapshot)
	app.Get("/alarm-logs/:id", a.alarmDetail)
	app.Get("/event-logs", a.eventLogs)
	app.Get("/notification-channels/summary", a.notificationSummary)
	app.Get("/notification-channels/:id/messages", a.channelMessages)
	app.Get("/notification-channels/:id/devices", a.channelDevices)
	app.Post("/notification-channels/:id/test", a.notificationTest)
	app.Get("/notification-channels/:id/template", func(c *fiber.Ctx) error {
		if err := a.require(c, "notification"); err != nil {
			return err
		}
		i, err := id(c)
		if err != nil {
			return err
		}
		r, err := a.row(c, `SELECT message_template FROM notification_channels WHERE id=? AND is_deleted=false`, i)
		if err != nil {
			return err
		}
		return data(c, Row{"preview": r["messageTemplate"]})
	})
	app.Get("/notification-health-checks", a.healthChecks)
	app.Post("/notification-health-checks", a.saveHealth)
	app.Post("/notification-health-checks/preview", a.healthPreview)
	app.Post("/notification-health-checks/:id/send", a.healthSend)
	app.Post("/notification-channels/:id/test-send", a.notificationTest)
	app.Get("/users/export", a.usersExport)
	app.Get("/users/statistics", a.userStatistics)
	app.Post("/users", a.saveUser)
	app.Put("/users/:id", a.saveUser)
	app.Delete("/users/:id", a.deleteUser)
	app.Put("/settings/:kind", a.settingsWrite)
	app.Get("/users", a.users)
	app.Get("/users/types", a.userTypes)
	app.Get("/retention", a.retention)
	app.Put("/retention", a.retentionSave)
	app.Get("/config-backup", a.backupInfo)
	app.Get("/config-backup/download", a.backupDownload)
	app.Post("/config-backup/validate", a.backupValidate)
	app.Get("/email-templates", a.emailTemplates)
	app.Put("/email-templates/:id", a.emailTemplateSave)
	app.Get("/settings", a.settings)
	app.Get("/settings/permissions", a.permissions)
	app.Get("/series", a.series)
	app.Get("/calculations", a.calculations)
	app.Post("/calculations", a.saveCalculation)
	app.Delete("/calculations/:id/:key", a.deleteCalculation)
	app.Get("/lorawan/applications", a.lorawan)
	app.Get("/lorawan/device-profiles", a.lorawanProfiles)
	app.Get("/control/health", a.controlHealth)
	app.Get("/control/bootstrap", a.controlBootstrap)
	app.All("/control/*", a.controlProxy)
	app.Get("/report-schedules", a.reportSchedules)
	app.Get("/report-files", a.reportFiles)
	app.Get("/report-files/:id", a.reportDownload)
	app.Get("/report-templates/lookups/:kind", a.reportLookups)
	app.Post("/report-templates/:id/fetch", a.reportFetch)
	app.Post("/report-templates/:id/export", a.reportExport)
	app.Get("/report-templates/lookups/metrics-by-device/:id", a.metricKeys)
	for _, spec := range resources {
		s := spec
		if s.Table == "health_checks" {
			app.Delete("/notification-health-checks/:id", a.resourceDelete(s))
		}
		app.Get("/"+s.Path, a.resourceRead(s))
		app.Get("/"+s.Path+"/:id", a.resourceRead(s))
		app.Post("/"+s.Path, a.resourceWrite(s))
		app.Put("/"+s.Path+"/:id", a.resourceWrite(s))
		app.Patch("/"+s.Path+"/:id", a.resourceWrite(s))
		app.Delete("/"+s.Path+"/:id", a.resourceDelete(s))
	}
}
func (a *App) series(c *fiber.Ctx) error {
	if err := a.require(c, "graph"); err != nil {
		return err
	}
	key := c.Query("key", "temperature")
	limit := c.QueryInt("limit", 200)
	if limit < 1 || limit > 500 {
		limit = 200
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT m.timestamp,m.value FROM device_data m JOIN devices d ON d.id=m.device_id WHERE m.key ILIKE ? AND m.value IS NOT NULL AND `+scope(c, "d")+` ORDER BY m.timestamp DESC LIMIT ?`, key, limit)
	if err != nil {
		return err
	}
	for l, r := 0, len(rows)-1; l < r; l, r = l+1, r-1 {
		rows[l], rows[r] = rows[r], rows[l]
	}
	return data(c, Row{"key": key, "points": rows})
}
func (a *App) healthChecks(c *fiber.Ctx) error {
	if err := a.require(c, "notification"); err != nil {
		return err
	}
	checks, err := a.Repo.Query(c.UserContext(), `SELECT * FROM health_checks ORDER BY name`)
	if err != nil {
		return err
	}
	channels, err := a.Repo.Query(c.UserContext(), `SELECT id,name,type FROM notification_channels WHERE is_deleted=false ORDER BY name`)
	if err != nil {
		return err
	}
	for _, r := range checks {
		conf := obj(r["scheduleConfig"])
		r["scheduleTime"] = conf["time"]
		if r["scheduleTime"] == nil {
			r["scheduleTime"] = "08:00"
		}
		r["scheduleWeekday"] = conf["weekday"]
		if r["scheduleWeekday"] == nil {
			r["scheduleWeekday"] = 1
		}
		r["scheduleDay"] = conf["day"]
		if r["scheduleDay"] == nil {
			r["scheduleDay"] = 1
		}
		if !nonempty(r["messageTemplate"]) {
			r["messageTemplate"] = defaultHealthTemplate
		}
	}
	return data(c, Row{"checks": checks, "healthChecks": checks, "channels": channels, "defaultTemplate": defaultHealthTemplate})
}
func (a *App) channelDevices(c *fiber.Ctx) error {
	if err := a.require(c, "notification"); err != nil {
		return err
	}
	i, err := id(c)
	if err != nil {
		return err
	}
	devices, err := a.Repo.Query(c.UserContext(), `SELECT id,name,description,location,metrics FROM devices WHERE is_deleted=false`)
	if err != nil {
		return err
	}
	result := []Row{}
	for _, d := range devices {
		for _, v := range arr(d["metrics"]) {
			m := obj(v)
			for _, raw := range arr(m["triggers"]) {
				t := obj(raw)
				match := false
				for _, ch := range arr(t["channels"]) {
					if num(ch) == i || text(ch) == text(i) {
						match = true
						break
					}
				}
				if match {
					result = append(result, Row{"deviceId": d["id"], "deviceName": d["name"], "description": d["description"], "location": d["location"], "metricLabel": m["label"], "metricUnit": m["unit"], "triggerOperator": t["operator"], "triggerValue": t["value"], "clearOperator": t["clear_operator"], "clearValue": t["clear_value"], "severity": t["severity"], "isEnabled": t["enable"]})
				}
			}
		}
	}
	return data(c, result)
}
