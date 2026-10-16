<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import ItemVisual from '../components/ItemVisual.vue'
import { CATEGORY_TYPE_OPTIONS, iri, optionLabel } from '../hierarchy'
import { fileToJpegDataUrl, toJpegDataUrl } from '../utils/image'

// Scanner : photo -> redimension 1024 px -> JPEG -> base64 -> analyse IA (OpenRouter, via l'API) -> fiche pré-remplie
const router = useRouter()

const MODES = [
  { value: 'photo', label: 'Photo', hint: 'Cadre le vêtement en entier, puis prends la photo' },
  { value: 'label', label: 'Étiquette', hint: "Cadre l'étiquette bien à plat et lisible" },
]
const mode = ref('photo')
const hint = computed(() => MODES.find((m) => m.value === mode.value).hint)

// Caméra
const video = ref(null)
const fileInput = ref(null)
const cameraReady = ref(false)
const cameraError = ref('')
const torchAvailable = ref(false)
const torchOn = ref(false)
let stream = null

// Étapes : 'aim' (viser), 'analyzing', 'result', 'manual'
const step = ref('aim')
const photo = ref(null)
const scanError = ref('')
const model = ref('')
const form = ref({ name: '', color: '', description: '', type: '', brand: '', size: '', composition: '', categoryType: 'other' })

// Emplacement
const categories = ref([])
const categoryIri = ref('')
const saving = ref(false)
const saveError = ref('')

async function startCamera() {
  if (!navigator.mediaDevices?.getUserMedia) {
    cameraError.value = "La caméra n'est pas disponible ici : importe une photo."
    return
  }
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
      audio: false,
    })
    video.value.srcObject = stream
    await video.value.play()
    cameraReady.value = true
    const track = stream.getVideoTracks()[0]
    torchAvailable.value = !!track.getCapabilities?.().torch
  } catch {
    cameraError.value = "Accès à la caméra refusé ou impossible : importe une photo."
  }
}

function stopCamera() {
  stream?.getTracks().forEach((track) => track.stop())
  stream = null
  cameraReady.value = false
}

async function toggleTorch() {
  const track = stream?.getVideoTracks()[0]
  if (!track) return
  torchOn.value = !torchOn.value
  await track.applyConstraints({ advanced: [{ torch: torchOn.value }] }).catch(() => (torchOn.value = false))
}

async function loadCategories() {
  try {
    categories.value = await api.list('/categories')
    // Emplacement par défaut, ajusté ensuite selon le type reconnu par l'IA
    if (!categoryIri.value && categories.value[0]) categoryIri.value = iri('category', categories.value[0].id)
  } catch {
    categories.value = []
  }
}

onMounted(() => {
  startCamera()
  loadCategories()
})
onBeforeUnmount(stopCamera)

function capture() {
  if (!cameraReady.value) return
  analyze(toJpegDataUrl(video.value))
}

async function onFileChosen(event) {
  const file = event.target.files?.[0]
  event.target.value = ''
  if (!file) return
  scanError.value = ''
  step.value = 'analyzing'
  try {
    analyze(await fileToJpegDataUrl(file))
  } catch (e) {
    scanError.value = e.message
    step.value = 'aim'
  }
}

// Préremplit l'emplacement avec une catégorie du même type que l'objet reconnu
function suggestCategory(categoryType) {
  const match = categories.value.find((category) => category.type === categoryType)
  if (match) categoryIri.value = iri('category', match.id)
}

async function analyze(dataUrl) {
  photo.value = dataUrl
  scanError.value = ''
  saveError.value = ''
  step.value = 'analyzing'
  try {
    const result = await api.post('/scan', { image: dataUrl, mode: mode.value })
    if (!result.name) {
      scanError.value = mode.value === 'label' ? "Aucune étiquette lisible : réessaie de plus près." : "Je n'ai rien reconnu : réessaie avec l'objet bien cadré."
      step.value = 'manual'
      return
    }
    model.value = result.model
    form.value = {
      name: result.name ?? '',
      color: result.color ?? '',
      description: result.description ?? '',
      type: result.type ?? '',
      brand: result.brand ?? '',
      size: result.size ?? '',
      composition: result.composition ?? '',
      categoryType: result.categoryType,
    }
    suggestCategory(result.categoryType)
    step.value = 'result'
  } catch (e) {
    scanError.value = e.message
    step.value = 'manual'
  }
}

function enterManually() {
  scanError.value = ''
  step.value = 'manual'
}

function restart() {
  photo.value = null
  scanError.value = ''
  saveError.value = ''
  step.value = 'aim'
}

const subtitle = computed(() =>
  [form.value.type, form.value.color, form.value.brand, form.value.size].filter(Boolean).join(' · ') ||
  optionLabel(CATEGORY_TYPE_OPTIONS, form.value.categoryType),
)

async function save() {
  if (!form.value.name.trim() || !categoryIri.value) return
  saving.value = true
  saveError.value = ''
  try {
    // Les infos de l'IA vont dans le champ JSON "attributes" de l'objet
    const attributes = Object.fromEntries(
      ['type', 'color', 'description', 'brand', 'size', 'composition']
        .map((key) => [key, form.value[key]?.trim()])
        .filter(([, value]) => value),
    )
    if (model.value && step.value === 'result') attributes.scannedWith = model.value

    const item = await api.post('/items', {
      name: form.value.name.trim(),
      category: categoryIri.value,
      status: 'available',
      attributes: Object.keys(attributes).length ? attributes : null,
    })
    // La photo prise pour le scan devient celle de l'objet (pas celle d'une étiquette)
    if (photo.value && mode.value === 'photo') {
      await api.post(`/items/${item.id}/photo`, { image: photo.value }).catch(() => {}) // sans gravité : elle pourra être ajoutée depuis la fiche
    }
    router.push(`/items/${item.id}`)
  } catch (e) {
    saveError.value = e.violations?.map((v) => v.message).join(' ') || e.message
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <div class="scan">
    <!-- Aperçu caméra en fond, ou la photo prise -->
    <video ref="video" class="scan-video" :class="{ 'scan-video--hidden': photo || !cameraReady }" playsinline muted></video>
    <img v-if="photo" :src="photo" alt="Photo prise" class="scan-video scan-photo" />
    <div class="scan-shade" aria-hidden="true"></div>

    <div class="scan-stage">
      <header class="scan-header">
        <RouterLink to="/" class="icon-btn icon-btn--on-dark" aria-label="Fermer le scanner">
          <AppIcon name="close" :stroke="2.4" />
        </RouterLink>
        <h1 class="scan-title">Scanner</h1>
        <button
          v-if="torchAvailable && !photo"
          type="button"
          class="icon-btn icon-btn--on-dark"
          :aria-pressed="torchOn"
          aria-label="Flash"
          @click="toggleTorch"
        >
          <AppIcon name="flash" />
        </button>
        <span v-else class="scan-header-spacer" aria-hidden="true"></span>
      </header>

      <div class="scan-modes" role="tablist" aria-label="Type de scan">
        <button type="button" role="tab" aria-selected="false" aria-disabled="true" title="Bientôt disponible" disabled>
          Code-barres
        </button>
        <button
          v-for="option in MODES"
          :key="option.value"
          type="button"
          role="tab"
          :aria-selected="mode === option.value"
          :disabled="step === 'analyzing'"
          @click="mode = option.value"
        >
          {{ option.label }}
        </button>
      </div>

      <div class="scan-target">
        <div class="scan-frame" :class="{ 'scan-frame--busy': step === 'analyzing' }">
          <svg width="100%" height="100%" viewBox="0 0 250 250" fill="none" stroke="#ffffff" stroke-width="5" stroke-linecap="round" aria-hidden="true">
            <path d="M3 50V25A22 22 0 0 1 25 3h25M200 3h25a22 22 0 0 1 22 22v25M247 200v25a22 22 0 0 1-22 22h-25M50 247H25A22 22 0 0 1 3 225v-25" />
          </svg>
          <span class="scan-line" aria-hidden="true"></span>
          <span v-if="!cameraReady && !photo" class="scan-frame-text">{{ cameraError || 'Ouverture de la caméra…' }}</span>
        </div>
        <p v-if="step === 'aim' || step === 'analyzing'" class="scan-hint" aria-live="polite">
          {{ step === 'analyzing' ? "Analyse en cours… l'IA regarde ta photo" : hint }}
        </p>

        <div v-if="step === 'aim'" class="scan-controls">
          <button type="button" class="scan-side-btn" aria-label="Importer une photo" @click="fileInput.click()">
            <AppIcon name="image" :size="22" />
          </button>
          <button
            type="button"
            class="scan-shutter"
            :disabled="!cameraReady"
            aria-label="Prendre la photo"
            @click="capture"
          ></button>
          <span class="scan-side-btn scan-side-btn--ghost" aria-hidden="true"></span>
        </div>
        <!-- Import : galerie, ou appareil photo natif du téléphone -->
        <input ref="fileInput" type="file" accept="image/*,.heic,.heif" capture="environment" class="visually-hidden" tabindex="-1" @change="onFileChosen" />
      </div>
    </div>

    <section class="scan-panel" aria-live="polite">
      <span class="sheet-grip" aria-hidden="true"></span>

      <!-- Avant la photo -->
      <template v-if="step === 'aim'">
        <p v-if="scanError" class="error" role="alert">{{ scanError }}</p>
        <p class="scan-panel-intro">
          Prends une photo : l'IA reconnaît le type, la couleur et décrit l'objet. Tu pourras tout corriger avant d'ajouter.
        </p>
        <div class="scan-actions">
          <button type="button" class="btn btn--large" @click="enterManually">
            <AppIcon name="keyboard" /> À la main
          </button>
          <button type="button" class="btn btn--primary btn--large scan-actions-main" @click="fileInput.click()">
            Importer une photo
          </button>
        </div>
      </template>

      <!-- Analyse -->
      <div v-else-if="step === 'analyzing'" class="scan-summary">
        <div class="scan-thumb"><img v-if="photo" :src="photo" alt="" /></div>
        <div class="scan-summary-text">
          <span class="chip chip--peach">Analyse…</span>
          <span class="display scan-summary-title">On regarde ça</span>
          <span class="small muted">Ça prend quelques secondes</span>
        </div>
      </div>

      <!-- Résultat (modifiable) ou saisie à la main -->
      <form v-else class="form" @submit.prevent="save">
        <div class="scan-summary">
          <div class="scan-thumb">
            <img v-if="photo" :src="photo" alt="" />
            <ItemVisual v-else :item="{ name: form.name }" :category-type="form.categoryType" :size="44" />
          </div>
          <div class="scan-summary-text">
            <span v-if="step === 'result'" class="chip chip--glaucous"><AppIcon name="check" :size="12" :stroke="3" /> Trouvé</span>
            <span v-else class="chip">À la main</span>
            <span class="display scan-summary-title">{{ form.name || 'Nouvel objet' }}</span>
            <span class="small muted">{{ subtitle }}</span>
          </div>
        </div>

        <p v-if="scanError" class="error" role="alert">{{ scanError }}</p>

        <label class="field">
          <span class="field-label">Nom</span>
          <input v-model="form.name" class="input" type="text" required placeholder="Pull bleu marine" />
        </label>
        <div class="scan-fields-row">
          <label class="field">
            <span class="field-label">Couleur</span>
            <input v-model="form.color" class="input" type="text" placeholder="Bleu marine" />
          </label>
          <label class="field">
            <span class="field-label">Type</span>
            <input v-model="form.type" class="input" type="text" placeholder="Pull" />
          </label>
        </div>
        <label class="field">
          <span class="field-label">Description</span>
          <textarea v-model="form.description" class="input input--area" rows="2" placeholder="Maille côtelée, col rond"></textarea>
        </label>

        <label class="field">
          <span class="field-label">Ranger dans</span>
          <select v-if="categories.length" v-model="categoryIri" class="input" required>
            <option v-for="category in categories" :key="category.id" :value="iri('category', category.id)">
              {{ category.location }} › {{ category.name }}
            </option>
          </select>
          <span v-else class="empty-state">
            Il faut d'abord une catégorie dans un rangement.
            <RouterLink to="/" class="btn btn--soft btn--small">Aller à mes pièces</RouterLink>
          </span>
        </label>

        <p v-if="saveError" class="error" role="alert">{{ saveError }}</p>
        <div class="scan-actions">
          <button type="button" class="btn btn--large" @click="restart">
            <AppIcon name="scan" /> Reprendre
          </button>
          <button type="submit" class="btn btn--primary btn--large scan-actions-main" :disabled="saving || !categories.length">
            {{ saving ? 'Ajout…' : 'Ajouter' }}
          </button>
        </div>
        <p v-if="step === 'result' && model" class="small muted scan-model">Analysé par {{ model }}</p>
      </form>
    </section>
  </div>
</template>
