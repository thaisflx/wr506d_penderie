<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, getToken } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import { useAuthStore } from '../stores/auth'

// « Mon espace » (docs/decisions.md) : chiffres sur mon dressing, langue du compte, export de mes données (RGPD).
// Textes en français pour l'instant : ils passeront dans vue-i18n avec le commit front de l'internationalisation.
const router = useRouter()
const auth = useAuthStore()

const LANGUAGES = [
  { value: 'fr', label: 'Français' },
  { value: 'en', label: 'English' },
  { value: 'de', label: 'Deutsch' },
]

const stats = ref(null)
const loading = ref(true)
const error = ref('')
const locale = ref(auth.user?.locale ?? 'fr')
const localeSaved = ref(false)
const exporting = ref(false)

async function load() {
  try {
    const [me, data] = await Promise.all([auth.fetchMe(), api.get('/me/stats')])
    locale.value = me?.locale ?? auth.user?.locale ?? 'fr'
    stats.value = data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
load()

const widthOf = (count, rows) => `${Math.round((100 * count) / Math.max(1, ...rows.map((row) => row.count)))}%`

async function changeLocale() {
  localeSaved.value = false
  error.value = ''
  try {
    await api.patch('/me', { locale: locale.value })
    await auth.fetchMe()
    localeSaved.value = true
  } catch (e) {
    error.value = e.message
  }
}

// Fichier JSON avec toutes mes données : demandé avec le jeton, puis enregistré par le navigateur
async function downloadExport() {
  exporting.value = true
  error.value = ''
  try {
    const response = await fetch('/api/me/export', { headers: { Authorization: `Bearer ${getToken()}` } })
    if (!response.ok) throw new Error(`Erreur ${response.status}`)
    const name = /filename="?([^";]+)/.exec(response.headers.get('Content-Disposition') ?? '')?.[1] ?? 'lendly-mes-donnees.json'
    const url = URL.createObjectURL(await response.blob())
    const link = Object.assign(document.createElement('a'), { href: url, download: name })
    document.body.append(link)
    link.click()
    link.remove()
    URL.revokeObjectURL(url)
  } catch (e) {
    error.value = e.message
  } finally {
    exporting.value = false
  }
}
</script>

<template>
  <main class="screen my-space" data-cy="my-space">
    <div class="page-head">
      <button type="button" class="icon-btn" aria-label="Retour" @click="router.back()">
        <AppIcon name="back" :stroke="2.4" />
      </button>
    </div>
    <div class="page-intro">
      <h1 class="display page-title">Mon espace</h1>
      <p class="page-intro-text">Ce que contient ton dressing, ce que tu portes, et tes données.</p>
    </div>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="loading" class="loading">Chargement…</p>

    <template v-else-if="stats">
      <div class="stat-grid">
        <div class="card stat" data-cy="space-items">
          <span class="display stat-number">{{ stats.itemCount }}</span>
          <span class="stat-label">{{ stats.itemCount > 1 ? 'objets' : 'objet' }}</span>
        </div>
        <div class="card stat" data-cy="space-looks">
          <span class="display stat-number">{{ stats.publishedLooks }}</span>
          <span class="stat-label">{{ stats.publishedLooks > 1 ? 'looks publiés' : 'look publié' }}</span>
        </div>
        <div class="card stat" data-cy="space-followers">
          <span class="display stat-number">{{ stats.followers }}</span>
          <span class="stat-label">{{ stats.followers > 1 ? 'abonnés' : 'abonné' }}</span>
        </div>
        <div class="card stat" data-cy="space-following">
          <span class="display stat-number">{{ stats.following }}</span>
          <span class="stat-label">{{ stats.following > 1 ? 'abonnements' : 'abonnement' }}</span>
        </div>
      </div>

      <section class="stack" aria-labelledby="space-places">
        <h2 id="space-places" class="section-title">par logement</h2>
        <ul class="space-bars" data-cy="space-places">
          <li v-for="place in stats.itemsByPlace" :key="place.id">
            <span class="space-bars-label">{{ place.name }}</span>
            <span class="space-bar" :style="{ width: widthOf(place.count, stats.itemsByPlace) }"></span>
            <span class="space-bars-count">{{ place.count }}</span>
          </li>
        </ul>
      </section>

      <section class="stack" aria-labelledby="space-categories">
        <h2 id="space-categories" class="section-title">par catégorie</h2>
        <ul class="space-bars" data-cy="space-categories">
          <li v-for="category in stats.itemsByCategory" :key="category.id">
            <span class="space-bars-label">{{ category.name }} <span class="small muted">· {{ category.location }}</span></span>
            <span class="space-bar" :style="{ width: widthOf(category.count, stats.itemsByCategory) }"></span>
            <span class="space-bars-count">{{ category.count }}</span>
          </li>
        </ul>
      </section>

      <section class="stack" aria-labelledby="space-most-worn">
        <h2 id="space-most-worn" class="section-title">les plus portés</h2>
        <p v-if="!stats.mostWorn.length" class="small muted">Aucun objet porté dans un look publié pour l'instant.</p>
        <ol v-else class="space-list" data-cy="space-most-worn">
          <li v-for="item in stats.mostWorn" :key="item.id">
            <RouterLink :to="`/items/${item.id}`">{{ item.name }}</RouterLink>
            <span class="small muted">{{ item.count }} look{{ item.count > 1 ? 's' : '' }}</span>
          </li>
        </ol>
      </section>

      <section class="stack" aria-labelledby="space-never-worn">
        <h2 id="space-never-worn" class="section-title">jamais portés · {{ stats.neverWorn.length }}</h2>
        <p class="small muted">Objets qui n'apparaissent dans aucun look publié.</p>
        <ul class="pills" data-cy="space-never-worn">
          <li v-for="item in stats.neverWorn" :key="item.id">
            <RouterLink :to="`/items/${item.id}`" class="pill">{{ item.name }}</RouterLink>
          </li>
        </ul>
      </section>

      <section class="card space-settings stack" aria-labelledby="space-language">
        <h2 id="space-language" class="section-title">langue</h2>
        <label class="field">
          <span class="field-label">Langue de l'application</span>
          <select v-model="locale" class="input" data-cy="space-locale" @change="changeLocale">
            <option v-for="language in LANGUAGES" :key="language.value" :value="language.value">{{ language.label }}</option>
          </select>
        </label>
        <p v-if="localeSaved" class="small muted" role="status" data-cy="space-locale-saved">Langue enregistrée sur ton compte.</p>
      </section>

      <section class="card space-settings stack" aria-labelledby="space-data">
        <h2 id="space-data" class="section-title">mes données</h2>
        <p class="small muted">Un fichier avec tout ce que Lendly garde sur toi : profil, logements, objets, looks et relations.</p>
        <button type="button" class="btn btn--primary" :disabled="exporting" data-cy="space-export" @click="downloadExport">
          {{ exporting ? 'Préparation…' : 'Télécharger mes données' }}
        </button>
      </section>
    </template>
  </main>
</template>
