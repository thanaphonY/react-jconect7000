import type { ThemeConfig } from 'antd'
import { theme } from 'antd'

/** AdminLTE / Bootstrap tokens from the PHP shell (frontend/assets/adminlte). */
export const ADMINLTE = {
  primary: '#0d6efd',
  bodyDark: '#212529',
  sidebarDark: '#343a40',
  textDark: '#dee2e6',
  mutedDark: 'rgba(255, 255, 255, 0.65)',
  borderDark: '#495057',
  bodyLight: '#f8f9fa',
  headerLight: '#ffffff',
  textLight: '#212529',
  mutedLight: 'rgba(33, 37, 41, 0.65)',
  borderLight: '#dee2e6',
  contentDark: '#212529',
  contentLight: '#f8f9fa',
} as const

export function buildAdminlteTheme(mode: 'dark' | 'light'): ThemeConfig {
  const isDark = mode === 'dark'
  return {
    algorithm: isDark ? theme.darkAlgorithm : theme.defaultAlgorithm,
    token: {
      colorPrimary: ADMINLTE.primary,
      colorInfo: ADMINLTE.primary,
      colorLink: ADMINLTE.primary,
      fontFamily: "'Sarabun', sans-serif",
      borderRadius: 6,
      colorBgBase: isDark ? ADMINLTE.bodyDark : '#ffffff',
      colorBgContainer: isDark ? ADMINLTE.bodyDark : '#ffffff',
      colorBgLayout: isDark ? ADMINLTE.contentDark : ADMINLTE.contentLight,
      colorText: isDark ? ADMINLTE.textDark : ADMINLTE.textLight,
      colorBorder: isDark ? ADMINLTE.borderDark : ADMINLTE.borderLight,
      colorBgElevated: isDark ? ADMINLTE.sidebarDark : '#ffffff',
    },
    components: {
      Layout: {
        headerBg: isDark ? ADMINLTE.bodyDark : ADMINLTE.headerLight,
        headerColor: isDark ? ADMINLTE.textDark : ADMINLTE.textLight,
        headerHeight: 57,
        siderBg: ADMINLTE.sidebarDark,
        bodyBg: isDark ? ADMINLTE.contentDark : ADMINLTE.contentLight,
        triggerBg: ADMINLTE.sidebarDark,
      },
      Menu: {
        darkItemBg: ADMINLTE.sidebarDark,
        darkSubMenuItemBg: ADMINLTE.sidebarDark,
        darkItemSelectedBg: 'rgba(13, 110, 253, 0.25)',
        darkItemHoverBg: 'rgba(255, 255, 255, 0.08)',
        darkItemColor: ADMINLTE.textDark,
        darkItemSelectedColor: '#fff',
        itemHeight: 40,
        iconSize: 16,
        fontSize: 14,
      },
      Button: {
        primaryShadow: 'none',
      },
    },
  }
}
