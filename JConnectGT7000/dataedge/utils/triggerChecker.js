const { randomBytes } = require('crypto');
const db = require('../config/dbConfig');

// Utility to check and fix trigger issues
class TriggerChecker {
  
  // Check if triggers exist
  static async checkTriggers() {
    try {
      const result = await db.query(`
        SELECT 
          trigger_name,
          event_manipulation,
          action_timing,
          action_statement
        FROM information_schema.triggers 
        WHERE trigger_name LIKE '%device_config%'
        ORDER BY trigger_name;
      `);
      
      return {
        success: true,
        triggers: result.rows,
        count: result.rows.length
      };
    } catch (error) {
      return {
        success: false,
        error: error.message,
        triggers: [],
        count: 0
      };
    }
  }

  // Check if notification function exists
  static async checkNotificationFunction() {
    try {
      const result = await db.query(`
        SELECT 
          proname,
          prosrc
        FROM pg_proc 
        WHERE proname = 'notify_device_config_updated';
      `);
      
      return {
        success: true,
        exists: result.rows.length > 0,
        function: result.rows[0] || null
      };
    } catch (error) {
      return {
        success: false,
        error: error.message,
        exists: false,
        function: null
      };
    }
  }

  // Test trigger by inserting a test device
  static async testTrigger() {
    try {
      // Insert test device
      const insertResult = await db.query(`
        INSERT INTO devices (token, name, communication_config, metrics, is_active, is_deleted) 
        VALUES ($1, $2, $3, $4, $5, $6) RETURNING id;
      `, [randomBytes(12).toString('base64url'), 'TEST_TRIGGER_DEVICE', '{"test": "value"}', '[]', false, true]);
      
      const testDeviceId = insertResult.rows[0].id;
      
      // Update test device
      await db.query(`
        UPDATE devices 
        SET communication_config = '{"test": "updated"}'
        WHERE id = $1;
      `, [testDeviceId]);
      
      // Clean up test device
      await db.query(`
        DELETE FROM devices WHERE id = $1;
      `, [testDeviceId]);
      
      return {
        success: true,
        message: 'Trigger test completed successfully',
        testDeviceId
      };
    } catch (error) {
      return {
        success: false,
        error: error.message
      };
    }
  }

  // Fix triggers if they're missing
  static async fixTriggers() {
    try {
      // Drop existing triggers and function
      await db.query(`
        DROP TRIGGER IF EXISTS device_config_updated_trigger ON devices;
        DROP TRIGGER IF EXISTS device_config_inserted_trigger ON devices;
        DROP FUNCTION IF EXISTS notify_device_config_updated();
      `);
      
      // Recreate function
      await db.query(`
        CREATE OR REPLACE FUNCTION notify_device_config_updated()
        RETURNS TRIGGER AS $$
        BEGIN
            PERFORM pg_notify('device_config_updated', NEW.id::text);
            RETURN NEW;
        END;
        $$ LANGUAGE plpgsql;
      `);
      
      // Recreate triggers
      await db.query(`
        CREATE TRIGGER device_config_updated_trigger
            AFTER UPDATE ON devices
            FOR EACH ROW
            WHEN (
                OLD.communication_config IS DISTINCT FROM NEW.communication_config OR
                OLD.metrics IS DISTINCT FROM NEW.metrics OR
                OLD.is_active IS DISTINCT FROM NEW.is_active OR
                OLD.is_deleted IS DISTINCT FROM NEW.is_deleted
            )
            EXECUTE FUNCTION notify_device_config_updated();
      `);
      
      await db.query(`
        CREATE TRIGGER device_config_inserted_trigger
            AFTER INSERT ON devices
            FOR EACH ROW
            EXECUTE FUNCTION notify_device_config_updated();
      `);
      
      // Grant permissions
      await db.query(`
        GRANT EXECUTE ON FUNCTION notify_device_config_updated() TO PUBLIC;
        GRANT TRIGGER ON devices TO PUBLIC;
      `);
      
      return {
        success: true,
        message: 'Triggers fixed successfully'
      };
    } catch (error) {
      return {
        success: false,
        error: error.message
      };
    }
  }

  // Comprehensive check and fix
  static async checkAndFix() {
    console.log('🔍 Checking trigger setup...');
    
    // Check triggers
    const triggerCheck = await this.checkTriggers();
    console.log(`📊 Found ${triggerCheck.count} triggers`);
    
    // Check function
    const functionCheck = await this.checkNotificationFunction();
    console.log(`🔧 Notification function exists: ${functionCheck.exists}`);
    
    // If issues found, fix them
    if (triggerCheck.count < 2 || !functionCheck.exists) {
      console.log('⚠️  Issues detected, fixing triggers...');
      const fixResult = await this.fixTriggers();
      
      if (fixResult.success) {
        console.log('✅ Triggers fixed successfully');
        
        // Re-check
        const recheck = await this.checkTriggers();
        console.log(`📊 After fix: ${recheck.count} triggers`);
        
        return {
          success: true,
          message: 'Triggers checked and fixed',
          before: triggerCheck.count,
          after: recheck.count
        };
      } else {
        console.log('❌ Failed to fix triggers:', fixResult.error);
        return {
          success: false,
          error: fixResult.error
        };
      }
    } else {
      console.log('✅ Triggers are properly configured');
      return {
        success: true,
        message: 'Triggers are working correctly',
        count: triggerCheck.count
      };
    }
  }
}

module.exports = TriggerChecker;
