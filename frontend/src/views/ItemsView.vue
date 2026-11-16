<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import ItemTile from '../components/ItemTile.vue'
import { itemCategoryLabel, toneFor } from '../hierarchy'
import { t } from '../i18n'

// Tous mes objets, avec recherche (aussi utilisée depuis l'accueil) et filtres par statut
const route = useRoute()
const router = useRouter()

const items = ref([])
const loading = ref(true)
const error = ref('')
const search = ref(route.query.q ?? '')
const filter = ref('all')

const FILTERS = [
  { value: 'all', get label() { return t('items.filter_all') } },
  { value: 'for_sale', get label() { return t('items.filter_for_sale') } },
  { value: 'lent', get label() { return t('items.filter_lent') } },
]

api
  .list('/items')
  .then((list) => (items.value = list))
  .catch((e) => (error.value = e.message))
  .finally(() => (loading.value = false))

// Garde la recherche dans l'URL pour pouvoir revenir en arrière
watch(search, (value) => router.replace({ query: value ? { q: value } : {} }))

function normalize(text) {
  return (text ?? '').normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase()
}

const visibleItems = computed(() => {
  const query = normalize(search.value)
  return items.value.filter(
    (item) =>
      (filter.value === 'all' || item.status === filter.value) &&
      (!query || normalize(`${item.name} ${item.categoryName} ${itemCategoryLabel(item)}`).includes(query)),
  )
})
</script>

<template>
  <main class="screen">
    <div class="page-head">
      <RouterLink to="/dressing" class="icon-btn" :aria-label="$t('items.back')" data-cy="items-back">
        <AppIcon name="back" :stroke="2.4" />
      </RouterLink>
    </div>
    <h1 class="display page-title">{{ $t('items.title') }}</h1>

    <div role="search">
      <label for="dressing-search" class="visually-hidden">{{ $t('dressing.search_label') }}</label>
      <span class="input-wrap">
        <span class="input-icon input-icon--search"><AppIcon name="search" :stroke="2.2" /></span>
        <input
          id="dressing-search"
          v-model.trim="search"
          class="input input--search"
          type="search"
          :placeholder="$t('items.search_placeholder')"
        />
      </span>
    </div>

    <div class="pills" role="group" :aria-label="$t('items.filters')">
      <button
        v-for="option in FILTERS"
        :key="option.value"
        type="button"
        class="pill"
        :aria-pressed="filter === option.value"
        @click="filter = option.value"
      >
        {{ option.label }}
      </button>
    </div>

    <p v-if="loading" class="loading">{{ $t('common.loading') }}</p>
    <p v-else-if="error" class="error" role="alert">{{ error }}</p>
    <template v-else>
      <p class="small muted" aria-live="polite">{{ $t('count.items', visibleItems.length) }}</p>
      <div v-if="visibleItems.length" class="item-cards item-grid">
        <ItemTile v-for="(item, index) in visibleItems" :key="item.id" :item="item" :tone="toneFor(index)" />
      </div>
      <div v-else class="empty-state">
        <p v-if="search">Aucun objet ne correspond à « {{ search }} ».</p>
        <p v-else>{{ $t('items.none_here') }}</p>
      </div>
    </template>
  </main>
</template>
