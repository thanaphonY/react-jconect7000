const STORAGE_KEY = 'site_lang'

export type AppLanguage = 'en' | 'th'


export function languageFromStorage(): AppLanguage {
  try {
    return sessionStorage.getItem(STORAGE_KEY) === 'thai' ? 'th' : 'en'
  } catch {
    return 'en'
  }
}

export function persistLanguage(language: AppLanguage): void {
  document.documentElement.lang = language
  try {
    if (language === 'th') {
      sessionStorage.setItem(STORAGE_KEY, 'thai')
    } else {
      sessionStorage.removeItem(STORAGE_KEY)
    }
  } catch {
    // Session storage can be blocked; the in-memory language still changes.
  }
}
