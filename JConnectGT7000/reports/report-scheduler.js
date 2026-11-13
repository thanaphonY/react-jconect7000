/**
 * Report Schedule Runner
 * เรียก PHP run_schedules ทุก 1 นาที (ใช้กับ PM2)
 * รอให้รอบก่อนหน้าจบก่อน จึงไม่ยิงซ้อนแล้วส่งออกซ้ำ
 *
 * pm2 start report-scheduler.js --name report-scheduler
 */
const http = require('http');
const https = require('https');

const INTERVAL_MS = 60 * 1000;
const REQUEST_TIMEOUT_MS = 180 * 1000;
const API_URL = process.env.REPORT_SCHEDULE_URL || 'http://localhost/reports/run_schedules';
const HEALTH_URL = process.env.HEALTH_CHECK_URL || 'http://localhost/notification_manager/run_health_checks';
const SECRET_KEY = process.env.REPORT_SCHEDULE_KEY || 'report_schedule_secret_key';

function request(url, label, done) {
  const lib = url.startsWith('https') ? https : http;
  let settled = false;
  function finish() {
    if (settled) return;
    settled = true;
    done();
  }
  const req = lib.get(url, (res) => {
    let body = '';
    res.on('data', (chunk) => { body += chunk; });
    res.on('end', () => {
      if (res.statusCode !== 200) {
        console.error(`[${new Date().toISOString()}] ${label} HTTP ${res.statusCode}`);
        const preview = body.trim().substring(0, 200);
        if (preview && !body.trim().startsWith('{')) {
          console.error('Response (not JSON):', preview + (body.length > 200 ? '...' : ''));
        }
        finish();
        return;
      }
      try {
        const data = JSON.parse(body);
        console.log(`[${new Date().toISOString()}] ${label} Processed: ${data.processed || 0}, Done: ${data.done || 0}`);
        if (data.errors && data.errors.length) {
          console.error(label + ' errors:', data.errors);
        }
      } catch (e) {
        console.error(`[${new Date().toISOString()}] ${label} parse error. Status: ${res.statusCode}`);
        const preview = body.trim().substring(0, 200);
        if (preview) console.error('Body preview:', preview + (body.length > 200 ? '...' : ''));
      }
      finish();
    });
  });
  req.setTimeout(REQUEST_TIMEOUT_MS, () => {
    console.error(`[${new Date().toISOString()}] ${label} timeout after ${REQUEST_TIMEOUT_MS / 1000}s`);
    req.destroy();
  });
  req.on('error', (err) => {
    console.error(`[${new Date().toISOString()}] ${label} error:`, err.message);
    finish();
  });
}

function tick() {
  const key = encodeURIComponent(SECRET_KEY);
  request(`${API_URL}?key=${key}`, 'reports', function () {
    request(`${HEALTH_URL}?key=${key}`, 'health', function () {
      setTimeout(tick, INTERVAL_MS);
    });
  });
}

tick();
console.log('Report scheduler started. Triggering every', INTERVAL_MS / 1000, 'seconds.');
console.log('Reports:', API_URL);
console.log('Health:', HEALTH_URL);
