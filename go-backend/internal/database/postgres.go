package database

import (
	"context"
	"fmt"
	"strings"
	"time"

	"gorm.io/driver/postgres"
	"gorm.io/gorm"
	"gorm.io/gorm/logger"
	"jconnect/go-backend/internal/config"
)

// Open connects only to the configured existing database; it never creates or migrates schema.
func Open(ctx context.Context, c config.Config) (*gorm.DB, error) {
	db, err := gorm.Open(postgres.Open(c.DSN()), &gorm.Config{Logger: logger.Default.LogMode(logger.Silent), DisableAutomaticPing: true})
	if err != nil {
		return nil, connectionError(c, err)
	}
	pool, err := db.DB()
	if err != nil {
		return nil, connectionError(c, err)
	}
	pool.SetMaxOpenConns(5)
	pool.SetMaxIdleConns(2)
	pool.SetConnMaxLifetime(30 * time.Minute)
	pool.SetConnMaxIdleTime(5 * time.Minute)
	pingCtx, cancel := context.WithTimeout(ctx, 5*time.Second)
	defer cancel()
	if err = pool.PingContext(pingCtx); err != nil {
		pool.Close()
		return nil, connectionError(c, err)
	}
	return db, nil
}

func connectionError(c config.Config, err error) error {
	// Drivers may include connection details in errors. Remove the password and DSN.
	message := strings.ReplaceAll(err.Error(), c.DSN(), "[connection details redacted]")
	if c.DBPassword != "" {
		message = strings.ReplaceAll(message, c.DBPassword, "[redacted]")
	}
	return fmt.Errorf("PostgreSQL connection %s:%s/%s: %s", c.DBHost, c.DBPort, c.DBName, message)
}
