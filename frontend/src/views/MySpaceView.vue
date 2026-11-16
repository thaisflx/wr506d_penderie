<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api, getToken } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import { categoryLabel } from '../hierarchy'
import { LOCALES, currentLocale, t } from '../i18n'
import { useAuthStore } from '../stores/auth'

// « Mon espace » (docs/decisions.md) : chiffres sur mon dressing, langue du compte, export de mes données (RGPD).
const router = useRouter()
const auth = useAuthStore()

const stats = ref(null)
const loading = ref(true)
const error = ref('')
const locale = ref(currentLocale())
const localeSaved = ref(false)
const exporting = ref(false)

async function load() {
  try {
    const [me, data] = await Promise.all([auth.fetchMe(), api.get('/me/stats')])
    locale.value = me?.locale ?? currentLocale()
    stats.value = data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
load()

const widthOf = (count, rows) => `${Math.round((100 * count) / Math.max(1, ...rows.map((row) => row.count)))}%`

// Langue du compte : enregistrée par l'API (PATCH /api/me), puis appliquée à l'interface par fetchMe()
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
    if (!response.ok) throw new Error(t('error.http', { status: response.status }))
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
      <button type="button" class="icon-btn" :aria-label="$t('common.back')" @click="router.back()">
        <AppIcon name="back" :stroke="2.4" />
      </button>
    </div>
    <div class="page-intro">
      <h1 class="display page-title">{{ $t('space.title') }}</h1>
      <p class="page-intro-text">{{ $t('space.intro') }}</p>
    </div>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="loading" class="loading">{{ $t('common.loading') }}</p>

    <template v-else-if="stats">
      <div class="stat-grid">
        <div class="card stat" data-cy="space-items">
          <span class="display stat-number">{{ stats.itemCount }}</span>
          <span class="stat-label">{{ $t('profile.stat_items', stats.itemCount) }}</span>
        </div>
        <div class="card stat" data-cy="space-looks">
          <span class="display stat-number">{{ stats.publishedLooks }}</span>
          <span class="stat-label">{{ $t('space.stat_looks', stats.publishedLooks) }}</span>
        </div>
        <div class="card stat" data-cy="space-followers">
          <span class="display stat-number">{{ stats.followers }}</span>
          <span class="stat-label">{{ $t('profile.stat_followers', stats.followers) }}</span>
        </div>
        <div class="card stat" data-cy="space-following">
          <span class="display stat-number">{{ stats.following }}</span>
          <span class="stat-label">{{ $t('space.stat_following', stats.following) }}</span>
        </div>
      </div>

      <section class="stack" aria-labelledby="space-places">
        <h2 id="space-places" class="section-title">{{ $t('space.by_place') }}</h2>
        <ul class="space-bars" data-cy="space-places">
          <li v-for="place in stats.itemsByPlace" :key="place.id">
            <span class="space-bars-label">{{ place.name }}</span>
            <span class="space-bar" :style="{ width: widthOf(place.count, stats.itemsByPlace) }"></span>
            <span class="space-bars-count">{{ place.count }}</span>
          </li>
        </ul>
      </section>

      <section class="stack" aria-labelledby="space-categories">
        <h2 id="space-categories" class="section-title">{{ $t('space.by_category') }}</h2>
        <ul class="space-bars" data-cy="space-categories">
          <li v-for="category in stats.itemsByCategory" :key="category.id">
            <span class="space-bars-label">{{ categoryLabel(category) }} <span class="small muted">· {{ category.location }}</span></span>
            <span class="space-bar" :style="{ width: widthOf(category.count, stats.itemsByCategory) }"></span>
            <span class="space-bars-count">{{ category.count }}</span>
          </li>
        </ul>
      </section>

      <section class="stack" aria-labelledby="space-most-worn">
        <h2 id="space-most-worn" class="section-title">{{ $t('space.most_worn') }}</h2>
        <p v-if="!stats.mostWorn.length" class="small muted">{{ $t('space.no_worn') }}</p>
        <ol v-else class="space-list" data-cy="space-most-worn">
          <li v-for="item in stats.mostWorn" :key="item.id">
            <RouterLink :to="`/items/${item.id}`">{{ item.name }}</RouterLink>
            <span class="small muted">{{ $t('space.worn_count', { n: item.count }, item.count) }}</span>
          </li>
        </ol>
      </section>

      <section class="stack" aria-labelledby="space-never-worn">
        <h2 id="space-never-worn" class="section-title">{{ $t('space.never_worn', { n: stats.neverWorn.length }) }}</h2>
        <p class="small muted">{{ $t('space.never_worn_help') }}</p>
        <ul class="pills" data-cy="space-never-worn">
          <li v-for="item in stats.neverWorn" :key="item.id">
            <RouterLink :to="`/items/${item.id}`" class="pill">{{ item.name }}</RouterLink>
          </li>
        </ul>
      </section>

      <section class="card space-settings stack" aria-labelledby="space-language">
        <h2 id="space-language" class="section-title">{{ $t('space.language') }}</h2>
        <label class="field">
          <span class="field-label">{{ $t('profile.language') }}</span>
          <select v-model="locale" class="input" data-cy="space-locale" @change="changeLocale">
            <option v-for="code in LOCALES" :key="code" :value="code" :lang="code">{{ $t(`language.${code}`) }}</option>
          </select>
        </label>
        <p v-if="localeSaved" class="small muted" role="status" data-cy="space-locale-saved">{{ $t('profile.language_saved') }}</p>
      </section>

      <section class="card space-settings stack" aria-labelledby="space-data">
        <h2 id="space-data" class="section-title">{{ $t('space.data') }}</h2>
        <p class="small muted">{{ $t('space.data_help') }}</p>
        <button type="button" class="btn btn--primary" :disabled="exporting" data-cy="space-export" @click="downloadExport">
          {{ exporting ? $t('space.exporting') : $t('space.export') }}
        </button>
      </section>
    </template>
  </main>
</template>
