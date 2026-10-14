<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import BottomSheet from '../components/BottomSheet.vue'
import ItemVisual from '../components/ItemVisual.vue'
import NodeForm from '../components/NodeForm.vue'
import {
  CATEGORY_TYPE_OPTIONS,
  ITEM_STATUS_OPTIONS,
  LEVELS,
  effectiveVisibility,
  iri,
  optionLabel,
} from '../hierarchy'
import { fileToJpegDataUrl } from '../utils/image'

const props = defineProps({
  id: { type: String, required: true },
})
const router = useRouter()

const item = ref(null)
const category = ref(null)
const storage = ref(null)
const room = ref(null)
const place = ref(null)
const loading = ref(true)
const error = ref('')

const editing = ref(false)
const saving = ref(false)
const formError = ref(null)

// Charge l'objet puis remonte la hiérarchie : catégorie > rangement > pièce > logement
async function load() {
  loading.value = true
  error.value = ''
  try {
    item.value = await api.get(iri('item', props.id))
    category.value = await api.get(item.value.category)
    storage.value = await api.get(category.value.storage)
    room.value = await api.get(storage.value.room)
    place.value = await api.get(room.value.place)
  } catch (e) {
    error.value = e.status === 404 ? 'Cet objet est introuvable.' : e.message
  } finally {
    loading.value = false
  }
}

watch(() => props.id, load, { immediate: true })

const visibility = computed(() =>
  item.value ? effectiveVisibility(item.value, [storage.value, room.value, place.value]) : null,
)

// Informations issues du scan (ou saisies), rangées dans le champ JSON "attributes"
const ATTRIBUTE_LABELS = { type: 'Type', color: 'Couleur', brand: 'Marque', size: 'Taille', composition: 'Composition' }
const attributeList = computed(() =>
  Object.entries(ATTRIBUTE_LABELS)
    .filter(([key]) => item.value?.attributes?.[key])
    .map(([key, label]) => ({ key, label, value: item.value.attributes[key] })),
)

const backLink = computed(() => (room.value ? `/rooms/${room.value.id}` : '/'))

async function save(payload) {
  saving.value = true
  formError.value = null
  try {
    item.value = await api.patch(iri('item', props.id), payload)
    editing.value = false
  } catch (e) {
    formError.value = e
  } finally {
    saving.value = false
  }
}

// Photo : galerie ou appareil photo du téléphone, réduite à 1024 px en JPEG avant l'envoi
const photoInput = ref(null)
const photoBusy = ref(false)
const photoError = ref('')

async function onPhotoChosen(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  photoBusy.value = true
  photoError.value = ''
  try {
    const image = await fileToJpegDataUrl(file)
    const { photo } = await api.post(`/items/${props.id}/photo`, { image })
    item.value = { ...item.value, photo }
  } catch (e) {
    photoError.value = e.message
  } finally {
    photoBusy.value = false
  }
}

async function removePhoto() {
  photoBusy.value = true
  photoError.value = ''
  try {
    await api.delete(`/items/${props.id}/photo`)
    item.value = { ...item.value, photo: null }
  } catch (e) {
    photoError.value = e.message
  } finally {
    photoBusy.value = false
  }
}

async function remove() {
  saving.value = true
  try {
    await api.delete(iri('item', props.id))
    router.push(backLink.value)
  } catch (e) {
    formError.value = e
    saving.value = false
  }
}
</script>

<template>
  <div class="item-page">
    <div class="item-visual tone-glaucous">
      <div class="item-visual-bar">
        <RouterLink :to="backLink" class="icon-btn" aria-label="Retour">
          <AppIcon name="back" :stroke="2.6" />
        </RouterLink>
        <button v-if="item" type="button" class="icon-btn" aria-label="Modifier l'objet" @click="editing = true; formError = null">
          <AppIcon name="edit" :size="19" />
        </button>
      </div>
      <div v-if="item" class="item-visual-art">
        <ItemVisual :item="item" :category-type="category?.type" :size="item.photo ? 220 : 96" />
      </div>
      <div v-if="item" class="item-photo-actions">
        <button type="button" class="chip chip--photo" :disabled="photoBusy" @click="photoInput.click()">
          <AppIcon name="image" :size="14" />
          {{ photoBusy ? 'Envoi…' : item.photo ? 'Changer la photo' : 'Ajouter une photo' }}
        </button>
        <button
          v-if="item.photo"
          type="button"
          class="chip chip--photo"
          :disabled="photoBusy"
          aria-label="Retirer la photo"
          @click="removePhoto"
        >
          <AppIcon name="trash" :size="14" />
        </button>
        <!-- Sans "capture" : le téléphone propose la galerie ou l'appareil photo -->
        <input ref="photoInput" type="file" accept="image/*,.heic,.heif" hidden @change="onPhotoChosen" />
      </div>
      <p v-if="photoError" class="item-photo-error" role="alert">{{ photoError }}</p>
    </div>

    <main class="item-details">
      <p v-if="loading" class="loading">Chargement…</p>
      <p v-else-if="error" class="error" role="alert">{{ error }}</p>
      <template v-else-if="item">
        <div class="item-heading">
          <nav class="breadcrumb-line" aria-label="Emplacement">
            <RouterLink to="/">{{ place.name }}</RouterLink>
            <AppIcon name="next" :size="12" :stroke="2.4" />
            <RouterLink :to="`/rooms/${room.id}`">{{ room.name }}</RouterLink>
            <AppIcon name="next" :size="12" :stroke="2.4" />
            <span>{{ storage.name }}</span>
          </nav>
          <h1 class="display item-title">{{ item.name }}</h1>
          <span class="muted item-subtitle">
            {{ category.name }} · {{ optionLabel(CATEGORY_TYPE_OPTIONS, category.type) }}
          </span>
        </div>

        <div class="stat-grid">
          <div class="card stat">
            <span class="display stat-value stat-value--text">{{ optionLabel(ITEM_STATUS_OPTIONS, item.status) }}</span>
            <span class="stat-label">statut</span>
          </div>
          <div class="card stat">
            <span class="display stat-value stat-value--text">{{ category.name }}</span>
            <span class="stat-label">catégorie</span>
          </div>
          <div class="card stat">
            <span class="display stat-value stat-value--text">{{ item.barcode || '—' }}</span>
            <span class="stat-label">code-barres</span>
          </div>
        </div>

        <div v-if="attributeList.length || item.attributes?.description" class="card attributes-card">
          <p v-if="item.attributes?.description" class="attributes-description">{{ item.attributes.description }}</p>
          <dl v-if="attributeList.length" class="attributes-list">
            <div v-for="attribute in attributeList" :key="attribute.key">
              <dt>{{ attribute.label }}</dt>
              <dd>{{ attribute.value }}</dd>
            </div>
          </dl>
          <p v-if="item.attributes?.scannedWith" class="small muted">Reconnu par l'IA ({{ item.attributes.scannedWith }})</p>
        </div>

        <div class="visibility-row">
          <span class="visibility-row-label">Qui peut le voir ?</span>
          <span class="chip" :class="visibility.value === 'personal' || visibility.value === 'private' ? 'chip--wine' : 'chip--glaucous'">
            <AppIcon :name="visibility.value === 'personal' || visibility.value === 'private' ? 'lock' : 'eye'" :size="13" />
            {{ visibility.value === 'private' ? 'Moi seul' : visibility.label }}
          </span>
        </div>
        <p class="small muted visibility-help">
          Calculé à partir de l'objet et de son rangement, de sa pièce et de son logement : le plus fermé l'emporte.
        </p>
      </template>
    </main>

    <BottomSheet v-if="editing" title="Modifier l'objet" @close="editing = false">
      <NodeForm
        :fields="LEVELS.item.fields"
        :initial="item"
        :saving="saving"
        :error="formError"
        deletable
        delete-label="Supprimer cet objet"
        @submit="save"
        @delete="remove"
      />
    </BottomSheet>
  </div>
</template>
