<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import BottomSheet from '../components/BottomSheet.vue'
import NodeForm from '../components/NodeForm.vue'
import StorageAccordion from '../components/StorageAccordion.vue'
import { LEVELS, categoryLabel, idFromIri, iri } from '../hierarchy'
import { t } from '../i18n'

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

// Rangement affiché seul (pilule), ou null pour « Tout »
const selectedStorage = ref(null)
const shownStorages = computed(() =>
  selectedStorage.value ? storages.value.filter((storage) => storage.id === selectedStorage.value) : storages.value,
)

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
    error.value = e.status === 404 ? t('room.not_found') : e.message
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

// « 5 objets · visible par tes abonnés »
const summary = computed(() => {
  if (!room.value) return ''
  const visibility = t(`room.visibility.${room.value.isPersonal ? 'personal' : room.value.visibility}`)
  return [t('count.items', room.value.itemCount ?? 0), t('count.storages', room.value.storageCount ?? 0), visibility].join(' · ')
})

// Formulaire d'objet : les champs de l'objet + le choix de la catégorie
const itemFields = computed(() => [
  LEVELS.item.fields[0],
  {
    name: 'category',
    label: t('item.category'),
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
    lists[i].map((cat) => ({ value: iri('category', cat.id), label: `${storage.name} › ${categoryLabel(cat)}` })),
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
    router.push('/dressing')
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
  if (selectedStorage.value === storage.id) selectedStorage.value = null
  storages.value = storages.value.filter((existing) => existing.id !== storage.id)
  load()
}
</script>

<template>
  <div class="screen room">
    <div class="room-bar">
      <RouterLink to="/dressing" class="icon-btn" :aria-label="$t('items.back')">
        <AppIcon name="back" :stroke="2.4" />
      </RouterLink>
      <nav v-if="room" class="breadcrumb" :aria-label="$t('room.breadcrumb')" data-cy="room-place">
        <RouterLink to="/dressing">{{ place?.name }}</RouterLink> / <span aria-current="page">{{ room.name }}</span>
      </nav>
      <button v-if="room" type="button" class="icon-btn room-settings" :aria-label="$t('room.settings')" @click="openSheet('room-edit')">
        <AppIcon name="settings" :size="19" />
      </button>
    </div>

    <p v-if="loading" class="loading">{{ $t('common.loading') }}</p>
    <p v-else-if="error" class="error" role="alert">{{ error }}</p>
    <template v-else>
      <div>
        <h1 class="display page-title" data-cy="room-name">{{ room.name }}</h1>
        <p class="room-summary">{{ summary }}</p>
      </div>

      <!-- Pilules : « Tout » affiche chaque rangement dépliable, une pilule n'affiche que ce rangement -->
      <div v-if="storages.length > 1" class="pills" role="group" :aria-label="$t('room.storages')">
        <button type="button" class="pill" :aria-pressed="selectedStorage === null" @click="selectedStorage = null">{{ $t('room.all') }}</button>
        <button
          v-for="storage in storages"
          :key="storage.id"
          type="button"
          class="pill"
          :aria-pressed="selectedStorage === storage.id"
          @click="selectedStorage = storage.id"
        >
          {{ storage.name }}<template v-if="storage.isPersonal"> · {{ $t('room.personal_suffix') }}</template>
        </button>
      </div>

      <div class="storage-list">
        <StorageAccordion
          v-for="(storage, index) in shownStorages"
          :key="storage.id"
          :storage="storage"
          :open="selectedStorage !== null || index === 0"
          :refresh-key="refreshKeys[storage.id] ?? 0"
          @changed="load"
          @deleted="onStorageDeleted"
          @add-item="openItemSheet"
        />

        <button type="button" class="add-tile add-tile--row" @click="openSheet('storage-create')">
          <AppIcon name="plus" :stroke="2.4" /> {{ $t('room.add_storage') }}
        </button>
      </div>
    </template>

    <button
      v-if="room && storages.length"
      type="button"
      class="fab"
      data-cy="add-item"
      :aria-label="$t('room.add_item_in', { name: room.name })"
      @click="openItemSheet()"
    >
      <AppIcon name="plus" :size="20" :stroke="2.4" /> {{ $t('room.add_item') }}
    </button>
    <BottomSheet v-if="sheet === 'room-edit'" :title="$t('room.settings')" @close="sheet = null">
      <NodeForm
        :fields="LEVELS.room.fields"
        :initial="room"
        :saving="saving"
        :error="formError"
        deletable
        :delete-label="$t('room.delete_room')"
        :delete-warning="$t('room.delete_room_warning')"
        @submit="updateRoom"
        @delete="deleteRoom"
      />
    </BottomSheet>

    <BottomSheet v-if="sheet === 'storage-create'" :title="$t('room.new_storage')" @close="sheet = null">
      <NodeForm :fields="LEVELS.storage.fields" :saving="saving" :error="formError" :submit-label="$t('common.create')" @submit="createStorage" />
    </BottomSheet>

    <BottomSheet v-if="sheet === 'item-create'" :title="$t('room.new_item')" @close="sheet = null">
      <div v-if="!categoryOptions.length" class="empty-state">
        <p>{{ $t('room.need_category') }}</p>
      </div>
      <NodeForm v-else :fields="itemFields" :saving="saving" :error="formError" :submit-label="$t('common.add')" @submit="createItem" />
    </BottomSheet>
  </div>
</template>
