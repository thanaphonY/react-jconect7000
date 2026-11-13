import { createContext, useContext, useEffect, useMemo, useState, type ReactNode } from 'react'
import { apiRequest, setAccessToken } from '../api/client'
import { setLanguage } from '../i18n'

export interface SessionUser {
  id: number
  name: string
  email: string
  userType: string
  profilePic: string | null
}

export interface MenuItem {
  labelKey: string
  path: string
}

interface SessionData {
 readOnly?:boolean
  accessToken: string
  user: SessionUser
  menu: MenuItem[]
  website: string
}

interface AuthState {
 readOnly:boolean
  status: 'loading' | 'ready'
  user: SessionUser | null
  menu: MenuItem[]
  website: string
  login: (email: string, password: string, remember: boolean) => Promise<void>
  logout: () => Promise<void>
}

const AuthContext = createContext<AuthState | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [status, setStatus] = useState<'loading' | 'ready'>('loading')
  const [user, setUser] = useState<SessionUser | null>(null)
  const [menu, setMenu] = useState<MenuItem[]>([])
  const [website, setWebsite] = useState('JConnect')
 const [readOnly,setReadOnly]=useState(true)

  function applySession(data: SessionData) {
    setReadOnly(data.readOnly!==false)
    setAccessToken(data.accessToken)
    setUser(data.user)
    setMenu(data.menu)
    setWebsite(data.website)
  }

  useEffect(() => {
    let cancelled = false
    apiRequest<SessionData>('/auth/refresh', { method: 'POST' })
      .then((data) => {
        if (!cancelled) {
          applySession(data)
        }
      })
      .catch(() => {
        setAccessToken(null)
      })
      .finally(() => {
        if (!cancelled) {
          setStatus('ready')
        }
      })
    return () => {
      cancelled = true
    }
  }, [])

  const value = useMemo<AuthState>(
    () => ({
      status,
      readOnly,
      user,
      menu,
      website,
      login: async (email, password, remember) => {
        const data = await apiRequest<SessionData>('/auth/login', {
          method: 'POST',
          body: { email, password, remember },
        })
        applySession(data)
      },
      logout: async () => {
        try {
          await apiRequest('/auth/logout', { method: 'POST' })
        } finally {
          setAccessToken(null)
          setUser(null)
          setMenu([])
          setLanguage('en')
        }
      },
    }),
    [status, user, menu, website,readOnly],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}

export function useAuth(): AuthState {
  const value = useContext(AuthContext)
  if (!value) {
    throw new Error('useAuth must be used inside AuthProvider')
  }
  return value
}
