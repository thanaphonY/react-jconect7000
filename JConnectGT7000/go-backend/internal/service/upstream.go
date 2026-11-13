package service

import (
	"bytes"
	"context"
	"encoding/json"
	"fmt"
	"io"
	"net/http"
	"net/url"
	"os"
	"strings"
	"time"
)

func Call(ctx context.Context, base, method, path string, body any, authorization string) (int, any, error) {
	u, err := url.Parse(strings.TrimRight(base, "/") + "/" + strings.TrimLeft(path, "/"))
	if err != nil || u.Host == "" || (u.Scheme != "http" && u.Scheme != "https") {
		return 0, nil, fmt.Errorf("invalid upstream URL")
	}
	var input io.Reader
	if body != nil {
		b, err := json.Marshal(body)
		if err != nil {
			return 0, nil, err
		}
		input = bytes.NewReader(b)
	}
	req, err := http.NewRequestWithContext(ctx, method, u.String(), input)
	if err != nil {
		return 0, nil, err
	}
	req.Header.Set("Accept", "application/json")
	if body != nil {
		req.Header.Set("Content-Type", "application/json")
	}
	if authorization != "" {
		req.Header.Set("Authorization", authorization)
	}
	client := &http.Client{Timeout: 8 * time.Second, CheckRedirect: func(_ *http.Request, _ []*http.Request) error { return http.ErrUseLastResponse }}
	response, err := client.Do(req)
	if err != nil {
		return 0, nil, fmt.Errorf("upstream unreachable")
	}
	defer response.Body.Close()
	b, err := io.ReadAll(io.LimitReader(response.Body, 8*1024*1024))
	if err != nil {
		return 0, nil, err
	}
	var payload any
	if err = json.Unmarshal(b, &payload); err != nil {
		return response.StatusCode, nil, fmt.Errorf("upstream did not return JSON")
	}
	return response.StatusCode, payload, nil
}
func EnvironmentURL(key, fallback string) string {
	if value := os.Getenv(key); value != "" {
		return value
	}
	return fallback
}
