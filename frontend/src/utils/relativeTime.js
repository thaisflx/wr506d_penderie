import { currentLocale, t } from '../i18n'

// Date relative dans la langue choisie, par Intl (« il y a 2 h », « hier », « vor 3 Tagen », « 12 nov. ») ;
// seul « à l'instant » est une traduction : Intl dirait « maintenant » (docs/decisions.md, « Internationalisation »)
const MINUTE = 60_000
const HOUR = 60 * MINUTE
const DAY = 24 * HOUR

export function relativeTime(value, now = new Date(), locale = currentLocale()) {
  const date = new Date(value)
  const elapsed = now - date
  if (Number.isNaN(elapsed)) return ''
  if (elapsed < MINUTE) return t('time.just_now')
  // Minutes et heures en abrégé (« il y a 2 h »), jours en entier avec « hier » (numeric: 'auto')
  const short = new Intl.RelativeTimeFormat(locale, { numeric: 'auto', style: 'short' })
  if (elapsed < HOUR) return short.format(-Math.floor(elapsed / MINUTE), 'minute')
  if (elapsed < DAY) return short.format(-Math.floor(elapsed / HOUR), 'hour')

  // Au-delà d'un jour, on compte en jours de calendrier
  const startOf = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate())
  const days = Math.round((startOf(now) - startOf(date)) / DAY)
  if (days < 7) return new Intl.RelativeTimeFormat(locale, { numeric: 'auto' }).format(-Math.max(days, 1), 'day')

  return formatDate(date, { day: 'numeric', month: 'short', year: date.getFullYear() === now.getFullYear() ? undefined : 'numeric' }, locale)
}

// Date au format de la langue choisie (Intl), ex. formatDate('2026-11-15', { day: 'numeric', month: 'short' })
export function formatDate(value, options, locale = currentLocale()) {
  return new Intl.DateTimeFormat(locale, options).format(value instanceof Date ? value : new Date(value))
}
