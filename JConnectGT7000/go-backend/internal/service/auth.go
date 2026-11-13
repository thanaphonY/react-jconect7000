package service

import (
	"context"
	"crypto/rand"
	"encoding/base64"
	"encoding/json"
	"golang.org/x/crypto/bcrypt"
	"jconnect/go-backend/internal/repository"
	"strings"
	"sync"
	"time"
)

type Row = repository.Row
type Actor struct {
	User       Row
	Permission map[string]map[string]any
	Regional   bool
	Sites      []int64
}

func (a *Actor) ID() int64   { return repository.Number(a.User["id"]) }
func (a *Actor) Admin() bool { return strings.EqualFold(repository.Text(a.User["userType"]), "admin") }
func (a *Actor) Can(module, method string) bool {
	if a.Admin() {
		return true
	}
	for k, p := range a.Permission {
		if strings.EqualFold(k, module) {
			v := p[method]
			return v == true || v == "1" || repository.Number(v) == 1
		}
	}
	return false
}
func (a *Actor) Read(module string) bool {
	return a.Can(module, "all_read") || a.Can(module, "own_read")
}
func (a *Actor) Own(module, action string, owner any) bool {
	return a.Can(module, "all_"+action) || (a.Can(module, "own_"+action) && repository.Number(owner) == a.ID())
}
func (a *Actor) SiteAllowed(v any) bool {
	if !a.Regional {
		return true
	}
	for _, id := range a.Sites {
		if repository.Number(v) == id {
			return true
		}
	}
	return false
}

type session struct {
	ID       int64
	Expires  time.Time
	Remember bool
}
type Auth struct {
	Repo     *repository.Repository
	mu       sync.Mutex
	sessions map[string]session
}

func NewAuth(r *repository.Repository) *Auth { return &Auth{Repo: r, sessions: map[string]session{}} }
func Token() string {
	var b [32]byte
	if _, err := rand.Read(b[:]); err != nil {
		panic(err)
	}
	return base64.RawURLEncoding.EncodeToString(b[:])
}
func Verify(password, hash string) bool {
	if strings.HasPrefix(hash, "$2y$") {
		hash = "$2a$" + hash[4:]
	}
	return bcrypt.CompareHashAndPassword([]byte(hash), []byte(password)) == nil
}
func (a *Auth) User(ctx context.Context, id int64) (*Actor, error) {
	user, err := a.Repo.One(ctx, `SELECT ia_users_id AS id,COALESCE(name,'') AS name,COALESCE(email,'') AS email,user_type,profile_pic,status,access_level,user_id FROM ia_users WHERE ia_users_id=? AND is_deleted='0'`, id)
	if err != nil || user == nil {
		return nil, err
	}
	actor := &Actor{User: user, Regional: user["accessLevel"] == "regional", Permission: map[string]map[string]any{}}
	if !actor.Admin() {
		p, err := a.Repo.One(ctx, `SELECT data FROM ia_permission WHERE user_type=? LIMIT 1`, user["userType"])
		if err != nil {
			return nil, err
		}
		if p != nil {
			_ = json.Unmarshal([]byte(repository.Text(p["data"])), &actor.Permission)
		}
	}
	if actor.Regional {
		rows, err := a.Repo.Query(ctx, `WITH RECURSIVE visible(id) AS (SELECT site_node_id FROM user_site_scopes WHERE ia_users_id=? UNION SELECT s.id FROM site_nodes s JOIN visible v ON s.parent_id=v.id) SELECT id FROM visible`, id)
		if err != nil {
			return nil, err
		}
		for _, row := range rows {
			actor.Sites = append(actor.Sites, repository.Number(row["id"]))
		}
	}
	return actor, nil
}
func (a *Auth) Login(ctx context.Context, email, password string, remember bool) (string, *Actor, error) {
	row, err := a.Repo.One(ctx, `SELECT ia_users_id,password FROM ia_users WHERE is_deleted='0' AND (name=? OR email=?) LIMIT 1`, email, email)
	if err != nil {
		return "", nil, err
	}
	if row == nil || !Verify(password, repository.Text(row["password"])) {
		return "", nil, nil
	}
	actor, err := a.User(ctx, repository.Number(row["iaUsersId"]))
	if err != nil || actor == nil {
		return "", nil, err
	}
	return a.Issue(actor.ID(), remember), actor, nil
}
func (a *Auth) Issue(id int64, remember bool) string {
	token := Token()
	ttl := 12 * time.Hour
	if remember {
		ttl = 30 * 24 * time.Hour
	}
	a.mu.Lock()
	defer a.mu.Unlock()
	now := time.Now()
	for k, v := range a.sessions {
		if now.After(v.Expires) {
			delete(a.sessions, k)
		}
	}
	a.sessions[token] = session{id, now.Add(ttl), remember}
	return token
}
func (a *Auth) Resolve(ctx context.Context, token string) (*Actor, error) {
	a.mu.Lock()
	s, ok := a.sessions[token]
	if ok && time.Now().After(s.Expires) {
		delete(a.sessions, token)
		ok = false
	}
	a.mu.Unlock()
	if !ok {
		return nil, nil
	}
	return a.User(ctx, s.ID)
}
func (a *Auth) Revoke(token string) { a.mu.Lock(); defer a.mu.Unlock(); delete(a.sessions, token) }
func (a *Auth) Remember(token string) bool {
	a.mu.Lock()
	defer a.mu.Unlock()
	return a.sessions[token].Remember
}

type Menu struct {
	LabelKey string `json:"labelKey"`
	Path     string `json:"path"`
}

var menuRules = []struct{ Label, Path, Module string }{
	{"home", "/home", ""}, {"dashboard", "/dashboard", "dashboard"}, {"Site Management", "/site", "site"}, {"devices", "/device", "devices"}, {"device_template", "/devicetemplate", "devicetemplate"}, {"notification", "/notification_manager", "notification"}, {"profile", "/profile", ""}, {"users", "/user/userlist", "user"}, {"auditlogs", "/eventlogs", "eventlog"}, {"alarmlog", "/alarmlogs", "alarmlogs"}, {"reports", "/reports", "reports"}, {"API Management", "/site/api", "site"}, {"System Setting", "/setting", "setting"},
}

func (a *Actor) Menu() []Menu {
	out := []Menu{}
	for _, m := range menuRules {
		if a.Regional {
			if m.Path != "/home" && m.Path != "/site" && m.Path != "/dashboard" && m.Path != "/alarmlogs" && m.Path != "/reports" {
				continue
			}
		}
		if a.Regional || m.Module == "" || m.Module == "site" || a.Read(m.Module) {
			out = append(out, Menu{m.Label, m.Path})
		}
	}
	return out
}
