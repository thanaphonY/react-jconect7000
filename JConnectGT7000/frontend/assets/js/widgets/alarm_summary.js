const ALARM_SUMMARY_SEVERITY_BUCKETS = {
  critical: new Set(['critical', 'major', 'high']),
  warning: new Set(['warning', 'medium']),
  info: new Set(['info', 'minor', 'low'])
};

function normalizeAlarmSeverity(severity) {
  return String(severity || '').trim().toLowerCase();
}

function bucketAlarmSeverity(severity) {
  const key = normalizeAlarmSeverity(severity);
  if (ALARM_SUMMARY_SEVERITY_BUCKETS.critical.has(key)) return 'critical';
  if (ALARM_SUMMARY_SEVERITY_BUCKETS.warning.has(key)) return 'warning';
  if (ALARM_SUMMARY_SEVERITY_BUCKETS.info.has(key)) return 'info';
  return 'warning';
}

function parseAlarmSummaryFilter(widgetEl) {
  const raw = widgetEl?.dataset?.deviceFilter || '[]';
  try {
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed) || !parsed.length) return null;
    return new Set(parsed.map((id) => String(id)));
  } catch (_e) {
    return null;
  }
}

function filterAlarmSummaryRows(rows, widgetEl) {
  const allowed = parseAlarmSummaryFilter(widgetEl);
  if (!allowed) return rows;
  return rows.filter((row) => allowed.has(String(row.device_id)));
}

function renderAlarmCounts(widgetEl, rows) {
  const counts = { total: rows.length, critical: 0, warning: 0, info: 0 };
  rows.forEach((row) => {
    counts[bucketAlarmSeverity(row.severity)] += 1;
  });

  widgetEl.querySelectorAll('.alarm-summary-count').forEach((badge) => {
    const severity = badge.dataset.severity;
    if (!severity || counts[severity] === undefined) return;
    badge.textContent = String(counts[severity]);
  });
}

function renderAlarmSummaryPreview(container, config) {
  container.innerHTML = `
    <div class="widget-alarm-summary border rounded p-2">
      <div class="alarm-summary-title small text-secondary mb-2">${config.label || 'Active Alarms'}</div>
      <div class="d-flex flex-column gap-2">
        ${config.show_total !== '0' ? '<div class="d-flex justify-content-between small"><span>Total</span><span class="badge bg-secondary">0</span></div>' : ''}
        <div class="d-flex justify-content-between small"><span>Critical</span><span class="badge bg-danger">0</span></div>
        <div class="d-flex justify-content-between small"><span>Warning</span><span class="badge bg-warning text-dark">0</span></div>
        <div class="d-flex justify-content-between small"><span>Info</span><span class="badge bg-info text-dark">0</span></div>
      </div>
    </div>
  `;
}

async function refreshAlarmSummaryWidget(widgetEl) {
  try {
    const res = await fetch('/alarmstate/get_json');
    const data = await res.json();
    if (data.status !== 'success' || !Array.isArray(data.data)) return;
    renderAlarmCounts(widgetEl, filterAlarmSummaryRows(data.data, widgetEl));
  } catch (err) {
    console.warn('[alarm_summary] refresh failed:', err);
  }
}

function initAlarmSummaryWidgets() {
  const widgets = document.querySelectorAll('.widget-alarm-summary');
  widgets.forEach((widgetEl) => {
    if (widgetEl.dataset.alarmSummaryInit === '1') return;
    widgetEl.dataset.alarmSummaryInit = '1';
    refreshAlarmSummaryWidget(widgetEl);
    setInterval(() => refreshAlarmSummaryWidget(widgetEl), 5000);
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initAlarmSummaryWidgets();
});
