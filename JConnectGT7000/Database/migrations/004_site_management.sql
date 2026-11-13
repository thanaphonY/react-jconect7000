-- Site tree, regional scope, and channel grants. Existing PHP ignores the new columns.

CREATE TABLE IF NOT EXISTS site_nodes (
  id serial PRIMARY KEY,
  parent_id integer NULL REFERENCES site_nodes (id) ON DELETE RESTRICT,
  name varchar(200) NOT NULL,
  node_type varchar(20) NOT NULL,
  sort_order integer NOT NULL DEFAULT 0,
  latitude double precision NULL,
  longitude double precision NULL,
  dashboard_layout_id integer NULL,
  created_at timestamptz NOT NULL DEFAULT now(),
  updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT site_nodes_type_check CHECK (node_type IN ('Main', 'Region', 'Site', 'Building', 'Room'))
);

CREATE INDEX IF NOT EXISTS site_nodes_parent_id_idx ON site_nodes (parent_id);

ALTER TABLE devices ADD COLUMN IF NOT EXISTS site_node_id integer NULL;
CREATE INDEX IF NOT EXISTS devices_site_node_id_idx ON devices (site_node_id);

DO $$
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM pg_constraint WHERE conname = 'devices_site_node_id_fkey'
  ) THEN
    ALTER TABLE devices
      ADD CONSTRAINT devices_site_node_id_fkey
      FOREIGN KEY (site_node_id) REFERENCES site_nodes (id) ON DELETE SET NULL;
  END IF;
END $$;

ALTER TABLE ia_users ADD COLUMN IF NOT EXISTS access_level varchar(20) DEFAULT 'global';
UPDATE ia_users SET access_level = 'global' WHERE access_level IS NULL;

CREATE TABLE IF NOT EXISTS user_site_scopes (
  ia_users_id integer NOT NULL REFERENCES ia_users (ia_users_id) ON DELETE CASCADE,
  site_node_id integer NOT NULL REFERENCES site_nodes (id) ON DELETE CASCADE,
  PRIMARY KEY (ia_users_id, site_node_id)
);

CREATE TABLE IF NOT EXISTS notification_channel_nodes (
  channel_id integer NOT NULL REFERENCES notification_channels (id) ON DELETE CASCADE,
  site_node_id integer NOT NULL REFERENCES site_nodes (id) ON DELETE CASCADE,
  PRIMARY KEY (channel_id, site_node_id)
);

INSERT INTO site_nodes (name, node_type, sort_order)
SELECT 'Main', 'Main', 0
WHERE NOT EXISTS (SELECT 1 FROM site_nodes WHERE node_type = 'Main');

INSERT INTO schema_migrations (filename)
VALUES ('004_site_management.sql')
ON CONFLICT (filename) DO NOTHING;
