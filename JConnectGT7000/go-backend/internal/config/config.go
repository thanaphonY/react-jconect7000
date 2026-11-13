package config

import (
	"fmt"
	"net"
	"net/url"
	"os"
	"strconv"

	"github.com/joho/godotenv"
)

type Config struct {
	AppPort    string
	DBHost     string
	DBPort     string
	DBUser     string
	DBPassword string
	DBName     string
	DBSSLMode  string
}

func Load() (Config, error) {
	if err := godotenv.Load(); err != nil && !os.IsNotExist(err) {
		return Config{}, fmt.Errorf("load .env: %w", err)
	}
	c := Config{get("APP_PORT", "3000"), get("DB_HOST", "127.0.0.1"), get("DB_PORT", "5433"), get("DB_USER", "jconnect"), os.Getenv("DB_PASSWORD"), get("DB_NAME", "jconnect"), get("DB_SSLMODE", "disable")}
	if c.DBPassword == "" {
		return Config{}, fmt.Errorf("DB_PASSWORD environment variable is required")
	}
	for _, value := range []string{c.AppPort, c.DBPort} {
		n, err := strconv.Atoi(value)
		if err != nil || n < 1 || n > 65535 {
			return Config{}, fmt.Errorf("invalid TCP port")
		}
	}
	switch c.DBSSLMode {
	case "disable", "allow", "prefer", "require", "verify-ca", "verify-full":
	default:
		return Config{}, fmt.Errorf("invalid DB_SSLMODE")
	}
	return c, nil
}

func get(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return fallback
}

// DSN must never be logged. URL encoding handles reserved characters in passwords.
// All new connections default to read-only while the existing system is audited.
func (c Config) DSN() string {
	u := url.URL{Scheme: "postgres", Host: net.JoinHostPort(c.DBHost, c.DBPort), Path: "/" + c.DBName, User: url.UserPassword(c.DBUser, c.DBPassword)}
	q := url.Values{}
	q.Set("sslmode", c.DBSSLMode)
	q.Set("connect_timeout", "5")
	q.Set("application_name", "jconnect-go")
	readOnly := "on"
	if os.Getenv("DB_READ_ONLY") == "false" {
		readOnly = "off"
	}
	q.Set("options", "-c default_transaction_read_only="+readOnly+" -c statement_timeout=15000")
	u.RawQuery = q.Encode()
	return u.String()
}
