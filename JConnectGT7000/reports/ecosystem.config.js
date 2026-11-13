/**
 * PM2 Ecosystem สำหรับ Report Scheduler
 *
 * ใช้: pm2 start ecosystem.config.js
 * หรือ: pm2 start ecosystem.config.js --only report-scheduler
 */
module.exports = {
  apps: [{
    name: 'report-scheduler',
    script: 'report-scheduler.js',
    cwd: __dirname,
    instances: 1,
    autorestart: true,
    watch: false,
    max_restarts: 10,
    env: {
      REPORT_SCHEDULE_URL: 'http://localhost/reports/run_schedules',
      REPORT_SCHEDULE_KEY: 'report_schedule_secret_key'
    }
  }]
};
