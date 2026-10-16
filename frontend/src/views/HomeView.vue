<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import BottomSheet from '../components/BottomSheet.vue'
import NodeForm from '../components/NodeForm.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { LEVELS, iri, plural, roomIcon } from '../hierarchy'
import { useAuthStore } from '../stores/auth'
import { usePlacesStore } from '../stores/places'

const auth = useAuthStore()
const placesStore = usePlacesStore()
const router = useRouter()

const rooms = ref([])
const loading = ref(true)
const error = ref('')
const search = ref('')

// Panneau ouvert : 'switch', 'place-create', 'place-edit' ou 'room-create'
const sheet = ref(null)
const saving = ref(false)
const formError = ref(null)

const place = computed(() => placesStore.current)

async function loadPlaces() {
  loading.value = true
  error.value = ''
  try {
    await placesStore.load()
  } catch (e) {
    error.value = e.message
    loading.value = false
  }
}

async function loadRooms() {
  if (!place.value) {
    rooms.value = []
    loading.value = false
    return
  }
  loading.value = true
  try {
    rooms.value = await api.list(`/places/${place.value.id}/rooms`)
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

// Les pièces suivent le logement sélectionné, dès que la liste des logements est connue
loadPlaces()
watch(
  [() => placesStore.loaded, () => place.value?.id],
  ([loaded]) => loaded && loadRooms(),
  { immediate: true },
)

function openSheet(name) {
  formError.value = null
  sheet.value = name
}

function searchItems() {
  router.push({ path: '/dressing', query: search.value ? { q: search.value } : {} })
}

// Enregistre via l'API puis ferme le panneau, en gardant l'erreur affichée en cas d'échec
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

const createPlace = (payload) =>
  save(async () => {
    const created = await api.post('/places', payload)
    placesStore.upsert(created)
    placesStore.select(created.id)
  })

const updatePlace = (payload) =>
  save(async () => placesStore.upsert(await api.patch(iri('place', place.value.id), payload)))

const deletePlace = () =>
  save(async () => {
    await api.delete(iri('place', place.value.id))
    placesStore.remove(place.value.id)
  })

const createRoom = (payload) =>
  save(async () => {
    const created = await api.post('/rooms', { ...payload, place: iri('place', place.value.id) })
    router.push(`/rooms/${created.id}`)
  })
</script>

<template>
  <main class="screen">
    <!-- Titre de la page pour les lecteurs d'écran (le design n'affiche pas de titre principal) -->
    <h1 class="visually-hidden">Accueil</h1>
    <header class="home-header">
      <RouterLink to="/profile" aria-label="Mon profil" class="home-avatar">
        <UserAvatar :name="auth.user?.username ?? ''" :src="auth.user?.avatar" :size="48" />
      </RouterLink>
      <div class="home-greeting">
        <span class="muted">Bonjour</span>
        <span class="display home-name" data-cy="home-username">{{ auth.user?.username }}</span>
      </div>
      <form role="search" class="home-search" @submit.prevent="searchItems">
        <label for="home-search" class="visually-hidden">Rechercher un objet</label>
        <span class="input-wrap">
          <span class="input-icon input-icon--search"><AppIcon name="search" :stroke="2.2" /></span>
          <input
            id="home-search"
            v-model.trim="search"
            class="input input--pill input--search"
            type="search"
            placeholder="Où est mon pull bleu ?"
          />
        </span>
      </form>
      <RouterLink
        to="/profile"
        class="icon-btn icon-btn--solid icon-btn--badge"
        :aria-label="auth.user?.pendingRequestCount ? `Demandes en attente : ${auth.user.pendingRequestCount}` : 'Notifications'"
      >
        <AppIcon name="bell" />
        <span v-if="auth.user?.pendingRequestCount" class="badge-count">{{ auth.user.pendingRequestCount }}</span>
      </RouterLink>
    </header>

    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <section v-if="placesStore.loaded && !placesStore.places.length" class="empty-state">
      <p><strong>Bienvenue !</strong> Commence par ajouter ton logement.</p>
      <button type="button" class="btn btn--primary" @click="openSheet('place-create')">
        <AppIcon name="plus" :stroke="2.4" /> Ajouter un logement
      </button>
    </section>

    <section v-else-if="place" class="stack">
      <div class="section-head">
        <h2 class="section-title" data-cy="place-name">{{ place.name }}</h2>
        <button type="button" class="btn btn--soft btn--small" @click="openSheet('switch')">
          Changer <AppIcon name="down" :size="14" :stroke="2.4" />
        </button>
      </div>

      <p v-if="loading" class="loading">Chargement…</p>
      <div v-else class="tile-grid">
        <RouterLink
          v-for="(room, index) in rooms"
          :key="room.id"
          :to="`/rooms/${room.id}`"
          class="room-tile"
          data-cy="room-tile"
          :class="{ 'room-tile--featured': index === 0 }"
        >
          <span class="room-tile-icon"><AppIcon :name="roomIcon(room.name)" /></span>
          <span class="room-tile-text">
            <span class="room-tile-name">{{ room.name }}</span>
            <span class="room-tile-meta">{{ plural(room.itemCount ?? 0, 'objet') }}</span>
          </span>
        </RouterLink>
        <button type="button" class="add-tile" @click="openSheet('room-create')">
          <AppIcon name="plus" :size="22" :stroke="2.4" />
          Ajouter une pièce
        </button>
      </div>
    </section>

    <BottomSheet v-if="sheet === 'switch'" title="Mes logements" @close="sheet = null">
      <ul class="sheet-list">
        <li v-for="item in placesStore.places" :key="item.id">
          <button
            type="button"
            class="sheet-option"
            :aria-current="item.id === place?.id ? 'true' : undefined"
            @click="placesStore.select(item.id); sheet = null"
          >
            <span>{{ item.name }}<span v-if="item.city" class="small sheet-option-sub"> · {{ item.city }}</span></span>
            <span class="small">{{ plural(item.roomCount ?? 0, 'pièce') }}</span>
          </button>
        </li>
      </ul>
      <button type="button" class="btn btn--block" @click="openSheet('place-edit')">
        <AppIcon name="edit" /> Modifier « {{ place?.name }} »
      </button>
      <button type="button" class="btn btn--soft btn--block" @click="openSheet('place-create')">
        <AppIcon name="plus" :stroke="2.4" /> Ajouter un logement
      </button>
    </BottomSheet>

    <BottomSheet v-if="sheet === 'place-create'" title="Nouveau logement" @close="sheet = null">
      <NodeForm :fields="LEVELS.place.fields" :saving="saving" :error="formError" submit-label="Créer" @submit="createPlace" />
    </BottomSheet>

    <BottomSheet v-if="sheet === 'place-edit'" title="Modifier le logement" @close="sheet = null">
      <NodeForm
        :fields="LEVELS.place.fields"
        :initial="place"
        :saving="saving"
        :error="formError"
        deletable
        delete-label="Supprimer ce logement"
        delete-warning="Toutes les pièces, tous les rangements et tous les objets de ce logement seront supprimés."
        @submit="updatePlace"
        @delete="deletePlace"
      />
    </BottomSheet>

    <BottomSheet v-if="sheet === 'room-create'" title="Nouvelle pièce" @close="sheet = null">
      <NodeForm :fields="LEVELS.room.fields" :saving="saving" :error="formError" submit-label="Créer" @submit="createRoom" />
    </BottomSheet>
  </main>
</template>
