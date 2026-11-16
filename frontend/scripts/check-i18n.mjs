// Contrôle des traductions du front (docs/decisions.md, « Internationalisation »), sans dépendance :
//   node scripts/check-i18n.mjs     (make i18n-check)
// Échoue si l'anglais ou l'allemand n'ont pas exactement les clés du français, si une valeur est vide, si les
// formes du pluriel ou les variables ({name}) diffèrent, ou si une clé utilisée dans le code n'existe pas.
import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const LOCALES = ['fr', 'en', 'de']
const errors = []

const flatten = (tree, prefix = '') =>
  Object.entries(tree).flatMap(([key, value]) =>
    typeof value === 'object' && value !== null ? flatten(value, `${prefix}${key}.`) : [[`${prefix}${key}`, value]],
  )
const messages = Object.fromEntries(
  LOCALES.map((locale) => [locale, Object.fromEntries(flatten(JSON.parse(readFileSync(join(root, 'src/locales', `${locale}.json`), 'utf8'))))]),
)
const french = messages.fr
const variables = (text) => [...String(text).matchAll(/\{(\w+)\}/g)].map((m) => m[1]).sort().join(',')
const forms = (text) => String(text).split('|').length

// 1. Mêmes clés, aucune valeur vide, mêmes pluriels et mêmes variables qu'en français
for (const locale of LOCALES) {
  for (const [key, value] of Object.entries(messages[locale])) {
    // Fin de phrase après un mot en gras (« veut s'abonner à toi ») : vide quand la langue n'en a pas besoin
    if (String(value).trim() === '' && !key.endsWith('.after')) errors.push(`${locale} : valeur vide pour ${key}`)
  }
  if (locale === 'fr') continue
  for (const key of Object.keys(french)) {
    if (!(key in messages[locale])) errors.push(`${locale} : clé manquante ${key}`)
    else if (forms(french[key]) !== forms(messages[locale][key])) errors.push(`${locale} : nombre de formes du pluriel différent pour ${key}`)
    else if (variables(french[key]) !== variables(messages[locale][key])) errors.push(`${locale} : variables différentes pour ${key}`)
  }
  for (const key of Object.keys(messages[locale])) {
    if (!(key in french)) errors.push(`${locale} : clé en trop (absente du français) ${key}`)
  }
}

// 2. Clés écrites dans le code : t('…') et $t('…')
const files = (dir) =>
  readdirSync(dir).flatMap((name) => {
    const path = join(dir, name)
    return statSync(path).isDirectory() ? files(path) : /\.(vue|js)$/.test(name) ? [path] : []
  })
const used = new Set()
for (const file of files(join(root, 'src'))) {
  for (const match of readFileSync(file, 'utf8').matchAll(/\$?\bt\(\s*['"]([a-z0-9_]+(?:\.[a-z0-9_]+)+)['"]/g)) used.add(match[1])
}

// 3. Clés construites dans le code (`enum.visibility.${value}`…) : toutes les valeurs possibles
const DYNAMIC = {
  'enum.visibility': ['private', 'close_friends', 'followers', 'personal'],
  'enum.category_type': ['top', 'bottom', 'shoes', 'media', 'other'],
  'enum.item_status': ['available', 'in_laundry', 'reserved', 'lent', 'for_sale'],
  'form.placeholder': ['place', 'room', 'storage', 'category', 'item'],
  level: ['place', 'room', 'storage', 'category', 'item'],
  relation: ['close_friend', 'follower'],
  'friends.type': ['close_friend', 'follower'],
  'room.visibility': ['private', 'close_friends', 'followers', 'personal'],
  language: LOCALES,
}
for (const [prefix, values] of Object.entries(DYNAMIC)) values.forEach((value) => used.add(`${prefix}.${value}`))
for (const type of ['close_friend', 'follower']) ['before', 'verb', 'after'].forEach((part) => used.add(`friends.request.${type}.${part}`))

// 4. Catégories proposées par l'application (Category.labelKey, fixtures du back)
const categoryLabels = join(root, '../backend/src/DataFixtures/CategoryLabels.php')
if (existsSync(categoryLabels)) {
  for (const match of readFileSync(categoryLabels, 'utf8').matchAll(/'(category\.[a-z_]+)'/g)) used.add(match[1])
}

for (const key of used) {
  if (!(key in french)) errors.push(`fr : clé utilisée dans le code mais absente ${key}`)
}

if (errors.length) {
  console.error(`Traductions incomplètes (${errors.length}) :\n- ${errors.join('\n- ')}`)
  process.exit(1)
}
console.log(`Traductions complètes : ${Object.keys(french).length} clés en ${LOCALES.join(', ')}, ${used.size} clés vérifiées dans le code.`)
