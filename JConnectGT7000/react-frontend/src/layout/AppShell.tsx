import { Button, Dropdown, Layout, Menu, theme as antdTheme } from 'antd'
import type { CSSProperties, ReactNode } from 'react'
import { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useLocation, useNavigate } from 'react-router'
import { useAuth } from '../auth/AuthContext'
import { setLanguage } from '../i18n'
import { assetImageUrl } from '../lib/assets'
import { ADMINLTE } from '../theme/adminlteTheme'
import { useColorMode } from '../theme/ColorModeContext'
import { useBranding } from './AuthBrandLayout'

const { Header, Sider, Content } = Layout

/** Menu icon classes from frontend/application/views/include/header.php */
const MENU_ICONS: Record<string, string> = {
  '/home': 'bi-house',
  '/site':'bi-diagram-3',
  '/site/api':'bi-braces',
  '/dashboard': 'bi-speedometer',
  '/device': 'bi-cpu',
  '/notification_manager': 'bi-bell',
  '/profile': 'bi-person',
  '/user/userlist': 'bi-people',
  '/eventlogs': 'bi-clock-history',
  '/alarmlogs': 'bi-clock-history',
  '/alarmstate': 'bi-exclamation-circle',
  '/reports': 'bi-file-earmark-bar-graph',
  '/devicetemplate': 'bi-gear',
  '/setting': 'bi-gear',
  '/widgets': 'bi-grid',
  '/graph': 'bi-graph-up',
  '/lorawan': 'bi-broadcast',
  '/calculation': 'bi-calculator',
  '/control': 'bi-sliders',
}

export function AppShell({ children }: { children: ReactNode }) {
  const { t, i18n } = useTranslation()
  const { menu, user, website, logout,readOnly } = useAuth()
  const branding = useBranding()
  const { mode, toggle } = useColorMode()
  const navigate = useNavigate()
  const location = useLocation()
  const { token } = antdTheme.useToken()
  const [collapsed, setCollapsed] = useState(false)
  const [isFullscreen, setIsFullscreen] = useState(false)
 const [clock,setClock]=useState(()=>new Date())
 useEffect(()=>{const timer=setInterval(()=>setClock(new Date()),60000);return()=>clearInterval(timer)},[])
  const language = i18n.language.startsWith('th') ? 'th' : 'en'
  const isDark = mode === 'dark'
  const logo = branding.data?.logo
  const headerText = isDark ? ADMINLTE.textDark : ADMINLTE.textLight
  const headerMuted = isDark ? ADMINLTE.mutedDark : ADMINLTE.mutedLight
  const headerBorder = isDark ? ADMINLTE.borderDark : ADMINLTE.borderLight

  useEffect(() => {
    const onFs = () => setIsFullscreen(Boolean(document.fullscreenElement))
    document.addEventListener('fullscreenchange', onFs)
    return () => document.removeEventListener('fullscreenchange', onFs)
  }, [])

  const selected =
    [...menu].sort((a,b)=>b.path.length-a.path.length).find(
      (item) => location.pathname === item.path || location.pathname.startsWith(`${item.path}/`),
    )?.path ?? location.pathname

  const pageTitle = t(menu.find((item) => item.path === selected)?.labelKey ?? 'home')

  return (
    <Layout style={{ minHeight: '100vh' }}>
      <Sider
        className="app-shell-sider"
        theme="dark"
        width={250}
        collapsedWidth={0}
        collapsible
        collapsed={collapsed}
        trigger={null}
        breakpoint="lg"
        onBreakpoint={(broken) => {
          if (broken) setCollapsed(true)
        }}
        style={{ background: ADMINLTE.sidebarDark }}
      >
        <a
          className="app-shell-brand"
          href="/home"
          onClick={(event) => {
            event.preventDefault()
            navigate('/home')
          }}
        >
          {logo ? (
            <img src={assetImageUrl(logo, 'logo.png')} alt={website} />
          ) : null}
          <span>{website}</span>
        </a>
        <Menu
          theme="dark"
          mode="inline"
          selectedKeys={[selected]}
          style={{ background: ADMINLTE.sidebarDark }}
          items={menu.map((item) => ({
            key: item.path,
            icon: MENU_ICONS[item.path] ? (
              <i className={`bi ${MENU_ICONS[item.path]}`} />
            ) : undefined,
            label: t(item.labelKey),
          }))}
          onClick={({ key }) => {
            navigate(key)
          }}
        />
        <div style={{color:"#dee2e6",textAlign:"center",padding:16,fontSize:13}}>{clock.toLocaleString("sv-SE",{timeZone:"Asia/Bangkok",year:"numeric",month:"2-digit",day:"2-digit",hour:"2-digit",minute:"2-digit"})}</div>
      </Sider>
      <Layout>
        <Header
          className="app-shell-header"
          style={
            {
              background: isDark ? ADMINLTE.bodyDark : ADMINLTE.headerLight,
              color: headerText,
              ['--jc-header-text' as string]: headerText,
              ['--jc-header-muted' as string]: headerMuted,
              ['--jc-header-border' as string]: headerBorder,
            } as CSSProperties
          }
        >
          <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
            <Button
              type="text"
              aria-label="Toggle sidebar"
              onClick={() => setCollapsed((value) => !value)}
              icon={<i className="bi bi-list" style={{ fontSize: 20 }} />}
            />
            <span style={{ color: headerMuted, fontWeight: 500, paddingInline: 8 }}>{pageTitle}</span>
            {menu.some(m=>m.path==="/site")&&<Button type="text" onClick={()=>navigate(location.pathname==="/home"&&location.hash==="#site"?`/home${location.search}`:`/home${location.pathname==="/home"?location.search:""}#site`)} icon={<i className="bi bi-diagram-3"/>}>View Site</Button>}
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 4 }}>
            {readOnly&&<span title="Existing database is read-only during verification" style={{fontSize:12,color:headerMuted}}>Read only</span>}
            <Dropdown
              trigger={['click']}
              menu={{
                items: [
                  { key: 'en', label: 'English' },
                  { key: 'th', label: 'ไทย' },
                ],
                onClick: ({ key }) => {
                  setLanguage(key === 'th' ? 'th' : 'en')
                },
              }}
            >
              <Button type="text">
                {language === 'th' ? t('lang_name_thai') : t('lang_name_english')}
              </Button>
            </Dropdown>
            <Button
              type="text"
              title="Toggle Dark Mode"
              onClick={toggle}
              icon={<i className={`bi ${mode === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill'}`} />}
            />
            <Button
              type="text"
              title="Toggle fullscreen"
              onClick={() => {
                if (document.fullscreenElement) {
                  void document.exitFullscreen()
                } else {
                  void document.documentElement.requestFullscreen()
                }
              }}
              icon={
                <i className={`bi ${isFullscreen ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen'}`} />
              }
            />
            <Dropdown
              trigger={['click']}
              placement="bottomRight"
              popupRender={() => (
                <div
                  style={{
                    width: 280,
                    background: token.colorBgElevated,
                    borderRadius: 8,
                    overflow: 'hidden',
                    border: `1px solid ${token.colorBorderSecondary}`,
                  }}
                >
                  <div
                    style={{
                      background: ADMINLTE.primary,
                      color: '#fff',
                      textAlign: 'center',
                      padding: '16px 12px',
                    }}
                  >
                    <img
                      src={assetImageUrl(user?.profilePic, 'user.png')}
                      alt=""
                      width={64}
                      height={64}
                      style={{ borderRadius: '50%', objectFit: 'cover', marginBottom: 8 }}
                    />
                    <div style={{ fontWeight: 600 }}>{user?.name}</div>
                  </div>
                  <div
                    style={{
                      display: 'flex',
                      justifyContent: 'space-between',
                      gap: 8,
                      padding: 12,
                    }}
                  >
                    <Button
                      onClick={() => {
                        navigate('/profile')
                      }}
                    >
                      {t('profile')}
                    </Button>
                    <Button
                      onClick={() => {
                        void logout().then(() => {
                          navigate('/login', { replace: true })
                        })
                      }}
                    >
                      {t('Signout')}
                    </Button>
                  </div>
                </div>
              )}
            >
              <span className="app-shell-user-trigger">
                <img src={assetImageUrl(user?.profilePic, 'user.png')} alt="" />
                <span className="d-none d-md-inline">{user?.name}</span>
              </span>
            </Dropdown>
          </div>
        </Header>
        <Content className="app-shell-content">{children}</Content>
      </Layout>
    </Layout>
  )
}
