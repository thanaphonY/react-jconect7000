package main

import (
	"context"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"

	"gorm.io/gorm"
	"jconnect/go-backend/internal/config"
	"jconnect/go-backend/internal/database"
)

func main() {
	id := flag.Int("device", 0, "PEMON simulator device ID")
	flag.Parse()
	if *id <= 0 || os.Getenv("DB_READ_ONLY") != "false" {
		log.Fatal("set DB_READ_ONLY=false and pass -device")
	}
	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()
	cfg, err := config.Load()
	if err != nil {
		log.Fatal(err)
	}
	db, err := database.Open(ctx, cfg)
	if err != nil {
		log.Fatal(err)
	}
	pool, err := db.DB()
	if err != nil {
		log.Fatal(err)
	}
	defer pool.Close()
	client := &http.Client{Timeout: 5 * time.Second, CheckRedirect: func(*http.Request, []*http.Request) error { return http.ErrUseLastResponse }}
	for ctx.Err() == nil {
		delay, err := poll(ctx, db, client, *id)
		if err != nil {
			log.Printf("PEMON device %d: %v", *id, err)
		}
		timer := time.NewTimer(delay)
		select {
		case <-ctx.Done():
			timer.Stop()
		case <-timer.C:
		}
	}
}

const simulatorDevice = `d.id=? AND d.is_active AND NOT d.is_deleted AND lower(t.protocol)='pemon'
 AND d.communication_config->>'host'='127.0.0.1' AND d.communication_config->>'http_port'='8080'
`

func poll(ctx context.Context, db *gorm.DB, client *http.Client, id int) (time.Duration, error) {
	var settings struct{ PullRate string }
	db = db.WithContext(ctx)
	result := db.Raw(`SELECT d.communication_config->>'pull_rate' AS pull_rate FROM devices d
 JOIN device_templates t ON t.id=d.device_template_id WHERE `+simulatorDevice, id).Scan(&settings)
	delay := 30 * time.Second
	if result.Error != nil {
		return delay, result.Error
	}
	if result.RowsAffected == 0 {
		return delay, fmt.Errorf("active HTTP simulator configuration not found")
	}
	var seconds int
	if _, err := fmt.Sscan(settings.PullRate, &seconds); err == nil && seconds > 0 && seconds <= 86400 {
		delay = time.Duration(max(10, seconds)) * time.Second
	}
	req, err := http.NewRequestWithContext(ctx, http.MethodGet, "http://127.0.0.1:8080/View1.js", nil)
	if err != nil {
		return delay, err
	}
	response, err := client.Do(req)
	if err != nil {
		return delay, err
	}
	defer response.Body.Close()
	if response.StatusCode != http.StatusOK {
		return delay, fmt.Errorf("View1.js returned %s", response.Status)
	}
	var raw struct {
		DCSTR, ACSTR int
		ACs          []int
		VDC_, IDC_   []*float64
		VAC, IAC     []json.RawMessage
	}
	if err := json.NewDecoder(io.LimitReader(response.Body, 1<<20)).Decode(&raw); err != nil {
		return delay, err
	}
	points := make(map[string]float64)
	add := func(key string, values []*float64, index int, divisor float64) {
		if index < len(values) && values[index] != nil {
			points[key] = *values[index] / divisor
		}
	}
	for n := 1; n <= raw.DCSTR && n < max(len(raw.VDC_), len(raw.IDC_)); n++ {
		add(fmt.Sprintf("dc.string%d.voltage_v", n), raw.VDC_, n, 100)
		add(fmt.Sprintf("dc.string%d.current_a", n), raw.IDC_, n, 10)
	}
	for n := 1; n <= raw.ACSTR && n < len(raw.ACs); n++ {
		var voltage, current []*float64
		if n < len(raw.VAC) {
			if err := json.Unmarshal(raw.VAC[n], &voltage); err != nil {
				return delay, fmt.Errorf("decode VAC system %d: %w", n, err)
			}
		}
		if n < len(raw.IAC) {
			if err := json.Unmarshal(raw.IAC[n], &current); err != nil {
				return delay, fmt.Errorf("decode IAC system %d: %w", n, err)
			}
		}
		for m := 1; m <= raw.ACs[n] && m < max(len(voltage), len(current)); m++ {
			add(fmt.Sprintf("ac.sys%d.line%d.voltage_v", n, m), voltage, m, 10)
			add(fmt.Sprintf("ac.sys%d.line%d.current_a", n, m), current, m, 10)
		}
	}
	if len(points) == 0 {
		return delay, fmt.Errorf("View1.js returned no AC/DC readings")
	}
	payload, err := json.Marshal(points)
	if err != nil {
		return delay, err
	}
	result = db.Exec(`WITH samples AS (
  INSERT INTO device_data(device_id,key,value,timestamp)
  SELECT d.id,p.key,p.value::double precision,now()
  FROM devices d JOIN device_templates t ON t.id=d.device_template_id
  CROSS JOIN jsonb_each_text(?::jsonb) p WHERE `+simulatorDevice+`
  RETURNING device_id,key,value,timestamp
 )
 INSERT INTO device_latest(device_id,key,value,timestamp)
 SELECT device_id,key,value,timestamp FROM samples
 ON CONFLICT(device_id,key) DO UPDATE SET value=excluded.value,
 value_text=NULL,value_bool=NULL,value_json=NULL,timestamp=excluded.timestamp
 WHERE device_latest.timestamp <= excluded.timestamp`, string(payload), id)
	if result.Error != nil {
		return delay, result.Error
	}
	if result.RowsAffected > 0 {
		log.Printf("PEMON device %d: stored %d readings", id, result.RowsAffected)
	}
	return delay, nil
}
