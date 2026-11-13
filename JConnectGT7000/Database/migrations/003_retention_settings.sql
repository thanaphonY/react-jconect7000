-- Retention defaults. Existing PHP ignores unknown ia_setting keys.
SELECT setval(
  pg_get_serial_sequence('ia_setting', 'id'),
  COALESCE((SELECT MAX(id) FROM ia_setting), 1),
  true
);

INSERT INTO ia_setting (keys, value)
SELECT v.keys, v.value
FROM (
  VALUES
    ('retention_device_data_enabled', '1'),
    ('retention_device_data_days', '90'),
    ('retention_event_logs_enabled', '1'),
    ('retention_event_logs_days', '180'),
    ('retention_alarm_logs_enabled', '1'),
    ('retention_alarm_logs_days', '365'),
    ('retention_alarm_log_dispatch_enabled', '1'),
    ('retention_alarm_log_dispatch_days', '365'),
    ('retention_health_check_logs_enabled', '1'),
    ('retention_health_check_logs_days', '30'),
    ('retention_report_export_files_enabled', '1'),
    ('retention_report_export_files_days', '30'),
    ('retention_ia_sessions_enabled', '1'),
    ('retention_ia_sessions_days', '7')
) AS v(keys, value)
WHERE NOT EXISTS (
  SELECT 1 FROM ia_setting s WHERE s.keys = v.keys
);
