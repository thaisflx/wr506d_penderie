// Date relative en français pour le fil (« il y a 2 h », « hier », « il y a 3 jours », puis « 12 nov. »)
const MINUTE = 60_000
const HOUR = 60 * MINUTE
const DAY = 24 * HOUR

export function relativeTime(value, now = new Date()) {
  const date = new Date(value)
  const elapsed = now - date
  if (Number.isNaN(elapsed)) return ''
  if (elapsed < MINUTE) return 'à l\'instant'
  if (elapsed < HOUR) return `il y a ${Math.floor(elapsed / MINUTE)} min`
  if (elapsed < DAY) return `il y a ${Math.floor(elapsed / HOUR)} h`

  // Au-delà d'un jour, on compte en jours de calendrier
  const startOf = (d) => new Date(d.getFullYear(), d.getMonth(), d.getDate())
  const days = Math.round((startOf(now) - startOf(date)) / DAY)
  if (days <= 1) return 'hier'
  if (days < 7) return `il y a ${days} jours`

  return date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'short', year: date.getFullYear() === now.getFullYear() ? undefined : 'numeric' })
}
