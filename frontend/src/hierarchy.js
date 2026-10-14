// Description des niveaux de la hiérarchie : place > room > storage > category > item.
// Une seule vue (LevelView) affiche n'importe quel niveau à partir de cette configuration.

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

const visibilityField = { name: 'visibility', label: 'Visibilité', type: 'select', options: VISIBILITY_OPTIONS, default: 'private' }
const personalField = { name: 'isPersonal', label: 'Perso (visible par moi seul)', type: 'checkbox', default: false }

export const LEVELS = {
  place: {
    key: 'place',
    label: 'Logement',
    withArticle: 'un logement',
    plural: 'Mes logements',
    resource: '/places',
    // Racine : pas de parent, la liste vient de /places
    listPath: () => '/places',
    parentField: null,
    childLevel: 'room',
    countField: 'roomCount',
    countLabel: (n) => `${n} pièce${n > 1 ? 's' : ''}`,
    fields: [
      { name: 'name', label: 'Nom', type: 'text', required: true, placeholder: 'Appartement, maison de vacances…' },
      { name: 'city', label: 'Ville', type: 'text' },
      { name: 'address', label: 'Adresse', type: 'text' },
      visibilityField,
    ],
  },
  room: {
    key: 'room',
    label: 'Pièce',
    withArticle: 'une pièce',
    plural: 'Pièces',
    resource: '/rooms',
    listPath: (parentId) => `/places/${parentId}/rooms`,
    parentLevel: 'place',
    parentField: 'place',
    childLevel: 'storage',
    countField: 'storageCount',
    countLabel: (n) => `${n} rangement${n > 1 ? 's' : ''}`,
    fields: [
      { name: 'name', label: 'Nom', type: 'text', required: true, placeholder: 'Chambre, salon, cave…' },
      visibilityField,
      personalField,
    ],
  },
  storage: {
    key: 'storage',
    label: 'Rangement',
    withArticle: 'un rangement',
    plural: 'Rangements',
    resource: '/storages',
    listPath: (parentId) => `/rooms/${parentId}/storages`,
    parentLevel: 'room',
    parentField: 'room',
    childLevel: 'category',
    countField: 'categoryCount',
    countLabel: (n) => `${n} catégorie${n > 1 ? 's' : ''}`,
    fields: [
      { name: 'name', label: 'Nom', type: 'text', required: true, placeholder: 'Armoire, tiroir, étagère…' },
      visibilityField,
      personalField,
    ],
  },
  category: {
    key: 'category',
    label: 'Catégorie',
    withArticle: 'une catégorie',
    plural: 'Catégories',
    resource: '/categories',
    listPath: (parentId) => `/storages/${parentId}/categories`,
    parentLevel: 'storage',
    parentField: 'storage',
    childLevel: 'item',
    countField: 'itemCount',
    countLabel: (n) => `${n} objet${n > 1 ? 's' : ''}`,
    fields: [
      { name: 'name', label: 'Nom', type: 'text', required: true, placeholder: 'T-shirts, vinyles rock…' },
      { name: 'type', label: 'Type', type: 'select', options: CATEGORY_TYPE_OPTIONS, default: 'other' },
    ],
  },
  item: {
    key: 'item',
    label: 'Objet',
    withArticle: 'un objet',
    plural: 'Objets',
    resource: '/items',
    listPath: (parentId) => `/categories/${parentId}/items`,
    parentLevel: 'category',
    parentField: 'category',
    childLevel: null,
    fields: [
      { name: 'name', label: 'Nom', type: 'text', required: true, placeholder: 'T-shirt blanc, Abbey Road…' },
      { name: 'status', label: 'Statut', type: 'select', options: ITEM_STATUS_OPTIONS, default: 'available' },
      { name: 'photo', label: 'Photo (URL)', type: 'text' },
      { name: 'barcode', label: 'Code-barres', type: 'text' },
      personalField,
    ],
  },
}

// Ordre du parent vers l'enfant, utilisé pour le fil d'Ariane
export const LEVEL_ORDER = ['place', 'room', 'storage', 'category', 'item']
