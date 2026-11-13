package httpapi

import (
	"fmt"
	"github.com/gofiber/fiber/v2"
)

func (a *App) sites(c *fiber.Ctx) error {
	rows, err := a.Repo.Query(c.UserContext(), `SELECT s.*,(SELECT count(*) FROM devices d WHERE d.site_node_id=s.id AND d.is_deleted=false) AS devices_count FROM site_nodes s ORDER BY s.sort_order,s.name`)
	if err != nil {
		return err
	}
	out := []Row{}
	for _, r := range rows {
		if actor(c).SiteAllowed(r["id"]) {
			out = append(out, r)
		}
	}
	return data(c, out)
}
func (a *App) site(c *fiber.Ctx) error {
	i, err := id(c)
	if err != nil {
		return err
	}
	if !actor(c).SiteAllowed(i) {
		return fiber.ErrForbidden
	}
	r, err := a.row(c, `SELECT * FROM site_nodes WHERE id=?`, i)
	if err != nil {
		return err
	}
	devices, err := a.Repo.Query(c.UserContext(), deviceSelect+` WHERE d.is_deleted=false AND d.site_node_id IN (WITH RECURSIVE subtree(id) AS (SELECT id FROM site_nodes WHERE id=? UNION SELECT s.id FROM site_nodes s JOIN subtree t ON s.parent_id=t.id) SELECT id FROM subtree) ORDER BY d.name`, i)
	if err != nil {
		return err
	}
	for _, d := range devices {
		deviceDTO(d)
	}
	children, err := a.Repo.Query(c.UserContext(), `SELECT * FROM site_nodes WHERE parent_id=? ORDER BY sort_order,name`, i)
	if err != nil {
		return err
	}
	return data(c, Row{"node": r, "devices": devices, "children": children})
}
func (a *App) saveSite(c *fiber.Ctx) error {
	if actor(c).Regional || !actor(c).Admin() {
		return fiber.ErrForbidden
	}
	b, err := body(c)
	if err != nil {
		return err
	}
	if !nonempty(b["name"]) {
		return invalid("Name is required")
	}
	ranks := map[string]int{"Main": 0, "Region": 1, "Site": 2, "Building": 3, "Room": 4}
	rank, ok := ranks[text(b["nodeType"])]
	if !ok {
		return invalid("Invalid node type")
	}
	parent := num(b["parentId"])
	var parentArg any
	if parent > 0 {
		parentArg = parent
	}
	var i int64
	if c.Params("id") != "" {
		i, err = id(c)
		if err != nil {
			return err
		}
	}
	if rank == 0 && parent > 0 {
		return invalid("Main cannot have a parent")
	}
	if rank > 0 && parent == 0 {
		return invalid("Only Main can be a root")
	}
	if parent > 0 {
		p, err := a.row(c, `SELECT node_type FROM site_nodes WHERE id=?`, parent)
		if err != nil {
			return invalid("Parent not found")
		}
		if ranks[text(p["nodeType"])] >= rank {
			return invalid("Parent must be higher in the site hierarchy")
		}
		if i > 0 {
			cycle, err := a.Repo.One(c.UserContext(), `WITH RECURSIVE descendants(id) AS (SELECT id FROM site_nodes WHERE id=? UNION SELECT s.id FROM site_nodes s JOIN descendants d ON s.parent_id=d.id) SELECT id FROM descendants WHERE id=?`, i, parent)
			if err != nil {
				return err
			}
			if cycle != nil {
				return invalid("A site cannot contain itself")
			}
		}
	}
	if !a.Write {
		return fiber.NewError(423, "Existing database is read-only")
	}
	var r Row
	if i == 0 {
		r, err = a.row(c, `INSERT INTO site_nodes(name,node_type,parent_id,sort_order,latitude,longitude,dashboard_layout_id) VALUES(?,?,?,?,?,?,?) RETURNING *`, b["name"], b["nodeType"], parentArg, num(b["sortOrder"]), b["latitude"], b["longitude"], b["dashboardLayoutId"])
	} else {
		r, err = a.row(c, `UPDATE site_nodes SET name=?,node_type=?,parent_id=?,sort_order=?,latitude=?,longitude=?,dashboard_layout_id=?,updated_at=now() WHERE id=? RETURNING *`, b["name"], b["nodeType"], parentArg, num(b["sortOrder"]), b["latitude"], b["longitude"], b["dashboardLayoutId"], i)
	}
	if err != nil {
		return err
	}
	return data(c, r)
}
func (a *App) deleteSite(c *fiber.Ctx) error {
	if !actor(c).Admin() || actor(c).Regional {
		return fiber.ErrForbidden
	}
	i, err := id(c)
	if err != nil {
		return err
	}
	used, err := a.Repo.One(c.UserContext(), `SELECT (SELECT count(*) FROM site_nodes WHERE parent_id=?)+(SELECT count(*) FROM devices WHERE site_node_id=? AND is_deleted=false) AS total`, i, i)
	if err != nil {
		return err
	}
	if num(used["total"]) > 0 {
		return fail(c, 409, "IN_USE", "Site contains children or devices")
	}
	if !a.Write {
		return fiber.NewError(423, "Existing database is read-only")
	}
	if err = a.Repo.Exec(c.UserContext(), `DELETE FROM site_nodes WHERE id=?`, i); err != nil {
		return err
	}
	return data(c, Row{"id": i})
}
func (a *App) siteScopes(c *fiber.Ctx) error {
	if actor(c).Regional {
		return fiber.ErrForbidden
	}
	return a.list(c, `SELECT u.ia_users_id AS id,u.name,u.email,u.access_level,COALESCE(jsonb_agg(s.site_node_id) FILTER(WHERE s.site_node_id IS NOT NULL),'[]'::jsonb) AS site_ids FROM ia_users u LEFT JOIN user_site_scopes s ON s.ia_users_id=u.ia_users_id WHERE u.is_deleted='0' GROUP BY u.ia_users_id ORDER BY u.name`)
}
func (a *App) apiClients(c *fiber.Ctx) error {
	if actor(c).Regional {
		return fiber.ErrForbidden
	}
	return a.list(c, `SELECT c.id,c.name,c.token_prefix,c.site_node_id,c.active,c.expires_at,c.rate_limit_per_minute,c.created_at,c.last_used_at,COALESCE(jsonb_agg(s.site_node_id) FILTER(WHERE s.site_node_id IS NOT NULL),'[]'::jsonb) AS site_ids FROM api_clients c LEFT JOIN api_client_scopes s ON s.api_client_id=c.id GROUP BY c.id ORDER BY c.id DESC`)
}

var _ = fmt.Sprint
