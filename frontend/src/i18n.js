import { createI18n } from 'vue-i18n'
import de from './locales/de.json'
import en from './locales/en.json'
import fr from './locales/fr.json'

// Langues de l'application (docs/decisions.md, « Internationalisation ») : le français est la langue par défaut
// et la langue de secours. Le choix est gardé dans le navigateur (avant la connexion) et sur le compte (User.locale).
export const LOCALES = ['fr', 'en', 'de']
export const DEFAULT_LOCALE = 'fr'
const STORAGE_KEY = 'penderie_locale'

function storedLocale() {
  try {
    return localStorage.getItem(STORAGE_KEY)
  } catch {
    return null
  }
}

// Première visite : la langue du navigateur si elle est proposée, sinon le français
function initialLocale() {
  const stored = storedLocale()
  if (LOCALES.includes(stored)) return stored
  const browser = (navigator.languages ?? [navigator.language]).map((lang) => String(lang).slice(0, 2).toLowerCase())
  return browser.find((lang) => LOCALES.includes(lang)) ?? DEFAULT_LOCALE
}

// Français : 0 et 1 au singulier (« 0 objet », « 1 objet »), au-delà au pluriel
function frenchPlural(choice, choicesLength) {
  if (choicesLength === 2) return choice > 1 ? 1 : 0
  return Math.min(choice, choicesLength - 1)
}

export const i18n = createI18n({
  legacy: false,
  locale: DEFAULT_LOCALE,
  fallbackLocale: DEFAULT_LOCALE,
  messages: { fr, en, de },
  pluralRules: { fr: frenchPlural },
})

// Traduction hors des composants (hierarchy.js, utilitaires, composables)
export const t = (...args) => i18n.global.t(...args)

export function currentLocale() {
  return i18n.global.locale.value
}

/**
 * Change la langue de l'interface : textes, attribut lang de la page (lecteurs d'écran, césure), titre et
 * description, et choix gardé dans le navigateur. Une langue non proposée donne le français.
 */
export function setLocale(locale) {
  const chosen = LOCALES.includes(locale) ? locale : DEFAULT_LOCALE
  i18n.global.locale.value = chosen
  document.documentElement.lang = chosen
  document.title = t('app.title')
  document.querySelector('meta[name="description"]')?.setAttribute('content', t('app.description'))
  try {
    localStorage.setItem(STORAGE_KEY, chosen)
  } catch {
    // stockage indisponible : la langue revient à celle du navigateur au rechargement
  }
  return chosen
}

setLocale(initialLocale())
