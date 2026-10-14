<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import ItemTile from '../components/ItemTile.vue'
import { plural, toneFor } from '../hierarchy'

// Tous mes objets, avec recherche (aussi utilisée depuis l'accueil) et filtres par statut
const route = useRoute()
const router = useRouter()

const items = ref([])
const loading = ref(true)
const error = ref('')
const search = ref(route.query.q ?? '')
const filter = ref('all')

const FILTERS = [
  { value: 'all', label: 'Tout' },
  { value: 'for_sale', label: 'En vente' },
  { value: 'lent', label: 'Prêtés' },
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
      (!query || normalize(`${item.name} ${item.categoryName}`).includes(query)),
  )
})
</script>

<template>
  <main class="screen">
    <h1 class="display page-title">Mon dressing</h1>

    <div role="search">
      <label for="dressing-search" class="visually-hidden">Rechercher un objet</label>
      <span class="input-wrap">
        <span class="input-icon input-icon--search"><AppIcon name="search" :stroke="2.2" /></span>
        <input
          id="dressing-search"
          v-model.trim="search"
          class="input input--pill input--search"
          type="search"
          placeholder="Où est mon pull bleu ?"
        />
      </span>
    </div>

    <div class="segmented" role="tablist" aria-label="Filtrer les objets">
      <button
        v-for="option in FILTERS"
        :key="option.value"
        type="button"
        role="tab"
        :aria-selected="filter === option.value"
        @click="filter = option.value"
      >
        {{ option.label }}
      </button>
    </div>

    <p v-if="loading" class="loading">Chargement…</p>
    <p v-else-if="error" class="error" role="alert">{{ error }}</p>
    <template v-else>
      <p class="small muted" aria-live="polite">{{ plural(visibleItems.length, 'objet') }}</p>
      <div v-if="visibleItems.length" class="item-grid">
        <ItemTile v-for="(item, index) in visibleItems" :key="item.id" :item="item" :tone="toneFor(index)" />
      </div>
      <div v-else class="empty-state">
        <p v-if="search">Aucun objet ne correspond à « {{ search }} ».</p>
        <p v-else>Aucun objet ici pour l'instant.</p>
      </div>
    </template>
  </main>
</template>
