// Niveaux de la hiérarchie place > room > storage > category > item :
// ressource d'API, champs des formulaires et libellés.

export const VISIBILITY_OPTIONS = [
  { value: 'private', label: 'Privé' },
  { value: 'close_friends', label: 'Amis proches' },
  { value: 'followers', label: 'Abonnés' },
]

export const CATEGORY_TYPE_OPTIONS = [
  { value: 'top', label: 'Haut' },
  { value: 'bottom', label: 'Bas' },
  { value: 'shoes', label: 'Chaussures' },
  { value: 'media', label: 'Média' },
  { value: 'other', label: 'Autre' },
]

export const ITEM_STATUS_OPTIONS = [
  { value: 'available', label: 'Disponible' },
  { value: 'in_laundry', label: 'Au sale' },
  { value: 'reserved', label: 'Réservé' },
  { value: 'lent', label: 'Prêté' },
  { value: 'for_sale', label: 'À la vente' },
]

export function optionLabel(options, value) {
  return options.find((option) => option.value === value)?.label ?? value
}

const visibilityField = { name: 'visibility', label: 'Qui peut le voir ?', type: 'select', options: VISIBILITY_OPTIONS, default: 'private' }
const personalField = { name: 'isPersonal', label: 'Perso (visible par moi seul)', type: 'checkbox', default: false }

export const LEVELS = {
  place: {
    label: 'logement',
    resource: '/places',
    parentField: null,
    fields: [
      { name: 'name', label: 'Nom', type: 'text', required: true, placeholder: 'Maison, appartement…' },
      { name: 'city', label: 'Ville', type: 'text' },
      { name: 'address', label: 'Adresse', type: 'text' },
      visibilityField,
    ],
  },
  room: {
    label: 'pièce',
    resource: '/rooms',
    parentField: 'place',
    fields: [
      { name: 'name', label: 'Nom', type: 'text', required: true, placeholder: 'Chambre, salon, grenier…' },
      visibilityField,
      personalField,
    ],
  },
  storage: {
    label: 'rangement',
    resource: '/storages',
    parentField: 'room',
    fields: [
      { name: 'name', label: 'Nom', type: 'text', required: true, placeholder: 'Armoire, carton, malle…' },
      visibilityField,
      personalField,
    ],
  },
  category: {
    label: 'catégorie',
    resource: '/categories',
    parentField: 'storage',
    fields: [
      { name: 'name', label: 'Nom', type: 'text', required: true, placeholder: 'T-shirts, vinyles rock…' },
      { name: 'type', label: 'Type', type: 'select', options: CATEGORY_TYPE_OPTIONS, default: 'other' },
    ],
  },
  item: {
    label: 'objet',
    resource: '/items',
    parentField: 'category',
    fields: [
      { name: 'name', label: 'Nom', type: 'text', required: true, placeholder: 'Pull bleu, Abbey Road…' },
      { name: 'status', label: 'Statut', type: 'select', options: ITEM_STATUS_OPTIONS, default: 'available' },
      { name: 'barcode', label: 'Code-barres', type: 'text' },
      personalField,
    ],
  },
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
    return { value: 'personal', label: 'Moi seul (perso)' }
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
const TONES = ['tone-glaucous', 'tone-peach', 'tone-orange']
export function toneFor(index) {
  return TONES[index % TONES.length]
}

export function plural(count, singular, pluralForm = `${singular}s`) {
  return `${count} ${count > 1 ? pluralForm : singular}`
}
