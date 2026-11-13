package main

import (
	"context"
	"encoding/json"
	"jconnect/go-backend/internal/config"
	"jconnect/go-backend/internal/database"
	"log"
	"os"
)

func main() {
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
	var result struct {
		Database string `json:"database"`
		ReadOnly string `json:"read_only"`
		Version  string `json:"version"`
	}
	if err := db.Raw("SELECT current_database() AS database, current_setting('transaction_read_only') AS read_only, version()").Scan(&result).Error; err != nil {
		log.Fatal(err)
	}
	if result.Database != "jconnect" || result.ReadOnly != "on" {
		log.Fatal("unexpected database identity or read-only setting")
	}
	if err := json.NewEncoder(os.Stdout).Encode(result); err != nil {
		log.Fatal(err)
	}
}
