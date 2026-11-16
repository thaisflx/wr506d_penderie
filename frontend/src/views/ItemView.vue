<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import BottomSheet from '../components/BottomSheet.vue'
import DecoStar from '../components/DecoStar.vue'
import FlowerShape from '../components/FlowerShape.vue'
import ItemVisual from '../components/ItemVisual.vue'
import NodeForm from '../components/NodeForm.vue'
import {
  CATEGORY_TYPE_OPTIONS,
  categoryLabel,
  ITEM_STATUS_OPTIONS,
  LEVELS,
  effectiveVisibility,
  iri,
  optionLabel,
} from '../hierarchy'
import { t } from '../i18n'
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
    error.value = e.status === 404 ? t('item.not_found') : e.message
  } finally {
    loading.value = false
  }
}

watch(() => props.id, load, { immediate: true })

const visibility = computed(() =>
  item.value ? effectiveVisibility(item.value, [storage.value, room.value, place.value]) : null,
)

// Puces sous le titre : catégorie, taille et matière quand elles sont connues
const chips = computed(() => {
  if (!item.value) return []
  const attributes = item.value.attributes ?? {}
  return [
    categoryLabel(category.value),
    attributes.size ? t('item.size', { size: attributes.size }) : null,
    attributes.composition,
  ].filter(Boolean)
})

// Informations en grille : champ JSON "attributes" (scan ou saisie), type de catégorie, code-barres, visibilité
const infos = computed(() => {
  if (!item.value) return []
  const attributes = item.value.attributes ?? {}
  return [
    { label: t('item.brand'), value: attributes.brand },
    { label: t('item.color'), value: attributes.color },
    { label: t('item.type'), value: attributes.type ?? optionLabel(CATEGORY_TYPE_OPTIONS, category.value?.type) },
    { label: t('item.barcode'), value: item.value.barcode },
    { label: t('item.visible_by'), value: visibility.value?.value === 'private' ? t('item.only_me') : visibility.value?.label },
  ].filter((info) => info.value)
})

// État : un clic enregistre le nouveau statut (même PATCH que le formulaire « Modifier »)
const statusSaving = ref(false)
const statusError = ref('')

async function setStatus(status) {
  if (status === item.value.status || statusSaving.value) return
  statusSaving.value = true
  statusError.value = ''
  try {
    item.value = await api.patch(iri('item', props.id), { status })
  } catch (e) {
    statusError.value = e.message
  } finally {
    statusSaving.value = false
  }
}

const backLink = computed(() => (room.value ? `/rooms/${room.value.id}` : '/dressing'))

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
    <div class="item-hero">
      <div class="item-bar">
        <RouterLink :to="backLink" class="icon-btn" :aria-label="$t('common.back')">
          <AppIcon name="back" :stroke="2.4" />
        </RouterLink>
        <button v-if="item" type="button" class="icon-btn" :aria-label="$t('item.edit')" @click="editing = true; formError = null">
          <AppIcon name="edit" :size="19" />
        </button>
      </div>

      <!-- Photo principale dans la forme fleur ; sans photo, fleur colorée et pictogramme -->
      <div v-if="item" class="item-flower">
        <img v-if="item.photo" :src="item.photo" :alt="item.name" class="item-flower-photo flower-mask" />
        <template v-else>
          <FlowerShape fill="var(--glaucous-soft)" />
          <span class="item-flower-icon"><ItemVisual :item="item" :category-type="category?.type" :size="96" /></span>
        </template>
        <DecoStar class="item-star item-star--big" :size="26" />
        <DecoStar class="item-star item-star--small" :size="16" color="var(--wine)" />
      </div>

      <div v-if="item" class="item-photo-actions">
        <button type="button" class="btn btn--soft btn--small" :disabled="photoBusy" @click="photoInput.click()">
          <AppIcon name="image" :size="14" />
          {{ photoBusy ? $t('item.sending') : item.photo ? $t('item.change_photo') : $t('item.add_photo') }}
        </button>
        <button v-if="item.photo" type="button" class="btn btn--soft btn--small" :disabled="photoBusy" :aria-label="$t('item.remove_photo')" @click="removePhoto">
          <AppIcon name="trash" :size="14" />
        </button>
        <!-- Sans "capture" : le téléphone propose la galerie ou l'appareil photo -->
        <input ref="photoInput" type="file" accept="image/*,.heic,.heif" hidden @change="onPhotoChosen" />
      </div>
      <p v-if="photoError" class="error item-photo-error" role="alert">{{ photoError }}</p>
    </div>

    <main class="item-details">
      <p v-if="loading" class="loading">{{ $t('common.loading') }}</p>
      <p v-else-if="error" class="error" role="alert">{{ error }}</p>
      <template v-else-if="item">
        <div>
          <nav class="item-place" :aria-label="$t('item.location')">
            <RouterLink to="/dressing">{{ place.name }}</RouterLink> ›
            <RouterLink :to="`/rooms/${room.id}`">{{ room.name }}</RouterLink> ›
            <span>{{ storage.name }}</span>
          </nav>
          <h1 class="display page-title item-title">{{ item.name }}</h1>
          <div v-if="chips.length" class="chips item-chips">
            <span v-for="chip in chips" :key="chip" class="chip chip--sky item-chip">{{ chip }}</span>
          </div>
          <p v-if="item.attributes?.description" class="item-description">{{ item.attributes.description }}</p>
        </div>

        <div class="segmented segmented--wrap" role="group" :aria-label="$t('item.status_group')">
          <button
            v-for="option in ITEM_STATUS_OPTIONS"
            :key="option.value"
            type="button"
            :aria-pressed="item.status === option.value"
            :disabled="statusSaving"
            @click="setStatus(option.value)"
          >
            {{ option.label }}
          </button>
        </div>
        <p v-if="statusError" class="error" role="alert">{{ statusError }}</p>

        <dl class="card item-infos">
          <div v-for="info in infos" :key="info.label" class="item-info">
            <dt>{{ info.label }}</dt>
            <dd>{{ info.value }}</dd>
          </div>
        </dl>
        <p class="small muted">
          {{ $t('item.visibility_note') }}<template v-if="item.attributes?.scannedWith"> {{ $t('item.recognised_by_ai', { model: item.attributes.scannedWith }) }}</template>
        </p>
      </template>
    </main>

    <!-- Prêt et vente : pas encore d'API (entités Sale et Reservation sans endpoint) -->
    <div v-if="item" class="item-actions">
      <p class="item-actions-soon">{{ $t('item.lend_sell_soon') }}</p>
      <span class="btn btn--primary btn--large" role="button" aria-disabled="true" :title="$t('item.coming_soon')">{{ $t('item.lend') }}</span>
      <span class="btn btn--large" role="button" aria-disabled="true" :title="$t('item.coming_soon')">{{ $t('item.sell') }}</span>
    </div>

    <BottomSheet v-if="editing" :title="$t('item.edit')" @close="editing = false">
      <NodeForm
        :fields="LEVELS.item.fields"
        :initial="item"
        :saving="saving"
        :error="formError"
        deletable
        :delete-label="$t('item.delete')"
        @submit="save"
        @delete="remove"
      />
    </BottomSheet>
  </div>
</template>
