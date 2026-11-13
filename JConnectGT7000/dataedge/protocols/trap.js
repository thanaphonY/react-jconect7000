const snmp = require('net-snmp');
const { getAllDevices } = require('../core/dataFetcher');

function startTrapListener() {
  const options = { port: 1162 };

  const receiver = snmp.createReceiver(options, (msg, rinfo) => {
    const senderIP = rinfo.address;
    const devices = getAllDevices();
    const matchingDevices = devices.filter(d => d.communication_config?.host === senderIP);

    if (!matchingDevices.length) {
      console.warn(`[TRAP] Received from unknown sender: ${senderIP}`);
      return;
    }

    for (const vb of msg.pdu.varbinds) {
      const oid = vb.oid;
      for (const device of matchingDevices) {
        for (const metric of device.metrics) {
          if (metric.type === 'snmp_trap' && metric.trap_oid === oid) {
            console.log(`[TRAP] ${device.name} - ${metric.key}: ${metric.trap_label || oid}`);
          }
        }
      }
    }
  });

  console.log('[TRAP] SNMP Trap Receiver listening on UDP port 1162');
}

module.exports = { startTrapListener };