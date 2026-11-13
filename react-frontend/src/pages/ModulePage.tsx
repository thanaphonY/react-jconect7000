import { Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { Navigate, useLocation } from 'react-router'
import { useAuth } from '../auth/AuthContext'

export function ModulePage({ titleKey }: { titleKey: string }) {
  const { t } = useTranslation()
  const { menu } = useAuth()
  const location = useLocation()
  const allowed = menu.some((item) => item.path === location.pathname)

  if (!allowed) {
    return <Navigate to="/home" replace />
  }

  return <Typography.Title level={3}>{t(titleKey)}</Typography.Title>
}
