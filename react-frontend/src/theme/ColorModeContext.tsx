import { createContext, useCallback, useContext, useMemo, useState, type ReactNode } from 'react'
import { apiRequest } from '../api/client'
import { applyInitialColorMode, readColorMode, writeColorMode, type ColorMode } from './colorMode'

applyInitialColorMode()

interface ColorModeState {
  mode: ColorMode
  toggle: () => void
  setMode: (mode: ColorMode) => void
}

const ColorModeContext = createContext<ColorModeState | null>(null)

export function ColorModeProvider({ children }: { children: ReactNode }) {
  const [mode, setModeState] = useState<ColorMode>(() => readColorMode())

  const setMode = useCallback((next: ColorMode) => {
    writeColorMode(next)
    setModeState(next)
    void apiRequest('/settings/theme-color', {
      method: 'POST',
      body: { theme_color: next },
    }).catch(() => {
      /* cookie sync is best-effort */
    })
  }, [])

  const toggle = useCallback(() => {
    setMode(mode === 'dark' ? 'light' : 'dark')
  }, [mode, setMode])

  const value = useMemo(() => ({ mode, toggle, setMode }), [mode, toggle, setMode])

  return <ColorModeContext.Provider value={value}>{children}</ColorModeContext.Provider>
}

export function useColorMode(): ColorModeState {
  const ctx = useContext(ColorModeContext)
  if (!ctx) {
    throw new Error('useColorMode must be used inside ColorModeProvider')
  }
  return ctx
}
