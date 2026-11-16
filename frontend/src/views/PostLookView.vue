<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import CameraOverlay from '../components/CameraOverlay.vue'
import DecoStar from '../components/DecoStar.vue'
import FlowerShape from '../components/FlowerShape.vue'
import ItemVisual from '../components/ItemVisual.vue'
import { itemCategoryLabel } from '../hierarchy'
import { t } from '../i18n'
import { fileToJpegDataUrl } from '../utils/image'
import { formatDate } from '../utils/relativeTime'

// Poster un look du jour (/looks/nouveau) ou reprendre un brouillon (/looks/:id/modifier), d'après Poster.png.
// Photo (caméra ou galerie), légende, date, objets portés parmi mes objets partageables ; Brouillon ou Publier.
const props = defineProps({
  id: { type: String, default: null },
})
const router = useRouter()

const TONES = ['tone-glaucous', 'tone-sky', 'tone-peach']
const today = () => new Date().toLocaleDateString('sv-SE') // AAAA-MM-JJ, à l'heure locale

const loading = ref(true)
const loadError = ref('')
const caption = ref('')
const wornOn = ref(today())
// Photo affichée : URL de la photo enregistrée, ou nouvelle photo en base64 pas encore envoyée
const photo = ref(null)
const newPhoto = ref(null)
const selected = ref([])
const items = ref([])
const search = ref('')
const cameraOpen = ref(false)
const galleryInput = ref(null)
const photoError = ref('')
const saving = ref(false)
const saveError = ref('')
// Look en cours d'édition : le brouillon repris, ou celui créé par un premier enregistrement
const ootdId = ref(props.id)

async function load() {
  try {
    const [list, ootd] = await Promise.all([api.list('/items'), props.id ? api.get(`/ootds/${props.id}`) : null])
    // Seuls les objets que le look peut montrer : ni perso, ni rangés dans un endroit privé ou perso
    items.value = list.filter((item) => item.shareable)
    if (ootd) {
      if (ootd.published) {
        router.replace('/')
        return
      }
      caption.value = ootd.caption ?? ''
      wornOn.value = ootd.wornOn
      photo.value = ootd.photo
      selected.value = ootd.wornItems.map((item) => item.id)
    }
  } catch (e) {
    loadError.value = e.status === 404 ? t('post.not_found') : e.message
  } finally {
    loading.value = false
  }
}
load()

function normalize(text) {
  return (text ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
}

// Objets choisis en tête, puis les autres ; la recherche porte sur le nom et la catégorie
const shownItems = computed(() => {
  const query = normalize(search.value)
  const matches = items.value.filter((item) => !query || normalize(`${item.name} ${item.categoryName} ${itemCategoryLabel(item)}`).includes(query))
  return [...matches.filter((i) => selected.value.includes(i.id)), ...matches.filter((i) => !selected.value.includes(i.id))]
})

function toggle(item) {
  selected.value = selected.value.includes(item.id) ? selected.value.filter((id) => id !== item.id) : [...selected.value, item.id]
}

const dateLabel = computed(() => {
  const date = new Date(`${wornOn.value}T12:00:00`)
  const label = formatDate(date, { day: 'numeric', month: 'short' })
  return wornOn.value === today() ? t('post.today', { date: label }) : label
})

function onPhoto(dataUrl) {
  cameraOpen.value = false
  photo.value = dataUrl
  newPhoto.value = dataUrl
  photoError.value = ''
}

async function onFileChosen(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  photoError.value = ''
  try {
    onPhoto(await fileToJpegDataUrl(file))
  } catch (e) {
    photoError.value = e.message
  }
}

// Enregistre (création ou mise à jour), envoie la nouvelle photo, puis publie si demandé
async function save(publish) {
  if (publish && !photo.value) {
    saveError.value = t('post.photo_required')
    return
  }
  saving.value = true
  saveError.value = ''
  try {
    const body = { caption: caption.value.trim() || null, wornOn: wornOn.value, items: selected.value.map((id) => `/api/items/${id}`) }
    // Après une première création, on met à jour ce look : un nouvel essai ne crée pas un deuxième brouillon
    const ootd = ootdId.value ? await api.patch(`/ootds/${ootdId.value}`, body) : await api.post('/ootds', body)
    ootdId.value = ootd.id
    if (newPhoto.value) {
      await api.post(`/ootds/${ootd.id}/photo`, { image: newPhoto.value })
      newPhoto.value = null
    }
    if (publish) {
      await api.patch(`/ootds/${ootd.id}`, { published: true })
      router.push('/')
    } else {
      router.push('/profile')
    }
  } catch (e) {
    saveError.value = e.violations?.map((v) => v.message).join(' ') || e.message
  } finally {
    saving.value = false
  }
}

async function removeDraft() {
  saving.value = true
  try {
    await api.delete(`/ootds/${props.id}`)
    router.push('/profile')
  } catch (e) {
    saveError.value = e.message
    saving.value = false
  }
}

function close() {
  if (window.history.state?.back) router.back()
  else router.push('/')
}
</script>

<template>
  <main class="screen post-look" data-cy="post-look">
    <div class="post-look-bar">
      <button type="button" class="icon-btn" :aria-label="$t('common.close')" data-cy="post-look-close" @click="close">
        <AppIcon name="close" :stroke="2.4" />
      </button>
      <label class="chip chip--sky post-look-date">
        <span>{{ dateLabel }}</span>
        <input v-model="wornOn" type="date" :max="today()" class="post-look-date-input" :aria-label="$t('post.date_label')" data-cy="post-look-date" />
      </label>
    </div>

    <h1 class="display page-title">{{ id ? $t('post.title_draft') : $t('post.title_new') }}</h1>

    <p v-if="loading" class="loading">{{ $t('common.loading') }}</p>
    <p v-else-if="loadError" class="error" role="alert">{{ loadError }}</p>
    <template v-else>
      <!-- Photo dans la forme fleur -->
      <div class="post-look-photo">
        <img v-if="photo" :src="photo" :alt="$t('post.photo_alt')" class="flower-mask post-look-photo-img" data-cy="post-look-photo" />
        <button v-else type="button" class="post-look-photo-empty" data-cy="post-look-camera" @click="cameraOpen = true">
          <FlowerShape fill="var(--rose-soft)" />
          <span class="post-look-photo-label">
            <AppIcon name="camera" :size="34" :stroke="2" />
            {{ $t('post.take_photo') }}
          </span>
        </button>
        <DecoStar class="post-look-star" :size="22" color="var(--violet)" />
      </div>
      <div class="post-look-photo-actions">
        <button v-if="photo" type="button" class="btn btn--soft btn--small" @click="cameraOpen = true">
          <AppIcon name="camera" :size="14" /> {{ $t('post.retake') }}
        </button>
        <button type="button" class="btn btn--soft btn--small" data-cy="post-look-gallery" @click="galleryInput.click()">
          <AppIcon name="image" :size="14" /> {{ $t('post.gallery') }}
        </button>
        <input ref="galleryInput" type="file" accept="image/*,.heic,.heif" class="visually-hidden" tabindex="-1" data-cy="post-look-file" @change="onFileChosen" />
      </div>
      <p v-if="photoError" class="error" role="alert">{{ photoError }}</p>

      <label class="field">
        <span class="section-title post-look-label">{{ $t('post.caption') }}</span>
        <textarea v-model="caption" class="input input--area" rows="2" maxlength="500" :placeholder="$t('post.caption_placeholder')" data-cy="post-look-caption"></textarea>
      </label>

      <section class="stack" aria-labelledby="worn-title">
        <div class="section-head">
          <h2 id="worn-title" class="section-title">{{ $t('look.what_i_wear') }}</h2>
          <span class="text-link post-look-count" data-cy="post-look-count">{{ $t('post.chosen', selected.length) }}</span>
        </div>
        <p class="small muted">{{ $t('post.shareable_only') }}</p>
        <div role="search">
          <label for="post-look-search" class="visually-hidden">{{ $t('post.search_label') }}</label>
          <span class="input-wrap">
            <span class="input-icon"><AppIcon name="search" :size="18" :stroke="2.2" /></span>
            <input id="post-look-search" v-model="search" class="input input--search" type="search" :placeholder="$t('post.search_placeholder')" data-cy="post-look-search" />
          </span>
        </div>
        <p v-if="!items.length" class="small muted">{{ $t('post.no_shareable') }}</p>
        <p v-else-if="!shownItems.length" class="small muted">{{ $t('post.no_match') }}</p>
        <div v-else class="pick-grid">
          <button
            v-for="(item, index) in shownItems"
            :key="item.id"
            type="button"
            class="pick-card"
            :aria-pressed="selected.includes(item.id)"
            data-cy="pick-item"
            @click="toggle(item)"
          >
            <span class="pick-card-media scallop" :class="item.photo ? '' : TONES[index % TONES.length]">
              <ItemVisual :item="item" :category-type="item.categoryType" :size="36" />
              <span v-if="selected.includes(item.id)" class="pick-card-check" aria-hidden="true"><AppIcon name="check" :size="14" :stroke="3" /></span>
            </span>
            <span class="pick-card-name">{{ item.name }}</span>
          </button>
        </div>
      </section>

      <button v-if="id" type="button" class="link-btn link-btn--danger" :disabled="saving" data-cy="post-look-delete" @click="removeDraft">{{ $t('post.delete_draft') }}</button>
      <p v-if="saveError" class="error" role="alert" data-cy="post-look-error">{{ saveError }}</p>
    </template>

    <div v-if="!loading && !loadError" class="item-actions post-look-actions">
      <button type="button" class="btn btn--large" :disabled="saving" data-cy="post-look-draft" @click="save(false)">{{ $t('post.draft') }}</button>
      <button type="button" class="btn btn--primary btn--large" :disabled="saving" data-cy="post-look-publish" @click="save(true)">
        {{ saving ? $t('post.sending') : $t('post.publish') }}
      </button>
    </div>

    <CameraOverlay v-if="cameraOpen" @photo="onPhoto" @close="cameraOpen = false" />
  </main>
</template>
