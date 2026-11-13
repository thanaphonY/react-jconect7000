const { getConnectionStatus } = require('../protocols/modbus_tcp');

// Monitoring utility for Modbus TCP connections
class ModbusMonitor {
  
  // Get all device connection statuses
  static getAllConnectionStatuses() {
    // This would need to be integrated with device management
    // For now, return a template structure
    return {
      timestamp: new Date(),
      totalDevices: 0,
      connectedDevices: 0,
      failedDevices: 0,
      devices: []
    };
  }

  // Get connection status for specific device
  static getDeviceConnectionStatus(deviceId) {
    const status = getConnectionStatus(deviceId);
    
    return {
      deviceId,
      timestamp: new Date(),
      connection: {
        status: status.status === 1 ? 'OK' : 'FAIL',
        value: status.status,
        errorCount: status.errorCount,
        lastSuccess: status.lastSuccess,
        uptime: status.lastSuccess ? 
          Math.floor((new Date() - status.lastSuccess) / 1000) : null
      }
    };
  }

  // Get connection health summary
  static getConnectionHealth() {
    // This would need to be integrated with device management
    // For now, return a template structure
    return {
      timestamp: new Date(),
      overall: 'UNKNOWN',
      details: {
        modbus_tcp: {
          total: 0,
          connected: 0,
          failed: 0,
          errorRate: 0
        }
      }
    };
  }

  // Check if device is healthy (for alerting)
  static isDeviceHealthy(deviceId) {
    const status = getConnectionStatus(deviceId);
    
    // Device is healthy if:
    // 1. Connection status is 1 (OK)
    // 2. Error count is less than 3
    // 3. Last success was within last 5 minutes
    const isConnected = status.status === 1;
    const lowErrorCount = status.errorCount < 3;
    const recentSuccess = status.lastSuccess && 
      (new Date() - status.lastSuccess) < 5 * 60 * 1000; // 5 minutes
    
    return isConnected && lowErrorCount && recentSuccess;
  }

  // Get devices that need attention
  static getDevicesNeedingAttention() {
    // This would need to be integrated with device management
    // For now, return a template structure
    return {
      timestamp: new Date(),
      critical: [], // Devices with 5+ consecutive errors
      warning: [],  // Devices with 3-4 consecutive errors
      offline: []   // Devices with no recent success
    };
  }
}

module.exports = ModbusMonitor;
