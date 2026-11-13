import { useQuery } from '@tanstack/react-query'
import { ConfigProvider, Typography, theme } from 'antd'
import { useEffect, type ReactNode } from 'react'
import { apiRequest } from '../api/client'
import { applyFavicon, assetImageUrl, type Branding } from '../lib/assets'

export function useBranding() {
  return useQuery({
    queryKey: ['branding'],
    queryFn: () => apiRequest<Branding>('/branding'),
    staleTime: 60_000,
  })
}

/** Auth pages: favicon is hardcoded like PHP (login Logo.png / forgot favicon.ico), not DB branding. */
export function AuthBrandLayout({
  children,
  favicon = 'favicon.ico',
}: {
  children: ReactNode
  favicon?: string
}) {
  const branding = useBranding()
  const website = branding.data?.website || 'JConnect'
  const logo = branding.data?.logo

  useEffect(() => {
    applyFavicon(favicon)
  }, [favicon])

  useEffect(() => {
    if (branding.data?.website) {
      document.title = branding.data.website
    }
  }, [branding.data?.website])

  return (
    <ConfigProvider
      theme={{
        algorithm: theme.defaultAlgorithm,
        token: {
          colorPrimary: '#0d6efd',
          fontFamily: "'Sarabun', sans-serif",
          borderRadius: 6,
        },
      }}
    >
      <div className="auth-page" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24 }}>
        <div style={{ width: 360, maxWidth: '100%' }}>
          <div style={{ textAlign: 'center', marginBottom: 20 }}>
            {logo ? (
              <img
                src={assetImageUrl(logo, 'logo.png')}
                alt={website}
                style={{ maxHeight: 72, maxWidth: '100%', marginBottom: 8 }}
              />
            ) : null}
            <Typography.Title level={3} style={{ margin: 0, color: '#555' }}>
              <strong>{website}</strong>
            </Typography.Title>
          </div>
          {children}
        </div>
      </div>
    </ConfigProvider>
  )
}
