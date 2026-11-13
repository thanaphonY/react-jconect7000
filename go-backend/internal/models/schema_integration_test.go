package models

import (
	"context"
	"github.com/joho/godotenv"
	"gorm.io/gorm/clause"
	"jconnect/go-backend/internal/config"
	"jconnect/go-backend/internal/database"
	"os"
	"testing"
)

func TestLiveModelScans(t *testing.T) {
	if os.Getenv("JCONNECT_INTEGRATION") != "1" {
		t.Skip("set JCONNECT_INTEGRATION=1 to read the existing database")
	}
	if err := godotenv.Load("../../.env"); err != nil && !os.IsNotExist(err) {
		t.Fatal("cannot load local environment")
	}
	cfg, err := config.Load()
	if err != nil {
		t.Fatal(err)
	}
	db, err := database.Open(context.Background(), cfg)
	if err != nil {
		t.Fatal(err)
	}
	pool, err := db.DB()
	if err != nil {
		t.Fatal(err)
	}
	defer pool.Close()
	var readOnly string
	if err := db.Raw("SHOW transaction_read_only").Scan(&readOnly).Error; err != nil || readOnly != "on" {
		t.Fatal("read-only session required")
	}
	cases := []struct {
		name string
		dest any
	}{
		{"alarm_log_dispatch", &[]AlarmLogDispatch{}},
		{"alarm_logs", &[]AlarmLogs{}},
		{"alarm_state", &[]AlarmState{}},
		{"api_client_scopes", &[]ApiClientScopes{}},
		{"api_clients", &[]ApiClients{}},
		{"api_request_counts", &[]ApiRequestCounts{}},
		{"api_request_logs", &[]ApiRequestLogs{}},
		{"dashboard_layout_items", &[]DashboardLayoutItems{}},
		{"dashboard_layouts", &[]DashboardLayouts{}},
		{"device_categories", &[]DeviceCategories{}},
		{"device_data", &[]DeviceData{}},
		{"device_latest", &[]DeviceLatest{}},
		{"device_maintenance", &[]DeviceMaintenance{}},
		{"device_templates", &[]DeviceTemplates{}},
		{"devices", &[]Devices{}},
		{"event_logs", &[]EventLogs{}},
		{"health_check_logs", &[]HealthCheckLogs{}},
		{"health_checks", &[]HealthChecks{}},
		{"ia_custom_fields", &[]IaCustomFields{}},
		{"ia_custom_fields_values", &[]IaCustomFieldsValues{}},
		{"ia_email_templates", &[]IaEmailTemplates{}},
		{"ia_menu", &[]IaMenu{}},
		{"ia_permission", &[]IaPermission{}},
		{"ia_plugins", &[]IaPlugins{}},
		{"ia_sessions", &[]IaSessions{}},
		{"ia_setting", &[]IaSetting{}},
		{"ia_users", &[]IaUsers{}},
		{"lorawan_applications", &[]LorawanApplications{}},
		{"notification_channel_nodes", &[]NotificationChannelNodes{}},
		{"notification_channels", &[]NotificationChannels{}},
		{"report_categories", &[]ReportCategories{}},
		{"report_executions", &[]ReportExecutions{}},
		{"report_export_files", &[]ReportExportFiles{}},
		{"report_favorites", &[]ReportFavorites{}},
		{"report_schedules", &[]ReportSchedules{}},
		{"report_templates", &[]ReportTemplates{}},
		{"schema_migrations", &[]SchemaMigrations{}},
		{"site_nodes", &[]SiteNodes{}},
		{"user_dashboard_homes", &[]UserDashboardHomes{}},
		{"user_site_scopes", &[]UserSiteScopes{}},
		{"widgets", &[]Widgets{}},
	}
	for _, tc := range cases {
		t.Run(tc.name, func(t *testing.T) {
			if err := db.Preload(clause.Associations).Limit(1).Find(tc.dest).Error; err != nil {
				t.Fatal(err)
			}
		})
	}
}
