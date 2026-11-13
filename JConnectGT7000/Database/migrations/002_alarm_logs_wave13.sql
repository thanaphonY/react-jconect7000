-- Wave 13: columns used by PHP alarmlogs acknowledge/resolve/detail (additive only).
ALTER TABLE alarm_logs
  ADD COLUMN IF NOT EXISTS updated_at timestamp with time zone NULL,
  ADD COLUMN IF NOT EXISTS note text NULL,
  ADD COLUMN IF NOT EXISTS snapshot_path text NULL,
  ADD COLUMN IF NOT EXISTS snapshot_camera_device_id integer NULL;

CREATE INDEX IF NOT EXISTS idx_alarm_logs_snapshot_path
  ON alarm_logs (snapshot_path)
  WHERE snapshot_path IS NOT NULL;
