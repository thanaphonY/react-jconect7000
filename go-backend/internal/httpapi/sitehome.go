package httpapi

import (
	"github.com/gofiber/fiber/v2"
	"os"
	"path/filepath"
	"time"
)

func (a *App) siteHome(c *fiber.Ctx) error {
	nodes, err := a.Repo.Query(c.UserContext(), `SELECT * FROM site_nodes ORDER BY sort_order,name`)
	if err != nil {
		return err
	}
	deviceRows, err := a.Repo.Query(c.UserContext(), `SELECT site_node_id,count(*) AS total FROM devices WHERE is_deleted=false AND site_node_id IS NOT NULL GROUP BY site_node_id`)
	if err != nil {
		return err
	}
	alarmRows, err := a.Repo.Query(c.UserContext(), `SELECT d.site_node_id,lower(a.severity) AS severity,count(*) AS total FROM alarm_state a JOIN devices d ON d.id=a.device_id WHERE a.active=true AND d.site_node_id IS NOT NULL AND d.is_deleted=false GROUP BY d.site_node_id,lower(a.severity)`)
	if err != nil {
		return err
	}
	seenRows, err := a.Repo.Query(c.UserContext(), `SELECT d.site_node_id,extract(epoch FROM max(dd.timestamp))::bigint AS seen FROM devices d JOIN device_data dd ON dd.device_id=d.id WHERE d.is_deleted=false AND d.site_node_id IS NOT NULL GROUP BY d.site_node_id`)
	if err != nil {
		return err
	}
	byID := map[int64]Row{}
	for _, r := range nodes {
		r["devicesCount"] = int64(0)
		r["alarms"] = int64(0)
		r["seen"] = int64(0)
		r["severity"] = ""
		byID[num(r["id"])] = r
	}
	walk := func(id int64, fn func(Row)) {
		visited := map[int64]bool{}
		for n := 0; n < 30 && id > 0; n++ {
			r := byID[id]
			if r == nil || visited[id] {
				break
			}
			visited[id] = true
			fn(r)
			id = num(r["parentId"])
		}
	}
	for _, r := range deviceRows {
		walk(num(r["siteNodeId"]), func(n Row) { n["devicesCount"] = num(n["devicesCount"]) + num(r["total"]) })
	}
	rank := map[string]int{"critical": 3, "crit": 3, "warning": 2, "warn": 2, "info": 1}
	for _, r := range alarmRows {
		walk(num(r["siteNodeId"]), func(n Row) {
			n["alarms"] = num(n["alarms"]) + num(r["total"])
			if rank[text(r["severity"])] > rank[text(n["severity"])] {
				n["severity"] = r["severity"]
			}
		})
	}
	for _, r := range seenRows {
		walk(num(r["siteNodeId"]), func(n Row) {
			if num(r["seen"]) > num(n["seen"]) {
				n["seen"] = r["seen"]
			}
		})
	}
	visible := []Row{}
	summary := Row{"total": int64(0), "online": int64(0), "offline": int64(0), "alarm": int64(0), "onlineMinutes": 15}
	for _, n := range nodes {
		if !actor(c).SiteAllowed(n["id"]) {
			continue
		}
		n["online"] = num(n["seen"]) >= time.Now().Add(-15*time.Minute).Unix()
		visible = append(visible, n)
		if n["nodeType"] == "Site" {
			summary["total"] = num(summary["total"]) + 1
			k := "offline"
			if n["online"] == true {
				k = "online"
			}
			summary[k] = num(summary[k]) + 1
			if num(n["alarms"]) > 0 {
				summary["alarm"] = num(summary["alarm"]) + 1
			}
		}
	}
	tiles := ""
	if _, err := os.Stat(filepath.Join(a.Root, "frontend/assets/maps/tiles/.source")); err == nil {
		tiles = "/assets/maps/tiles/"
	}
	return data(c, Row{"nodes": visible, "summary": summary, "localTiles": tiles, "bounds": []float64{5.55, 20.55, 97.25, 105.75}})
}
