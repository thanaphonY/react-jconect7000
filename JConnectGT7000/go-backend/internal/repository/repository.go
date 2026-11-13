package repository

import (
	"context"
	"encoding/json"
	"fmt"
	"gorm.io/gorm"
	"strconv"
	"strings"
	"unicode"
)

type Row = map[string]any
type Repository struct{ DB *gorm.DB }

func New(db *gorm.DB) *Repository { return &Repository{DB: db} }
func Camel(s string) string {
	parts := strings.Split(s, "_")
	for i := 1; i < len(parts); i++ {
		if parts[i] != "" {
			rr := []rune(parts[i])
			rr[0] = unicode.ToUpper(rr[0])
			parts[i] = string(rr)
		}
	}
	return strings.Join(parts, "")
}
func (r *Repository) Query(ctx context.Context, query string, args ...any) ([]Row, error) {
	return r.query(ctx, true, query, args...)
}
func (r *Repository) QueryRaw(ctx context.Context, query string, args ...any) ([]Row, error) {
	return r.query(ctx, false, query, args...)
}
func (r *Repository) query(ctx context.Context, camel bool, query string, args ...any) ([]Row, error) {
	rows, err := r.DB.WithContext(ctx).Raw(query, args...).Rows()
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	cols, err := rows.Columns()
	if err != nil {
		return nil, err
	}
	types, err := rows.ColumnTypes()
	if err != nil {
		return nil, err
	}
	result := make([]Row, 0)
	for rows.Next() {
		values := make([]any, len(cols))
		ptrs := make([]any, len(cols))
		for i := range values {
			ptrs[i] = &values[i]
		}
		if err := rows.Scan(ptrs...); err != nil {
			return nil, err
		}
		row := Row{}
		for i, k := range cols {
			v := values[i]
			kind := strings.ToUpper(types[i].DatabaseTypeName())
			if b, ok := v.([]byte); ok {
				v = string(b)
			}
			if kind == "JSONB" || kind == "JSON" {
				if s, ok := v.(string); ok {
					var decoded any
					if json.Unmarshal([]byte(s), &decoded) == nil {
						v = decoded
					}
				}
			}
			name := k
			if camel {
				name = Camel(k)
			}
			row[name] = v
		}
		result = append(result, row)
	}
	return result, rows.Err()
}
func (r *Repository) One(ctx context.Context, q string, args ...any) (Row, error) {
	rows, err := r.Query(ctx, q, args...)
	if err != nil {
		return nil, err
	}
	if len(rows) == 0 {
		return nil, nil
	}
	return rows[0], nil
}
func (r *Repository) Exec(ctx context.Context, q string, args ...any) error {
	return r.DB.WithContext(ctx).Exec(q, args...).Error
}
func (r *Repository) Transaction(ctx context.Context, f func(*Repository) error) error {
	return r.DB.WithContext(ctx).Transaction(func(tx *gorm.DB) error { return f(New(tx)) })
}
func Number(v any) int64 {
	switch n := v.(type) {
	case int64:
		return n
	case int32:
		return int64(n)
	case int:
		return int64(n)
	case float64:
		return int64(n)
	case string:
		i, _ := strconv.ParseInt(n, 10, 64)
		return i
	case json.Number:
		i, _ := n.Int64()
		return i
	}
	return 0
}
func Text(v any) string {
	if v == nil {
		return ""
	}
	return fmt.Sprint(v)
}
func Object(v any) Row {
	for i := 0; i < 3; i++ {
		if s, ok := v.(string); ok {
			var d any
			if json.Unmarshal([]byte(s), &d) != nil {
				return Row{}
			}
			v = d
			continue
		}
		if m, ok := v.(map[string]any); ok {
			return m
		}
		break
	}
	return Row{}
}
func Array(v any) []any {
	if s, ok := v.(string); ok {
		var d any
		if json.Unmarshal([]byte(s), &d) == nil {
			v = d
		}
	}
	if a, ok := v.([]any); ok {
		return a
	}
	return []any{}
}
func JSON(v any) string { b, _ := json.Marshal(v); return string(b) }
