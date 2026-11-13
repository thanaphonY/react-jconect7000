package httpapi

import (
	_ "embed"
	"encoding/json"
	"github.com/gofiber/fiber/v2"
	"strings"
)

//go:embed taxonomy.json
var taxonomyData []byte

func (a *App) taxonomy(c *fiber.Ctx) error {
	if err := a.require(c, "devicetemplate"); err != nil && !actor(c).Read("devices") {
		return err
	}
	var rows []Row
	if err := json.Unmarshal(taxonomyData, &rows); err != nil {
		return err
	}
	return data(c, rows)
}
func (a *App) templateWrite(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	if !nonempty(b["name"]) || !nonempty(b["protocol"]) {
		return invalid("Name and protocol are required")
	}
	var categories []Row
	if err = json.Unmarshal(taxonomyData, &categories); err != nil {
		return err
	}
	cat, sub := text(b["dcimCategory"]), text(b["dcimSubCategory"])
	var selected Row
	for _, entry := range categories {
		if entry["category"] == cat {
			for _, value := range arr(entry["subs"]) {
				if value == sub {
					selected = entry
					break
				}
			}
		}
	}
	if selected == nil {
		return invalid("Select a valid Device Category and Sub-Category")
	}
	var i int64
	var owner any
	if c.Params("id") != "" {
		i, err = id(c)
		if err != nil {
			return err
		}
		if _, err = a.row(c, `SELECT id FROM device_templates WHERE id=?`, i); err != nil {
			return err
		}
	}
	if err = a.writable(c, "devicetemplate", owner, i == 0); err != nil {
		return err
	}
	category, err := a.Repo.One(c.UserContext(), `SELECT id FROM device_categories WHERE name=? LIMIT 1`, cat+" / "+sub)
	if err != nil {
		return err
	}
	if category == nil {
		category, err = a.row(c, `INSERT INTO device_categories(name,description,is_active) VALUES(?,'',true) RETURNING id`, cat+" / "+sub)
		if err != nil {
			return err
		}
	}
	info := obj(b["deviceInformation"])
	info["dcim_category"] = cat
	info["dcim_sub_category"] = sub
	info["domain"] = selected["domain"]
	info["pue_role"] = selected["pue_role"]
	args := []any{strings.TrimSpace(text(b["name"])), text(b["description"]), b["protocol"], category["id"], jsonValue(info), jsonValue(obj(b["communicationConfig"])), jsonValue(arr(b["metrics"])), jsonValue(arr(b["defaultDashboardLayout"])), b["isActive"], b["image"]}
	var r Row
	if i == 0 {
		r, err = a.row(c, `INSERT INTO device_templates(name,description,protocol,category_id,device_information,communication_config,metrics,default_dashboard_layout,is_active,image) VALUES(?,?,?,?,?::jsonb,?::jsonb,?::jsonb,?::jsonb,?,?) RETURNING *`, args...)
	} else {
		r, err = a.row(c, `UPDATE device_templates SET name=?,description=?,protocol=?,category_id=?,device_information=?::jsonb,communication_config=?::jsonb,metrics=?::jsonb,default_dashboard_layout=?::jsonb,is_active=?,image=?,updated_at=now() WHERE id=? RETURNING *`, append(args, i)...)
	}
	if err != nil {
		return err
	}
	return data(c, templateDTO(r))
}
