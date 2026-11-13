import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import { en } from './en'
import { th } from './th'

const storedLanguage = window.localStorage.getItem('site_lang')
const initialLanguage = storedLanguage === 'th' ? 'th' : 'en'

void i18n.use(initReactI18next).init({
  resources: {
    en: { translation: en },
    th: { translation: th },
  },
  lng: initialLanguage,
  fallbackLng: 'en',
  interpolation: { escapeValue: false },
})

document.documentElement.lang = initialLanguage

export function setLanguage(language: 'en' | 'th') {
  void i18n.changeLanguage(language)
  document.documentElement.lang = language
  window.localStorage.setItem('site_lang', language)
}

export { i18n }
