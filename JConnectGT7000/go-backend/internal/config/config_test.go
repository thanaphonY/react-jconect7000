package config

import (
	"net/url"
	"testing"
)

func TestDSNReservedCharactersAndReadOnly(t *testing.T) {
	c := Config{DBHost: "127.0.0.1", DBPort: "5433", DBUser: "jconnect", DBPassword: "test@:/?#% secret", DBName: "jconnect", DBSSLMode: "disable"}
	u, err := url.Parse(c.DSN())
	if err != nil {
		t.Fatal(err)
	}
	password, _ := u.User.Password()
	if password != c.DBPassword {
		t.Fatal("password was not correctly encoded")
	}
	if u.Host != "127.0.0.1:5433" || u.Path != "/jconnect" {
		t.Fatal("wrong database target")
	}
	if u.Query().Get("options") != "-c default_transaction_read_only=on -c statement_timeout=15000" {
		t.Fatal("missing read-only protection")
	}
}
