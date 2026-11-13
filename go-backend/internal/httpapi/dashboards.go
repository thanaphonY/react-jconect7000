package httpapi

import (
	"fmt"
	"github.com/gofiber/fiber/v2"
	"jconnect/go-backend/internal/repository"
	"strings"
)

func layoutScope(c *fiber.Ctx) string {
	u := actor(c)
	if u.Regional {
		if len(u.Sites) == 0 {
			return "FALSE"
		}
		ids := []string{}
		for _, i := range u.Sites {
			ids = append(ids, fmt.Sprint(i))
		}
		return "dl.id IN (SELECT dashboard_layout_id FROM site_nodes WHERE id IN (" + strings.Join(ids, ",") + "))"
	}
	if u.Can("dashboard", "all_read") {
		return "TRUE"
	}
	if u.Can("dashboard", "own_read") {
		return fmt.Sprintf("dl.created_by=%d", u.ID())
	}
	return "FALSE"
}

const layoutSQL = `SELECT dl.id,dl.name,dl.description,dl.background_image,dl.sort_order,dl.created_by,COALESCE(u.name,'') AS creator FROM dashboard_layouts dl LEFT JOIN ia_users u ON u.ia_users_id=dl.created_by`

func (a *App) layoutList(c *fiber.Ctx) ([]Row, any, error) {
	rows, err := a.Repo.Query(c.UserContext(), layoutSQL+" WHERE "+layoutScope(c)+" ORDER BY dl.sort_order NULLS LAST,dl.name,dl.id")
	if err != nil {
		return nil, nil, err
	}
	home, err := a.Repo.One(c.UserContext(), `SELECT layout_id FROM user_dashboard_homes WHERE user_id=?`, actor(c).ID())
	if err != nil {
		return nil, nil, err
	}
	var homeID any
	if home != nil {
		homeID = home["layoutId"]
	}
	for _, r := range rows {
		r["isHome"] = num(r["id"]) == num(homeID)
	}
	return rows, homeID, nil
}
func (a *App) dashboards(c *fiber.Ctx) error {
	if err := a.require(c, "dashboard"); err != nil {
		return err
	}
	rows, home, err := a.layoutList(c)
	if err != nil {
		return err
	}
	return data(c, Row{"layouts": rows, "homeLayoutId": home})
}
func (a *App) layoutView(c *fiber.Ctx) error {
	if err := a.require(c, "dashboard"); err != nil {
		return err
	}
	layouts, home, err := a.layoutList(c)
	if err != nil {
		return err
	}
	i := num(home)
	if c.Params("id") != "" {
		i, err = id(c)
		if err != nil {
			return err
		}
	}
	if i == 0 && len(layouts) > 0 {
		i = num(layouts[0]["id"])
	}
	var layout Row
	for _, r := range layouts {
		if num(r["id"]) == i {
			layout = r
			break
		}
	}
	if layout == nil {
		return fail(c, 404, "NOT_FOUND", "No accessible dashboard layout")
	}
	rows, err := a.Repo.Query(c.UserContext(), `SELECT i.id,i.widget_id,i.x,i.y,i.w,i.h,i.config,w.name,w.widget_type FROM dashboard_layout_items i LEFT JOIN widgets w ON w.id=i.widget_id WHERE i.layout_id=? ORDER BY i.y,i.x,i.id`, i)
	if err != nil {
		return err
	}
	for _, r := range rows {
		config := obj(r["config"])
		r["config"] = config
		r["widget_id"] = r["widgetId"]
		r["type"] = r["widgetType"]
		if !nonempty(r["widgetType"]) {
			r["widgetType"] = config["widget_type"]
			r["type"] = r["widgetType"]
		}
		r["label"] = r["name"]
		for _, k := range []string{"label", "zone_name"} {
			if nonempty(config[k]) {
				r["label"] = config[k]
				break
			}
		}
	}
	return data(c, Row{"layout": layout, "layouts": layouts, "items": rows, "homeLayoutId": home})
}
func (a *App) saveLayout(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	if !nonempty(b["name"]) {
		return invalid("Name is required")
	}
	var i int64
	var existing Row
	if c.Params("id") != "" {
		i, err = id(c)
		if err != nil {
			return err
		}
		existing, err = a.row(c, `SELECT created_by FROM dashboard_layouts WHERE id=?`, i)
		if err != nil {
			return err
		}
	}
	var owner any
	if existing != nil {
		owner = existing["createdBy"]
	}
	if err = a.writable(c, "dashboard", owner, existing == nil); err != nil {
		return err
	}
	if existing == nil {
		r, err := a.Repo.One(c.UserContext(), `INSERT INTO dashboard_layouts(name,description,layout,created_by,sort_order) VALUES(?,?,'[]'::jsonb,?,?) RETURNING id`, b["name"], text(b["description"]), actor(c).ID(), b["sortOrder"])
		if err != nil {
			return err
		}
		i = num(r["id"])
	} else if err = a.Repo.Exec(c.UserContext(), `UPDATE dashboard_layouts SET name=?,description=?,sort_order=?,updated_at=now() WHERE id=?`, b["name"], text(b["description"]), b["sortOrder"], i); err != nil {
		return err
	}
	r, err := a.row(c, layoutSQL+" WHERE dl.id=?", i)
	if err != nil {
		return err
	}
	return data(c, r)
}
func (a *App) saveLayoutItems(c *fiber.Ctx) error {
	i, err := id(c)
	if err != nil {
		return err
	}
	existing, err := a.row(c, `SELECT created_by FROM dashboard_layouts WHERE id=?`, i)
	if err != nil {
		return err
	}
	b, err := body(c)
	if err != nil {
		return err
	}
	items, ok := b["items"].([]any)
	if !ok {
		return invalid("Items array is required")
	}
	for _, v := range items {
		item := obj(v)
		if num(item["x"]) < 0 || num(item["y"]) < 0 || num(item["w"]) < 1 || num(item["h"]) < 1 || num(item["x"])+num(item["w"]) > 16 {
			return invalid("Invalid dashboard grid geometry")
		}
	}
	if err = a.writable(c, "dashboard", existing["createdBy"], false); err != nil {
		return err
	}
	err = a.Repo.Transaction(c.UserContext(), func(r *repository.Repository) error {
		if err := r.Exec(c.UserContext(), `DELETE FROM dashboard_layout_items WHERE layout_id=?`, i); err != nil {
			return err
		}
		for _, v := range items {
			item := obj(v)
			wid := item["widget_id"]
			if wid == nil {
				wid = item["widgetId"]
			}
			if err := r.Exec(c.UserContext(), `INSERT INTO dashboard_layout_items(layout_id,widget_id,x,y,w,h,config) VALUES(?,?,?,?,?,?,?::jsonb)`, i, wid, item["x"], item["y"], item["w"], item["h"], jsonValue(obj(item["config"]))); err != nil {
				return err
			}
		}
		return nil
	})
	if err != nil {
		return err
	}
	return a.layoutView(c)
}
func (a *App) setHome(c *fiber.Ctx) error {
	i, err := id(c)
	if err != nil {
		return err
	}
	if _, err = a.row(c, layoutSQL+" WHERE dl.id=? AND "+layoutScope(c), i); err != nil {
		return err
	}
	if !a.Write {
		return fiber.NewError(423, "Existing database is read-only")
	}
	if err = a.Repo.Exec(c.UserContext(), `INSERT INTO user_dashboard_homes(user_id,layout_id,updated_at) VALUES(?,?,now()) ON CONFLICT(user_id) DO UPDATE SET layout_id=EXCLUDED.layout_id,updated_at=now()`, actor(c).ID(), i); err != nil {
		return err
	}
	return data(c, Row{"homeLayoutId": i})
}
func (a *App) deleteLayout(c *fiber.Ctx) error {
	i, err := id(c)
	if err != nil {
		return err
	}
	r, err := a.row(c, `SELECT created_by FROM dashboard_layouts WHERE id=?`, i)
	if err != nil {
		return err
	}
	if err = a.writable(c, "dashboard", r["createdBy"], false); err != nil {
		return err
	}
	err = a.Repo.Transaction(c.UserContext(), func(r *repository.Repository) error {
		for _, q := range []string{`DELETE FROM dashboard_layout_items WHERE layout_id=?`, `DELETE FROM user_dashboard_homes WHERE layout_id=?`, `DELETE FROM dashboard_layouts WHERE id=?`} {
			if err := r.Exec(c.UserContext(), q, i); err != nil {
				return err
			}
		}
		return nil
	})
	if err != nil {
		return err
	}
	return data(c, Row{"id": i})
}
func (a *App) sortLayouts(c *fiber.Ctx) error {
	b, err := body(c)
	if err != nil {
		return err
	}
	order, ok := b["order"].([]any)
	if !ok || len(order) == 0 {
		return invalid("Order is required")
	}
	for _, i := range order {
		r, err := a.row(c, `SELECT created_by FROM dashboard_layouts WHERE id=?`, num(i))
		if err != nil {
			return err
		}
		if err = a.writable(c, "dashboard", r["createdBy"], false); err != nil {
			return err
		}
	}
	err = a.Repo.Transaction(c.UserContext(), func(r *repository.Repository) error {
		for n, i := range order {
			if err := r.Exec(c.UserContext(), `UPDATE dashboard_layouts SET sort_order=?,updated_at=now() WHERE id=?`, (n+1)*10, num(i)); err != nil {
				return err
			}
		}
		return nil
	})
	if err != nil {
		return err
	}
	return a.dashboards(c)
}
