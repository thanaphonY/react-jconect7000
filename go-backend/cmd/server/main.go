package main

import (
	"context"
	"jconnect/go-backend/internal/config"
	"jconnect/go-backend/internal/database"
	"jconnect/go-backend/internal/httpapi"
	"log"
	"path/filepath"
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
	root, err := filepath.Abs("..")
	if err != nil {
		log.Fatal(err)
	}
	app := httpapi.New(db, root)
	log.Printf("JConnect Go API listening on 127.0.0.1:%s", cfg.AppPort)
	log.Fatal(app.Fiber.Listen("127.0.0.1:" + cfg.AppPort))
}
