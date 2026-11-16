import { t } from './i18n'

// Niveaux de la hiérarchie place > room > storage > category > item :
// ressource d'API, champs des formulaires et libellés.

// Option d'une liste (visibilité, type, statut) : la valeur envoyée à l'API et son libellé traduit, lu à chaque
// affichage pour suivre la langue choisie
const option = (value, key) => ({ value, get label() { return t(key) } })

export const VISIBILITY_OPTIONS = ['private', 'close_friends', 'followers'].map((value) => option(value, `enum.visibility.${value}`))

export const CATEGORY_TYPE_OPTIONS = ['top', 'bottom', 'shoes', 'media', 'other'].map((value) => option(value, `enum.category_type.${value}`))

export const ITEM_STATUS_OPTIONS = ['available', 'in_laundry', 'reserved', 'lent', 'for_sale'].map((value) => option(value, `enum.item_status.${value}`))

export function optionLabel(options, value) {
  return options.find((option) => option.value === value)?.label ?? value
}

// Champ de formulaire au libellé (et à l'exemple) traduits
const field = (definition, labelKey, placeholderKey = null) => ({
  ...definition,
  get label() { return t(labelKey) },
  get placeholder() { return placeholderKey ? t(placeholderKey) : undefined },
})
const nameField = (level) => field({ name: 'name', type: 'text', required: true }, 'form.name', `form.placeholder.${level}`)
const visibilityField = field({ name: 'visibility', type: 'select', options: VISIBILITY_OPTIONS, default: 'private' }, 'form.visibility')
const personalField = field({ name: 'isPersonal', type: 'checkbox', default: false }, 'form.personal')

const level = (key, definition) => ({ ...definition, get label() { return t(`level.${key}`) } })

export const LEVELS = {
  place: level('place', {
    resource: '/places',
    parentField: null,
    fields: [nameField('place'), field({ name: 'city', type: 'text' }, 'form.city'), field({ name: 'address', type: 'text' }, 'form.address'), visibilityField],
  }),
  room: level('room', {
    resource: '/rooms',
    parentField: 'place',
    fields: [nameField('room'), visibilityField, personalField],
  }),
  storage: level('storage', {
    resource: '/storages',
    parentField: 'room',
    fields: [nameField('storage'), visibilityField, personalField],
  }),
  category: level('category', {
    resource: '/categories',
    parentField: 'storage',
    fields: [nameField('category'), field({ name: 'type', type: 'select', options: CATEGORY_TYPE_OPTIONS, default: 'other' }, 'form.type')],
  }),
  item: level('item', {
    resource: '/items',
    parentField: 'category',
    fields: [
      nameField('item'),
      field({ name: 'status', type: 'select', options: ITEM_STATUS_OPTIONS, default: 'available' }, 'form.status'),
      field({ name: 'barcode', type: 'text' }, 'form.barcode'),
      personalField,
    ],
  }),
}

// Nom affiché d'une catégorie : la traduction d'une catégorie proposée par l'application (labelKey), sinon le nom
// saisi par l'utilisateur, qui ne se traduit pas (docs/decisions.md, « Internationalisation »)
export function categoryLabel(category) {
  return category?.labelKey ? t(category.labelKey) : (category?.name ?? '')
}

// Même chose pour la catégorie d'un objet (champs categoryLabelKey et categoryName de l'API)
export function itemCategoryLabel(item) {
  return categoryLabel({ labelKey: item?.categoryLabelKey, name: item?.categoryName })
}

// IRI d'un élément, ex. iri('room', 3) -> /api/rooms/3
export function iri(level, id) {
  return `/api${LEVELS[level].resource}/${id}`
}

// Identifiant numérique à partir d'une IRI (/api/rooms/3 -> 3)
export function idFromIri(value) {
  return value ? Number(value.split('/').pop()) : null
}

const VISIBILITY_RANK = { private: 0, close_friends: 1, followers: 2 }

/**
 * Visibilité réelle d'un objet : "perso" l'emporte, sinon le niveau le plus fermé
 * entre le rangement, la pièce et le logement.
 */
export function effectiveVisibility(item, containers) {
  if (item.isPersonal || containers.some((container) => container?.isPersonal)) {
    return { value: 'personal', label: t('enum.visibility.personal') }
  }
  const value = containers
    .map((container) => container?.visibility ?? 'private')
    .reduce((closest, current) => (VISIBILITY_RANK[current] < VISIBILITY_RANK[closest] ? current : closest), 'followers')

  return { value, label: optionLabel(VISIBILITY_OPTIONS, value) }
}

// Icône d'une pièce d'après son nom, comme sur l'accueil des maquettes
export function roomIcon(name = '') {
  const lower = name.toLowerCase()
  if (/chambre|dressing|penderie/.test(lower)) return 'hanger'
  if (/salon|séjour|musique/.test(lower)) return 'disc'
  if (/garage|atelier|cave|outil/.test(lower)) return 'tool'
  return 'box'
}

// Couleurs de fond alternées des vignettes
// Fonds des vignettes sans photo, en alternance (maquette v2)
const TONES = ['tone-sky', 'tone-glaucous', 'tone-peach']
export function toneFor(index) {
  return TONES[index % TONES.length]
}

// Couleur de la puce de statut d'un objet : « Au sale » rose pâle, « Prêté » cyan, « En vente » violet pâle.
// Sur un fond de la même couleur, la puce passe en blanc pour rester visible.
const STATUS_CHIPS = { in_laundry: 'chip--peach', lent: 'chip--sky', for_sale: 'chip--glaucous', reserved: 'chip--white' }
export function statusChip(status, tone) {
  const chip = STATUS_CHIPS[status] ?? 'chip--white'
  return chip.replace('chip--', 'tone-') === tone ? 'chip--white' : chip
}

