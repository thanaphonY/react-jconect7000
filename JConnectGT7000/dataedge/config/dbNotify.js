// Dedicated PG connection for LISTEN (must not use pool.query for LISTEN).
const pool = require('./dbConfig');

function listenForDeviceUpdates(callback) {
  pool.connect((err, client, release) => {
    if (err) {
      console.error('[NOTIFY] Could not connect for LISTEN:', err.message);
      return;
    }

    client.on('error', (e) => {
      console.error('[NOTIFY] LISTEN client error:', e.message);
    });

    client.on('notification', (msg) => {
      if (msg.channel !== 'device_config_updated') return;
      const deviceId = parseInt(msg.payload, 10);
      console.log(`[NOTIFY] device_config_updated id=${msg.payload}`);
      if (!Number.isNaN(deviceId)) {
        Promise.resolve(callback(deviceId)).catch((e) => {
          console.error(`[NOTIFY] reload device ${deviceId} failed:`, e.message);
        });
      }
    });

    client.query('LISTEN device_config_updated', (qerr) => {
      if (qerr) {
        console.error('[NOTIFY] LISTEN failed:', qerr.message);
        client.removeAllListeners();
        release();
        return;
      }
      console.log('[NOTIFY] Listening on channel "device_config_updated"');
    });
  });
}

module.exports = { listenForDeviceUpdates };
