<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import BottomSheet from '../components/BottomSheet.vue'
import NodeForm from '../components/NodeForm.vue'
import StorageAccordion from '../components/StorageAccordion.vue'
import { LEVELS, VISIBILITY_OPTIONS, idFromIri, iri, optionLabel, plural } from '../hierarchy'

const props = defineProps({
  id: { type: String, required: true },
})
const router = useRouter()

const room = ref(null)
const place = ref(null)
const storages = ref([])
const loading = ref(true)
const error = ref('')

// Panneau ouvert : 'room-edit', 'storage-create' ou 'item-create'
const sheet = ref(null)
const saving = ref(false)
const formError = ref(null)

// Ajout d'objet : catégories de toute la pièce et catégorie présélectionnée
const categoryOptions = ref([])
const preselectedCategory = ref(null)
const refreshKeys = ref({})

const TONES = ['tone-orange', 'tone-glaucous', 'tone-peach']

async function load() {
  error.value = ''
  try {
    room.value = await api.get(iri('room', props.id))
    const [placeData, storageList] = await Promise.all([
      api.get(room.value.place),
      api.list(`/rooms/${props.id}/storages`),
    ])
    place.value = placeData
    storages.value = storageList
  } catch (e) {
    error.value = e.status === 404 ? 'Cette pièce est introuvable.' : e.message
  } finally {
    loading.value = false
  }
}

watch(
  () => props.id,
  () => {
    loading.value = true
    load()
  },
  { immediate: true },
)

const visibilityChip = computed(() => {
  if (!room.value) return null
  if (room.value.isPersonal) return { icon: 'lock', label: 'Perso' }
  if (room.value.visibility === 'private') return { icon: 'lock', label: 'Privée' }
  return { icon: 'eye', label: optionLabel(VISIBILITY_OPTIONS, room.value.visibility) }
})

// Formulaire d'objet : les champs de l'objet + le choix de la catégorie
const itemFields = computed(() => [
  LEVELS.item.fields[0],
  {
    name: 'category',
    label: 'Catégorie',
    type: 'select',
    required: true,
    options: categoryOptions.value,
    default: preselectedCategory.value ?? categoryOptions.value[0]?.value,
  },
  ...LEVELS.item.fields.slice(1),
])

function openSheet(name) {
  formError.value = null
  sheet.value = name
}

async function openItemSheet(category = null) {
  preselectedCategory.value = category ? iri('category', category.id) : null
  formError.value = null
  // Liste « Rangement › Catégorie » de toute la pièce
  const lists = await Promise.all(storages.value.map((storage) => api.list(`/storages/${storage.id}/categories`)))
  categoryOptions.value = storages.value.flatMap((storage, i) =>
    lists[i].map((cat) => ({ value: iri('category', cat.id), label: `${storage.name} › ${cat.name}` })),
  )
  sheet.value = 'item-create'
}

async function save(action) {
  saving.value = true
  formError.value = null
  try {
    await action()
    sheet.value = null
  } catch (e) {
    formError.value = e
  } finally {
    saving.value = false
  }
}

const updateRoom = (payload) =>
  save(async () => {
    room.value = await api.patch(iri('room', props.id), payload)
  })

const deleteRoom = () =>
  save(async () => {
    await api.delete(iri('room', props.id))
    router.push('/')
  })

const createStorage = (payload) =>
  save(async () => {
    await api.post('/storages', { ...payload, room: iri('room', props.id) })
    await load()
  })

const createItem = (payload) =>
  save(async () => {
    await api.post('/items', payload)
    // Recharge le rangement qui contient la catégorie choisie
    const category = await api.get(payload.category)
    const storageId = idFromIri(category.storage)
    refreshKeys.value = { ...refreshKeys.value, [storageId]: (refreshKeys.value[storageId] ?? 0) + 1 }
    await load()
  })

function onStorageDeleted(storage) {
  storages.value = storages.value.filter((existing) => existing.id !== storage.id)
  load()
}
</script>

<template>
  <div class="screen--flush">
    <header class="hero">
      <div class="hero-inner">
        <div class="hero-bar">
          <RouterLink to="/" class="icon-btn icon-btn--on-dark" aria-label="Retour à l'accueil">
            <AppIcon name="back" :stroke="2.6" />
          </RouterLink>
          <div class="hero-actions">
            <button v-if="room" type="button" class="icon-btn icon-btn--on-dark" aria-label="Réglages de la pièce" @click="openSheet('room-edit')">
              <AppIcon name="settings" :size="19" />
            </button>
          </div>
        </div>
        <div v-if="room" class="hero-heading">
          <span class="hero-eyebrow">{{ place?.name }}<template v-if="place?.city"> · {{ place.city }}</template></span>
          <h1 class="display hero-title">{{ room.name }}</h1>
          <div class="chips hero-chips">
            <span class="chip chip--glass">{{ plural(room.storageCount ?? 0, 'rangement') }}</span>
            <span class="chip chip--glass">{{ plural(room.itemCount ?? 0, 'objet') }}</span>
            <span class="chip chip--cream"><AppIcon :name="visibilityChip.icon" :size="12" :stroke="2.2" /> {{ visibilityChip.label }}</span>
          </div>
        </div>
      </div>
    </header>

    <main class="screen-body">
      <p v-if="loading" class="loading">Chargement…</p>
      <p v-else-if="error" class="error" role="alert">{{ error }}</p>
      <template v-else>
        <div class="section-head">
          <h2 class="section-title">Rangements</h2>
        </div>

        <div class="storage-list">
          <StorageAccordion
            v-for="(storage, index) in storages"
            :key="storage.id"
            :storage="storage"
            :tone="TONES[index % TONES.length]"
            :open="index === 0"
            :refresh-key="refreshKeys[storage.id] ?? 0"
            @changed="load"
            @deleted="onStorageDeleted"
            @add-item="openItemSheet"
          />

          <button type="button" class="add-tile add-tile--row" @click="openSheet('storage-create')">
            <AppIcon name="plus" :stroke="2.4" /> Ajouter un rangement
          </button>
        </div>
      </template>
    </main>

    <button
      v-if="room && storages.length"
      type="button"
      class="fab"
      :aria-label="`Ajouter un objet dans ${room.name}`"
      @click="openItemSheet()"
    >
      <AppIcon name="plus" :size="26" :stroke="2.6" />
    </button>

    <BottomSheet v-if="sheet === 'room-edit'" title="Réglages de la pièce" @close="sheet = null">
      <NodeForm
        :fields="LEVELS.room.fields"
        :initial="room"
        :saving="saving"
        :error="formError"
        deletable
        delete-label="Supprimer cette pièce"
        delete-warning="Tous ses rangements et tous les objets qu'ils contiennent seront supprimés."
        @submit="updateRoom"
        @delete="deleteRoom"
      />
    </BottomSheet>

    <BottomSheet v-if="sheet === 'storage-create'" title="Nouveau rangement" @close="sheet = null">
      <NodeForm :fields="LEVELS.storage.fields" :saving="saving" :error="formError" submit-label="Créer" @submit="createStorage" />
    </BottomSheet>

    <BottomSheet v-if="sheet === 'item-create'" title="Nouvel objet" @close="sheet = null">
      <div v-if="!categoryOptions.length" class="empty-state">
        <p>Il faut d'abord une catégorie dans un rangement pour y ranger un objet.</p>
      </div>
      <NodeForm v-else :fields="itemFields" :saving="saving" :error="formError" submit-label="Ajouter" @submit="createItem" />
    </BottomSheet>
  </div>
</template>
