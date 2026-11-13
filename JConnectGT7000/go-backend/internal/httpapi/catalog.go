package httpapi

import (
	"fmt"
	"github.com/gofiber/fiber/v2"
	"strings"
)

const deviceSelect = `SELECT d.*,dt.name AS template_name,dt.image AS template_image,dt.protocol AS template_protocol,dt.device_information->>'Brand' AS brand,dt.device_information->>'Model' AS model,dc.name AS category_name FROM devices d LEFT JOIN device_templates dt ON dt.id=d.device_template_id LEFT JOIN device_categories dc ON dc.id=d.category_id`

func deviceDTO(r Row) Row {
	r["customFields"] = obj(r["config"])["custom_fields"]
	if r["customFields"] == nil {
		r["customFields"] = Row{}
	}
	conf := obj(r["config"])
	r["deviceRole"] = conf["role"]
	if r["deviceRole"] == nil {
		r["deviceRole"] = "native_device"
	}
	r["gatewayDeviceId"] = conf["via_gateway"]
	r["snapshotTriggers"] = conf["snapshot_triggers"]
	if r["snapshotTriggers"] == nil {
		r["snapshotTriggers"] = []any{}
	}
	r["communicationConfig"] = obj(r["communicationConfig"])
	r["protocol"] = obj(r["communicationConfig"])["protocol"]
	if !nonempty(r["protocol"]) {
		r["protocol"] = r["templateProtocol"]
	}
	r["metrics"] = arr(r["metrics"])
	r["imageUrl"] = nil
	r["templateImageUrl"] = nil
	if nonempty(r["templateImage"]) {
		r["templateImageUrl"] = "/api/v1/uploads/device-templates/" + text(r["templateImage"])
		r["imageUrl"] = r["templateImageUrl"]
	}
	if nonempty(r["image"]) {
		r["imageUrl"] = "/api/v1/uploads/devices/" + text(r["image"])
	}
	return r
}
func scope(c *fiber.Ctx, alias string) string {
	u := actor(c)
	if !u.Regional {
		return "TRUE"
	}
	if len(u.Sites) == 0 {
		return "FALSE"
	}
	ids := []string{}
	for _, i := range u.Sites {
		ids = append(ids, fmt.Sprint(i))
	}
	return alias + ".site_node_id IN (" + strings.Join(ids, ",") + ")"
}
func (a *App) devices(c *fiber.Ctx) error {
	if err := a.require(c, "devices"); err != nil {
		return err
	}
	p, size := page(c)
	where := []string{"d.is_deleted=false", scope(c, "d")}
	args := []any{}
	if s := c.Query("search"); s != "" {
		where = append(where, "(d.name ILIKE ? OR d.serial_number ILIKE ? OR d.location ILIKE ?)")
		args = append(args, "%"+s+"%", "%"+s+"%", "%"+s+"%")
	}
	if s := c.Query("status"); s != "" && s != "all" {
		where = append(where, "d.is_active=?")
		args = append(args, s == "active" || s == "1" || s == "true")
	}
	for key, col := range map[string]string{"template": "dt.name", "location": "d.location"} {
		if s := c.Query(key); s != "" && s != "all" {
			where = append(where, col+"=?")
			args = append(args, s)
		}
	}
	sql := " WHERE " + strings.Join(where, " AND ")
	count, err := a.Repo.One(c.UserContext(), `SELECT COUNT(*) AS total FROM devices d LEFT JOIN device_templates dt ON dt.id=d.device_template_id`+sql, args...)
	if err != nil {
		return err
	}
	rows, err := a.Repo.Query(c.UserContext(), deviceSelect+sql+" ORDER BY d.name,d.id LIMIT ? OFFSET ?", append(args, size, (p-1)*size)...)
	if err != nil {
		return err
	}
	for _, r := range rows {
		deviceDTO(r)
	}
	return data(c, Row{"devices": rows, "total": count["total"], "page": p, "pageSize": size})
}
func (a *App) device(c *fiber.Ctx) error {
	if err := a.require(c, "devices"); err != nil {
		return err
	}
	i, err := id(c)
	if err != nil {
		return err
	}
	r, err := a.row(c, deviceSelect+" WHERE d.id=? AND d.is_deleted=false AND "+scope(c, "d"), i)
	if err != nil {
		return err
	}
	return data(c, deviceDTO(r))
}
func (a *App) deviceFilters(c *fiber.Ctx) error {
	if err := a.require(c, "devices"); err != nil {
		return err
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT DISTINCT d.location,dt.name AS template_name,dc.name AS category_name FROM devices d LEFT JOIN device_templates dt ON dt.id=d.device_template_id LEFT JOIN device_categories dc ON dc.id=d.category_id WHERE d.is_deleted=false AND `+scope(c, "d"))
	if err != nil {
		return err
	}
	out := Row{}
	for _, pair := range [][2]string{{"locations", "location"}, {"templates", "templateName"}, {"categories", "categoryName"}} {
		seen := map[string]bool{}
		values := []string{}
		for _, r := range rows {
			s := text(r[pair[1]])
			if s != "" && !seen[s] {
				values = append(values, s)
				seen[s] = true
			}
		}
		out[pair[0]] = values
	}
	return data(c, out)
}
func (a *App) metricSources(c *fiber.Ctx) error {
	if err := a.require(c, "devices"); err != nil {
		return err
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT d.id,d.name,d.metrics,d.config FROM devices d WHERE d.is_deleted=false AND `+scope(c, "d")+` ORDER BY d.name`)
	if err != nil {
		return err
	}
	gateways := []Row{}
	for _, r := range rows {
		r["metrics"] = arr(r["metrics"])
		if obj(r["config"])["role"] == "gateway" {
			gateways = append(gateways, Row{"id": r["id"], "name": r["name"]})
		}
		delete(r, "config")
	}
	return data(c, Row{"devices": rows, "gateways": gateways})
}
func (a *App) metricKeys(c *fiber.Ctx) error {
	i, err := id(c)
	if err != nil {
		return err
	}
	r, err := a.row(c, `SELECT d.metrics FROM devices d WHERE d.id=? AND d.is_deleted=false AND `+scope(c, "d"), i)
	if err != nil {
		return err
	}
	return data(c, arr(r["metrics"]))
}
func (a *App) latest(c *fiber.Ctx) error {
	i, err := id(c)
	if err != nil {
		return err
	}
	if _, err = a.row(c, `SELECT d.id FROM devices d WHERE d.id=? AND d.is_deleted=false AND `+scope(c, "d"), i); err != nil {
		return err
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT DISTINCT ON(key) key,value,value_text,value_bool,value_json,timestamp FROM device_data WHERE device_id=? ORDER BY key,timestamp DESC`, i)
	if err != nil {
		return err
	}
	if key := c.Params("key", c.Query("key", c.Query("metricKey"))); key != "" {
		for _, r := range rows {
			if r["key"] == key {
				return data(c, r)
			}
		}
		return fail(c, 404, "NOT_FOUND", "Metric has no recorded value")
	}
	return data(c, Row{"deviceId": i, "latest": rows})
}
func (a *App) timeseries(c *fiber.Ctx) error {
	i, err := id(c)
	if err != nil {
		return err
	}
	if _, err = a.row(c, `SELECT d.id FROM devices d WHERE d.id=? AND d.is_deleted=false AND `+scope(c, "d"), i); err != nil {
		return err
	}
	key := c.Params("key", c.Query("key", c.Query("metricKey")))
	if key == "" {
		return invalid("Metric key is required")
	}
	ranges := map[string]string{"1h": "1 hour", "6h": "6 hours", "12h": "12 hours", "24h": "24 hours", "7d": "7 days", "30d": "30 days"}
	rangeKey := c.Query("range", "24h")
	duration, ok := ranges[rangeKey]
	if !ok {
		return invalid("Invalid metric time range")
	}
	where := ` WHERE device_id=? AND key=? AND timestamp>=now()-?::interval`
	args := []any{i, key, duration}
	for _, pair := range [][2]string{{"start", "timestamp>=?::timestamptz"}, {"end", "timestamp<=?::timestamptz"}} {
		if value := c.Query(pair[0]); value != "" {
			where += " AND " + pair[1]
			args = append(args, value)
		}
	}
	limit := c.QueryInt("limit", 1000)
	if limit < 1 || limit > 5000 {
		limit = 1000
	}
	interval := c.Query("interval")
	q := `SELECT timestamp,value,value_text,value_bool,value_json FROM device_data` + where + ` ORDER BY timestamp DESC LIMIT ?`
	if interval != "" {
		intervals := map[string]string{"1m": "1 minute", "5m": "5 minutes", "10m": "10 minutes", "15m": "15 minutes", "30m": "30 minutes", "1h": "1 hour", "4h": "4 hours", "12h": "12 hours", "24h": "24 hours", "1d": "1 day"}
		bucket, ok := intervals[interval]
		if !ok {
			return invalid("Invalid aggregation interval")
		}
		q = `SELECT time_bucket(?::interval,timestamp) AS timestamp,avg(value) AS value FROM device_data` + where + ` GROUP BY 1 ORDER BY 1 DESC LIMIT ?`
		args = append([]any{bucket}, args...)
	}
	rows, err := a.Repo.Query(c.UserContext(), q, append(args, limit)...)
	if err != nil {
		return err
	}
	for l, r := 0, len(rows)-1; l < r; l, r = l+1, r-1 {
		rows[l], rows[r] = rows[r], rows[l]
	}
	values := []any{}
	timestamps := []any{}
	for _, r := range rows {
		values = append(values, r["value"])
		timestamps = append(timestamps, r["timestamp"])
	}
	return data(c, Row{"deviceId": i, "key": key, "range": rangeKey, "points": rows, "series": values, "timestamps": timestamps})
}

func (a *App) saveDevice(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	var existing Row
	var i int64
	if c.Params("id") != "" {
		i, err = id(c)
		if err != nil {
			return err
		}
		existing, err = a.row(c, `SELECT * FROM devices WHERE id=? AND is_deleted=false AND `+scope(c, "devices"), i)
		if err != nil {
			return err
		}
	}
	if !nonempty(b["name"]) {
		return invalid("Name is required")
	}
	template := num(b["deviceTemplateId"])
	if template == 0 {
		template = num(b["device_template_id"])
	}
	if template < 1 {
		return invalid("Device template is required")
	}
	meta, err := a.row(c, `SELECT * FROM device_templates WHERE id=?`, template)
	if err != nil {
		return invalid("Invalid device template")
	}
	owner := any(nil)
	if existing != nil {
		owner = existing["createdBy"]
	}
	if err = a.writable(c, "devices", owner, existing == nil); err != nil {
		return err
	}
	siteValue, hasSite := b["siteNodeId"]
	var siteID any
	if hasSite && siteValue != nil {
		n := num(siteValue)
		if n < 1 || text(siteValue) != fmt.Sprint(n) {
			return invalid("Invalid site node ID")
		}
		if !actor(c).SiteAllowed(n) {
			return fiber.ErrForbidden
		}
		if _, err := a.row(c, `SELECT id FROM site_nodes WHERE id=?`, n); err != nil {
			return err
		}
		siteID = n
	} else if hasSite && actor(c).Regional {
		return fiber.ErrForbidden
	}
	config := Row{}
	if existing != nil {
		config = obj(existing["config"])
	}
	if v, ok := b["customFields"]; ok {
		config["custom_fields"] = obj(v)
	}
	if v, ok := b["deviceRole"]; ok {
		role := text(v)
		if role != "native_device" && role != "gateway" && role != "endpoint" {
			return invalid("Invalid device role")
		}
		config["role"] = v
	}
	if v, ok := b["gatewayDeviceId"]; ok {
		config["via_gateway"] = v
	}
	if v, ok := b["snapshotTriggers"]; ok {
		config["snapshot_triggers"] = v
	}
	value := func(key string, fallback any) any {
		if v, ok := b[key]; ok {
			return v
		}
		if existing != nil {
			if v, ok := existing[key]; ok {
				return v
			}
		}
		return fallback
	}
	comm := value("communicationConfig", meta["communicationConfig"])
	metrics := value("metrics", meta["metrics"])
	active := value("isActive", true)
	args := []any{b["name"], template, value("serialNumber", ""), value("location", ""),
		value("description", ""), active, jsonValue(config), jsonValue(comm), jsonValue(metrics), value("image", nil)}
	if existing == nil {
		columns := "name,device_template_id,serial_number,location,description,is_active,config,communication_config,metrics,image,created_by,category_id,is_deleted"
		values := "?,?,?,?,?,?,?::jsonb,?::jsonb,?::jsonb,?,?,?,false"
		args = append(args, actor(c).ID(), meta["categoryId"])
		if hasSite {
			columns += ",site_node_id"
			values += ",?"
			args = append(args, siteID)
		}
		r, err := a.Repo.One(c.UserContext(), "INSERT INTO devices("+columns+") VALUES("+values+") RETURNING id", args...)
		if err != nil {
			return err
		}
		i = num(r["id"])
	} else {
		update := `UPDATE devices SET name=?,device_template_id=?,serial_number=?,location=?,description=?,is_active=?,config=?::jsonb,communication_config=?::jsonb,metrics=?::jsonb,image=?,category_id=?,updated_at=now()`
		args = append(args, meta["categoryId"])
		if hasSite {
			update += ",site_node_id=?"
			args = append(args, siteID)
		}
		if err = a.Repo.Exec(c.UserContext(), update+" WHERE id=? AND "+scope(c, "devices"), append(args, i)...); err != nil {
			return err
		}
	}

	r, err := a.row(c, deviceSelect+" WHERE d.id=?", i)
	if err != nil {
		return err
	}
	return data(c, deviceDTO(r))
}
func (a *App) deleteDevice(c *fiber.Ctx) error {
	i, err := id(c)
	if err != nil {
		return err
	}
	r, err := a.row(c, `SELECT created_by FROM devices WHERE id=? AND is_deleted=false`, i)
	if err != nil {
		return err
	}
	if err = a.writable(c, "devices", r["createdBy"], false); err != nil {
		return err
	}
	if err = a.Repo.Exec(c.UserContext(), `UPDATE devices SET is_deleted=true,updated_at=now() WHERE id=?`, i); err != nil {
		return err
	}
	return data(c, Row{"id": i})
}
func (a *App) categories(c *fiber.Ctx) error {
	if err := a.require(c, "device_categories"); err != nil && !actor(c).Read("devices") {
		return err
	}
	return a.list(c, `SELECT c.*,(SELECT count(*) FROM devices d WHERE d.category_id=c.id AND d.is_deleted=false) AS devices_count,(SELECT count(*) FROM device_templates t WHERE t.category_id=c.id) AS templates_count FROM device_categories c ORDER BY c.name`)
}
func (a *App) templates(c *fiber.Ctx) error {
	if err := a.require(c, "devicetemplate"); err != nil && !actor(c).Read("devices") {
		return err
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT t.*,c.name AS category_name FROM device_templates t LEFT JOIN device_categories c ON c.id=t.category_id ORDER BY t.name`)
	if err != nil {
		return err
	}
	for _, r := range rows {
		templateDTO(r)
	}
	return data(c, rows)
}
func templateDTO(r Row) Row {
	r["deviceInformation"] = obj(r["deviceInformation"])
	r["selectedDcimCategory"] = obj(r["deviceInformation"])["dcim_category"]
	r["selectedDcimSubCategory"] = obj(r["deviceInformation"])["dcim_sub_category"]
	r["dcimCategory"] = r["selectedDcimCategory"]
	r["dcimSubCategory"] = r["selectedDcimSubCategory"]
	r["communicationConfig"] = obj(r["communicationConfig"])
	r["metrics"] = arr(r["metrics"])
	r["imageUrl"] = nil
	if nonempty(r["image"]) {
		r["imageUrl"] = "/api/v1/uploads/device-templates/" + text(r["image"])
	}
	return r
}
func (a *App) template(c *fiber.Ctx) error {
	if err := a.require(c, "devicetemplate"); err != nil && !actor(c).Read("devices") {
		return err
	}
	i, err := id(c)
	if err != nil {
		return err
	}
	r, err := a.row(c, `SELECT t.*,c.name AS category_name FROM device_templates t LEFT JOIN device_categories c ON c.id=t.category_id WHERE t.id=?`, i)
	if err != nil {
		return err
	}
	return data(c, templateDTO(r))
}
