-- Public API keys, request logs, and request counts. Existing PHP ignores these tables.

CREATE TABLE IF NOT EXISTS api_clients (
  id serial PRIMARY KEY,
  name varchar(200) NOT NULL,
  token_hash varchar(64) NOT NULL UNIQUE,
  token_prefix varchar(16) NOT NULL,
  site_node_id integer NULL REFERENCES site_nodes (id) ON DELETE RESTRICT,
  active boolean NOT NULL DEFAULT true,
  expires_at timestamptz NULL,
  rate_limit_per_minute integer NOT NULL DEFAULT 60 CHECK (rate_limit_per_minute > 0),
  created_at timestamptz NOT NULL DEFAULT now(),
  last_used_at timestamptz NULL,
  created_by integer NULL
);

CREATE INDEX IF NOT EXISTS api_clients_active_idx ON api_clients (active);

CREATE TABLE IF NOT EXISTS api_request_logs (
  id bigserial PRIMARY KEY,
  created_at timestamptz NOT NULL DEFAULT now(),
  api_client_id integer NULL REFERENCES api_clients (id) ON DELETE SET NULL,
  method varchar(10) NOT NULL,
  path varchar(80) NOT NULL,
  zone_id integer NULL,
  site_id integer NULL,
  device_id integer NULL,
  status_code integer NOT NULL,
  error_code varchar(40) NULL,
  client_ip varchar(64) NULL
);

CREATE INDEX IF NOT EXISTS api_request_logs_client_created_idx
  ON api_request_logs (api_client_id, created_at DESC);

CREATE TABLE IF NOT EXISTS api_request_counts (
  api_client_id integer NOT NULL REFERENCES api_clients (id) ON DELETE CASCADE,
  period varchar(10) NOT NULL CHECK (period IN ('hour', 'day', 'month')),
  period_start timestamptz NOT NULL,
  request_count integer NOT NULL DEFAULT 0,
  PRIMARY KEY (api_client_id, period, period_start)
);

INSERT INTO schema_migrations (filename)
VALUES ('006_api_clients.sql')
ON CONFLICT (filename) DO NOTHING;
