import {Realtime} from './Realtime'
import {QueryErrors} from './QueryErrors'
import { App as AntApp, ConfigProvider, Spin } from 'antd'
import enUS from 'antd/locale/en_US'
import thTH from 'antd/locale/th_TH'
import { useEffect,lazy,Suspense } from 'react'
import { useTranslation } from 'react-i18next'
import { Navigate, Outlet, RouterProvider, createBrowserRouter, useLocation } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { apiRequest } from './api/client'
import { AuthProvider, useAuth } from './auth/AuthContext'
import { AppShell } from './layout/AppShell'
import { applyFavicon, type Branding } from './lib/assets'
import { buildAdminlteTheme } from './theme/adminlteTheme'
import { ColorModeProvider, useColorMode } from './theme/ColorModeContext'
const HomeOverview=lazy(()=>import('./pages/SiteHome').then(m=>({default:m.HomeOverview})))
const RetentionPage=lazy(()=>import('./pages/SystemPages').then(m=>({default:m.RetentionPage})))
const BackupPage=lazy(()=>import('./pages/SystemPages').then(m=>({default:m.BackupPage})))
const SitesPage=lazy(()=>import('./pages/SitesPage').then(m=>({default:m.SitesPage})))
const SiteViewPage=lazy(()=>import('./pages/SitesPage').then(m=>({default:m.SiteViewPage})))
const SiteAccessPage=lazy(()=>import('./pages/SitesPage').then(m=>({default:m.SiteAccessPage})))
const ApiManagementPage=lazy(()=>import('./pages/SitesPage').then(m=>({default:m.ApiManagementPage})))
const AlarmLogsPage=lazy(()=>import('./pages/AlarmLogsPage').then(m=>({default:m.AlarmLogsPage})))
const AlarmLogsExportPage=lazy(()=>import('./pages/AlarmLogsExportPage').then(m=>({default:m.AlarmLogsExportPage})))
const AlarmStatePage=lazy(()=>import('./pages/AlarmStatePage').then(m=>({default:m.AlarmStatePage})))
const CalculationsPage=lazy(()=>import('./pages/CalculationsPage').then(m=>({default:m.CalculationsPage})))
const CategoriesPage=lazy(()=>import('./pages/CategoriesPage').then(m=>({default:m.CategoriesPage})))
const ControlPage=lazy(()=>import('./pages/ControlPage').then(m=>({default:m.ControlPage})))
const DashboardEditLayoutPage=lazy(()=>import('./features/dashboard/DashboardEditLayoutPage').then(m=>({default:m.DashboardEditLayoutPage})))
const DashboardPage=lazy(()=>import('./pages/DashboardPage').then(m=>({default:m.DashboardPage})))
const DashboardFormPage=lazy(()=>import('./pages/DashboardFormPage').then(m=>({default:m.DashboardFormPage})))
const DashboardViewPage=lazy(()=>import('./pages/DashboardViewPage').then(m=>({default:m.DashboardViewPage})))
const DeviceFormPage=lazy(()=>import('./pages/DeviceFormPage').then(m=>({default:m.DeviceFormPage})))
const DevicesPage=lazy(()=>import('./pages/DevicesPage').then(m=>({default:m.DevicesPage})))
const DeviceViewPage=lazy(()=>import('./pages/DeviceViewPage').then(m=>({default:m.DeviceViewPage})))
const EventLogsPage=lazy(()=>import('./pages/EventLogsPage').then(m=>({default:m.EventLogsPage})))
const ForgotPasswordOtpPage=lazy(()=>import('./pages/ForgotPasswordOtpPage').then(m=>({default:m.ForgotPasswordOtpPage})))
const ForgotPasswordPage=lazy(()=>import('./pages/ForgotPasswordPage').then(m=>({default:m.ForgotPasswordPage})))
const ForgotPasswordResetPage=lazy(()=>import('./pages/ForgotPasswordResetPage').then(m=>({default:m.ForgotPasswordResetPage})))
const NotificationsPage=lazy(()=>import('./pages/NotificationsPage').then(m=>({default:m.NotificationsPage})))
const NotificationHealthChecksPage=lazy(()=>import('./pages/NotificationHealthChecksPage').then(m=>({default:m.NotificationHealthChecksPage})))
const ProfilePage=lazy(()=>import('./pages/ProfilePage').then(m=>({default:m.ProfilePage})))
const ReportBuilderPage=lazy(()=>import('./pages/ReportBuilderPage').then(m=>({default:m.ReportBuilderPage})))
const ReportFilesPage=lazy(()=>import('./pages/ReportFilesPage').then(m=>({default:m.ReportFilesPage})))
const ReportRunPage=lazy(()=>import('./pages/ReportRunPage').then(m=>({default:m.ReportRunPage})))
const ReportSchedulesPage=lazy(()=>import('./pages/ReportSchedulesPage').then(m=>({default:m.ReportSchedulesPage})))
const ReportsPage=lazy(()=>import('./pages/ReportsPage').then(m=>({default:m.ReportsPage})))
const GraphPage=lazy(()=>import('./pages/GraphPage').then(m=>({default:m.GraphPage})))
const HomePage=lazy(()=>import('./pages/HomePage').then(m=>({default:m.HomePage})))
const LoginPage=lazy(()=>import('./pages/LoginPage').then(m=>({default:m.LoginPage})))
const LorawanAddApplicationPage=lazy(()=>import('./pages/LorawanAddApplicationPage').then(m=>({default:m.LorawanAddApplicationPage})))
const LorawanPage=lazy(()=>import('./pages/LorawanPage').then(m=>({default:m.LorawanPage})))
const SettingsPage=lazy(()=>import('./pages/SettingsPage').then(m=>({default:m.SettingsPage})))
const TemplatesPage=lazy(()=>import('./pages/TemplatesPage').then(m=>({default:m.TemplatesPage})))
const TemplateFormPage=lazy(()=>import('./pages/TemplateFormPage').then(m=>({default:m.TemplateFormPage})))
const UserListPage=lazy(()=>import('./pages/UserListPage').then(m=>({default:m.UserListPage})))
const WidgetsPage=lazy(()=>import('./pages/WidgetsPage').then(m=>({default:m.WidgetsPage})))
const WidgetFormPage=lazy(()=>import('./pages/WidgetFormPage').then(m=>({default:m.WidgetFormPage})))

const PUBLIC_PATHS = new Set([
  '/login',
  '/forgot_password',
  '/forgot_password/otp',
  '/forgot_password/reset',
  '/forgot_password/set_password',
  '/set_password',
])

function Root() {
  const { status, user } = useAuth()
  const location = useLocation()
  const branding = useQuery({
    queryKey: ['branding'],
    queryFn: () => apiRequest<Branding>('/branding'),
    staleTime: 60_000,
  })

  useEffect(() => {
    if (!branding.data) return
    document.title = branding.data.website || 'JConnect'
    // Auth pages set favicon themselves (login Logo.png / forgot favicon.ico) like PHP.
    if (PUBLIC_PATHS.has(location.pathname)) return
    applyFavicon(branding.data.favicon || branding.data.logo || 'favicon.png')
  }, [branding.data, location.pathname])

  if (status === 'loading') {
    return (
      <div style={{ minHeight: '100vh', display: 'grid', placeItems: 'center' }}>
        <Spin size="large" />
      </div>
    )
  }

  const onPublic = PUBLIC_PATHS.has(location.pathname)
  if (!user && !onPublic) {
    return <Navigate to="/login" replace />
  }
  if (user && onPublic) {
    return <Navigate to="/home" replace />
  }
  if (!user) {
    return <Outlet />
  }

  return (
    <AppShell>
      <Realtime>
      <QueryErrors/>
      <Outlet />
      </Realtime>
    </AppShell>
  )
}

const router = createBrowserRouter([
  {
    path: '/',
    element: <Root />,
    children: [
      { index: true, element: <Navigate to="/home" replace /> },
      { path: 'login', element: <LoginPage /> },
      { path: 'forgot_password', element: <ForgotPasswordPage /> },
      { path: 'forgot_password/otp', element: <ForgotPasswordOtpPage /> },
      { path: 'forgot_password/reset', element: <ForgotPasswordResetPage /> },
      { path: 'forgot_password/set_password', element: <ForgotPasswordResetPage /> },
      { path: 'set_password', element: <ForgotPasswordResetPage /> },
      { path: 'home', element: <HomeOverview /> },
      {path:'site',element:<SitesPage/>},
      {path:'site/view/:id',element:<SiteViewPage/>},
      {path:'site/access',element:<SiteAccessPage/>},
      {path:'site/api',element:<ApiManagementPage/>},
      { path: 'dashboard', element: <DashboardPage /> },
      { path: 'dashboard/add', element: <DashboardFormPage /> },
      { path: 'dashboard/edit/:id', element: <DashboardFormPage /> },
      { path: 'dashboard/view/:id', element: <DashboardViewPage /> },
      { path: 'dashboard/edit_layout/:id', element: <DashboardEditLayoutPage /> },
      { path: 'widgets', element: <WidgetsPage /> },
      { path: 'widgets/add', element: <WidgetFormPage /> },
      { path: 'widgets/edit/:id', element: <WidgetFormPage /> },
      { path: 'graph', element: <GraphPage /> },
      { path: 'device', element: <DevicesPage /> },
      { path: 'device/add', element: <DeviceFormPage /> },
      { path: 'device/edit/:id', element: <DeviceFormPage /> },
      { path: 'device/view/:id', element: <DeviceViewPage /> },
      { path: 'device_categories', element: <CategoriesPage /> },
      { path: 'notification_manager', element: <NotificationsPage /> },
      { path: 'notification_manager/health_checks', element: <NotificationHealthChecksPage /> },
      { path: 'profile', element: <ProfilePage /> },
      { path: 'user/userlist', element: <UserListPage /> },
      { path: 'eventlogs', element: <EventLogsPage /> },
      { path: 'alarmlogs', element: <AlarmLogsPage /> },
      { path: 'alarmlogs/export', element: <AlarmLogsExportPage /> },
      { path: 'alarmstate', element: <AlarmStatePage /> },
      { path: 'reports', element: <ReportsPage /> },
      { path: 'reports/files', element: <ReportFilesPage /> },
      { path: 'reports/schedules', element: <ReportSchedulesPage /> },
      { path: 'reports/builder', element: <ReportBuilderPage /> },
      { path: 'reports/builder/:id', element: <ReportBuilderPage /> },
      { path: 'reports/run/:id', element: <ReportRunPage /> },
      { path: 'devicetemplate', element: <TemplatesPage /> },
      { path: 'devicetemplate/new', element: <TemplateFormPage /> },
      { path: 'devicetemplate/:id', element: <TemplateFormPage /> },
      { path: 'setting', element: <SettingsPage /> },
      {path:'retention',element:<RetentionPage/>},
      {path:'config_backup',element:<BackupPage/>},
      { path: 'lorawan', element: <LorawanPage /> },
      { path: 'lorawan/add_application', element: <LorawanAddApplicationPage /> },
      { path: 'calculation', element: <CalculationsPage /> },
      { path: 'control', element: <ControlPage /> },
    ],
  },
])

function LocaleBridge() {
  const { i18n } = useTranslation()
  const { mode } = useColorMode()
  const antdLocale = i18n.language.startsWith('th') ? thTH : enUS

  return (
    <ConfigProvider locale={antdLocale} theme={buildAdminlteTheme(mode)}>
      <AntApp>
        <AuthProvider>
          <Suspense fallback={<div style={{padding:40,textAlign:"center"}}><Spin size="large"/></div>}><RouterProvider router={router} /></Suspense>
        </AuthProvider>
      </AntApp>
    </ConfigProvider>
  )
}

export default function App() {
  return (
    <ColorModeProvider>
      <LocaleBridge />
    </ColorModeProvider>
  )
}
