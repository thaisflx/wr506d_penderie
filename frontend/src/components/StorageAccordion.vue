<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../api/client'
import { ITEM_STATUS_OPTIONS, LEVELS, VISIBILITY_OPTIONS, iri, optionLabel, plural } from '../hierarchy'
import AppIcon from './AppIcon.vue'
import BottomSheet from './BottomSheet.vue'
import ItemVisual from './ItemVisual.vue'
import NodeForm from './NodeForm.vue'

// Rangement dépliable : ses catégories et leurs objets sont chargés à la première ouverture
const props = defineProps({
  storage: { type: Object, required: true },
  tone: { type: String, default: 'tone-orange' },
  open: { type: Boolean, default: false },
  // Incrémenté par le parent pour forcer un rechargement (ex. objet ajouté)
  refreshKey: { type: Number, default: 0 },
})
const emit = defineEmits(['changed', 'deleted', 'add-item'])

const expanded = ref(props.open)
const categories = ref([])
const itemsByCategory = ref({})
const loaded = ref(false)
const loading = ref(false)
const error = ref('')

// Panneau ouvert : 'storage-edit', 'category-create' ou { edit: catégorie }
const sheet = ref(null)
const saving = ref(false)
const formError = ref(null)

const subtitle = computed(() =>
  [plural(props.storage.itemCount ?? 0, 'objet'), plural(props.storage.categoryCount ?? 0, 'catégorie')].join(' · '),
)

async function load() {
  loading.value = true
  error.value = ''
  try {
    categories.value = await api.list(`/storages/${props.storage.id}/categories`)
    const lists = await Promise.all(categories.value.map((category) => api.list(`/categories/${category.id}/items`)))
    itemsByCategory.value = Object.fromEntries(categories.value.map((category, i) => [category.id, lists[i]]))
    loaded.value = true
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

watch(
  expanded,
  (isOpen) => {
    if (isOpen && !loaded.value) load()
  },
  { immediate: true },
)
watch(
  () => props.refreshKey,
  () => {
    loaded.value = false
    if (expanded.value) load()
  },
)

function openSheet(value) {
  formError.value = null
  sheet.value = value
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

const updateStorage = (payload) =>
  save(async () => emit('changed', await api.patch(iri('storage', props.storage.id), payload)))

const deleteStorage = () =>
  save(async () => {
    await api.delete(iri('storage', props.storage.id))
    emit('deleted', props.storage)
  })

const createCategory = (payload) =>
  save(async () => {
    await api.post('/categories', { ...payload, storage: iri('storage', props.storage.id) })
    await load()
    emit('changed')
  })

const updateCategory = (payload) =>
  save(async () => {
    await api.patch(iri('category', sheet.value.edit.id), payload)
    await load()
  })

const deleteCategory = () =>
  save(async () => {
    await api.delete(iri('category', sheet.value.edit.id))
    await load()
    emit('changed')
  })
</script>

<template>
  <article class="card storage" data-cy="storage">
    <button type="button" class="storage-head" data-cy="storage-toggle" :aria-expanded="expanded" @click="expanded = !expanded">
      <span class="storage-icon" :class="tone"><AppIcon name="box" :size="22" /></span>
      <span class="storage-text">
        <span class="storage-name" data-cy="storage-name">{{ storage.name }}</span>
        <span class="small muted">{{ subtitle }}</span>
        <span v-if="storage.isPersonal || storage.visibility !== 'private'" class="chips storage-chips">
          <span v-if="storage.isPersonal" class="chip chip--wine"><AppIcon name="lock" :size="12" :stroke="2.2" /> Perso</span>
          <span v-else class="chip chip--glaucous"><AppIcon name="eye" :size="12" :stroke="2.2" /> {{ optionLabel(VISIBILITY_OPTIONS, storage.visibility) }}</span>
        </span>
      </span>
      <span class="chevron" :class="{ 'chevron--open': expanded }"><AppIcon name="down" :size="18" :stroke="2.4" /></span>
    </button>

    <div v-if="expanded" class="storage-body">
      <p v-if="loading && !loaded" class="empty-note">Chargement…</p>
      <p v-else-if="error" class="error" role="alert">{{ error }}</p>
      <template v-else>
        <p v-if="!categories.length" class="empty-note">Aucune catégorie : ajoute-en une pour y ranger des objets.</p>

        <section v-for="category in categories" :key="category.id" data-cy="category">
          <div class="category-head">
            <span data-cy="category-name">{{ category.name }}</span>
            <span class="category-actions">
              <button type="button" class="link-btn" @click="emit('add-item', category)">+ Objet</button>
              <button type="button" class="link-btn" :aria-label="`Modifier la catégorie ${category.name}`" @click="openSheet({ edit: category })">
                Modifier
              </button>
            </span>
          </div>
          <p v-if="!itemsByCategory[category.id]?.length" class="empty-note">Aucun objet.</p>
          <RouterLink v-for="item in itemsByCategory[category.id]" :key="item.id" :to="`/items/${item.id}`" class="item-row" data-cy="item-row">
            <span class="item-row-mini" :class="category.type === 'media' ? '' : 'tone-peach'">
              <ItemVisual :item="item" :category-type="category.type" :size="category.type === 'media' ? 44 : 30" />
            </span>
            <span class="item-row-text">
              <span class="item-row-name" data-cy="item-name">{{ item.name }}</span>
              <span class="small muted">{{ category.name }}<template v-if="item.isPersonal"> · Perso</template></span>
            </span>
            <span v-if="item.status !== 'available'" class="chip chip--glaucous">{{ optionLabel(ITEM_STATUS_OPTIONS, item.status) }}</span>
            <span class="item-row-chevron"><AppIcon name="next" :size="16" :stroke="2.2" /></span>
          </RouterLink>
        </section>

        <div class="storage-actions">
          <button type="button" class="btn btn--soft btn--small" @click="openSheet('category-create')">
            <AppIcon name="plus" :size="14" :stroke="2.4" /> Catégorie
          </button>
          <button type="button" class="btn btn--soft btn--small" @click="openSheet('storage-edit')">
            <AppIcon name="edit" :size="14" /> Rangement
          </button>
        </div>
      </template>
    </div>

    <BottomSheet v-if="sheet === 'storage-edit'" title="Modifier le rangement" @close="sheet = null">
      <NodeForm
        :fields="LEVELS.storage.fields"
        :initial="storage"
        :saving="saving"
        :error="formError"
        deletable
        delete-label="Supprimer ce rangement"
        delete-warning="Ses catégories et tous les objets qu'il contient seront supprimés."
        @submit="updateStorage"
        @delete="deleteStorage"
      />
    </BottomSheet>

    <BottomSheet v-if="sheet === 'category-create'" :title="`Nouvelle catégorie`" @close="sheet = null">
      <NodeForm :fields="LEVELS.category.fields" :saving="saving" :error="formError" submit-label="Créer" @submit="createCategory" />
    </BottomSheet>

    <BottomSheet v-if="sheet?.edit" title="Modifier la catégorie" @close="sheet = null">
      <NodeForm
        :fields="LEVELS.category.fields"
        :initial="sheet.edit"
        :saving="saving"
        :error="formError"
        deletable
        delete-label="Supprimer cette catégorie"
        delete-warning="Tous les objets de cette catégorie seront supprimés."
        @submit="updateCategory"
        @delete="deleteCategory"
      />
    </BottomSheet>
  </article>
</template>
