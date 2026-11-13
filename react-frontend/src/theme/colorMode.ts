export const COLOR_MODE_KEY = 'jconnect_color_mode'

export type ColorMode = 'dark' | 'light'

export function readColorMode(): ColorMode {
  const stored = window.localStorage.getItem(COLOR_MODE_KEY)
  if (stored === 'light' || stored === 'dark') {
    return stored
  }
  return 'dark'
}

export function writeColorMode(mode: ColorMode) {
  window.localStorage.setItem(COLOR_MODE_KEY, mode)
  document.documentElement.setAttribute('data-bs-theme', mode)
  document.documentElement.style.colorScheme = mode
}

export function applyInitialColorMode() {
  writeColorMode(readColorMode())
}
