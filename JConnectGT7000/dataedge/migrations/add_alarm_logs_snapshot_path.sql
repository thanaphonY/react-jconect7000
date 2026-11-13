-- Link CCTV snapshots to alarm log rows (run once on PostgreSQL)
ALTER TABLE alarm_logs
  ADD COLUMN IF NOT EXISTS snapshot_path TEXT,
  ADD COLUMN IF NOT EXISTS snapshot_camera_device_id INTEGER;

CREATE INDEX IF NOT EXISTS idx_alarm_logs_snapshot_path
  ON alarm_logs (snapshot_path)
  WHERE snapshot_path IS NOT NULL;
