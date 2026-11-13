package main

import (
	"context"
	"database/sql"
	"encoding/json"
	"flag"
	"fmt"
	"jconnect/go-backend/internal/config"
	"jconnect/go-backend/internal/database"
	"log"
	"os"
	"strings"
	"time"
)

func query(db *sql.DB, text string) ([]map[string]any, error) {
	ctx, cancel := context.WithTimeout(context.Background(), 15*time.Second)
	defer cancel()
	rows, err := db.QueryContext(ctx, text)
	if err != nil {
		return nil, err
	}
	defer rows.Close()
	cols, err := rows.Columns()
	if err != nil {
		return nil, err
	}
	out := make([]map[string]any, 0)
	for rows.Next() {
		values := make([]any, len(cols))
		ptrs := make([]any, len(cols))
		for i := range values {
			ptrs[i] = &values[i]
		}
		if err := rows.Scan(ptrs...); err != nil {
			return nil, err
		}
		row := map[string]any{}
		for i, k := range cols {
			if b, ok := values[i].([]byte); ok {
				row[k] = string(b)
			} else {
				row[k] = values[i]
			}
		}
		out = append(out, row)
	}
	return out, rows.Err()
}
func main() {
	path := flag.String("out", "", "output schema JSON path (required)")
	flag.Parse()
	if *path == "" {
		log.Fatal("-out is required")
	}
	cfg, err := config.Load()
	if err != nil {
		log.Fatal(err)
	}
	db, err := database.Open(context.Background(), cfg)
	if err != nil {
		log.Fatal(err)
	}
	pool, err := db.DB()
	if err != nil {
		log.Fatal(err)
	}
	defer pool.Close()
	out := map[string]any{"inspected_at": time.Now().UTC().Format(time.RFC3339)}
	queries := map[string]string{
		"server":                      "SELECT current_database() AS database, current_user AS username, version(), current_setting('transaction_read_only') AS read_only",
		"extensions":                  "SELECT extname, extversion FROM pg_extension ORDER BY extname",
		"tables":                      "SELECT c.relname AS table_name,c.relkind,c.reltuples::bigint AS estimated_rows FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' AND c.relkind IN ('r','p') ORDER BY c.relname",
		"columns":                     "SELECT table_name,column_name,ordinal_position,data_type,udt_name,is_nullable,column_default,character_maximum_length,numeric_precision,numeric_scale FROM information_schema.columns WHERE table_schema='public' ORDER BY table_name,ordinal_position",
		"constraints":                 "SELECT c.relname AS table_name,k.conname,k.contype,pg_get_constraintdef(k.oid) AS definition FROM pg_constraint k JOIN pg_class c ON c.oid=k.conrelid JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='public' ORDER BY c.relname,k.conname",
		"indexes":                     "SELECT tablename,indexname,indexdef FROM pg_indexes WHERE schemaname='public' ORDER BY tablename,indexname",
		"views":                       "SELECT schemaname,viewname,definition FROM pg_views WHERE schemaname='public'",
		"materialized_views":          "SELECT schemaname,matviewname,definition FROM pg_matviews WHERE schemaname='public'",
		"functions":                   "SELECT p.proname,pg_get_function_identity_arguments(p.oid) AS arguments,pg_get_function_result(p.oid) AS result, l.lanname AS language FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace JOIN pg_language l ON l.oid=p.prolang WHERE n.nspname='public' ORDER BY p.proname",
		"triggers":                    "SELECT event_object_table,trigger_name,event_manipulation,action_statement FROM information_schema.triggers WHERE trigger_schema='public' ORDER BY event_object_table,trigger_name",
		"hypertables":                 "SELECT * FROM timescaledb_information.hypertables",
		"custom_function_definitions": "SELECT p.proname,pg_get_function_identity_arguments(p.oid) AS arguments,pg_get_functiondef(p.oid) AS definition FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace WHERE n.nspname='public' AND p.prokind IN ('f','p') AND NOT EXISTS (SELECT 1 FROM pg_depend d WHERE d.classid='pg_proc'::regclass AND d.objid=p.oid AND d.deptype='e') ORDER BY p.proname",
	}
	for name, text := range queries {
		rows, err := query(pool, text)
		if err != nil {
			log.Fatalf("inspect %s: %v", name, err)
		}
		out[name] = rows
	}
	tables := out["tables"].([]map[string]any)
	evidence := make([]map[string]any, 0, len(tables))
	for _, table := range tables {
		name := table["table_name"].(string)
		escaped := strings.ReplaceAll(name, `"`, `""`)
		rows, err := query(pool, `SELECT EXISTS(SELECT 1 FROM public."`+escaped+`" LIMIT 1) AS has_data`)
		if err != nil {
			log.Fatal(err)
		}
		evidence = append(evidence, map[string]any{"table_name": name, "has_data": rows[0]["has_data"]})
	}
	out["existing_data"] = evidence
	raw, err := json.MarshalIndent(out, "", "  ")
	if err != nil {
		log.Fatal(err)
	}
	if err := os.WriteFile(*path, raw, 0600); err != nil {
		log.Fatal(err)
	}
	fmt.Printf("Schema inspected read-only: %d public tables. Saved %s; no row values exported.\n", len(tables), *path)
}
