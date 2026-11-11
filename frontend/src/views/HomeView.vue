<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import AppLogo from '../components/AppLogo.vue'
import BottomSheet from '../components/BottomSheet.vue'
import DecoStar from '../components/DecoStar.vue'
import FlowerShape from '../components/FlowerShape.vue'
import NodeForm from '../components/NodeForm.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { LEVELS, VISIBILITY_OPTIONS, iri, optionLabel, plural, roomIcon } from '../hierarchy'
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

// Fonds des cartes de pièce, en alternance (maquette v2)
const TONES = ['tone-sky', 'tone-glaucous', 'tone-peach']

// Puce de visibilité d'une pièce : Perso l'emporte sur la visibilité choisie
const visibilityLabel = (room) => (room.isPersonal ? 'Perso' : optionLabel(VISIBILITY_OPTIONS, room.visibility))

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
  <main class="screen home">
    <header class="home-header">
      <AppLogo class="home-logo" />
      <div class="home-header-actions">
        <RouterLink
          to="/friends"
          class="icon-btn icon-btn--badge"
          data-cy="home-bell"
          :aria-label="auth.user?.pendingRequestCount ? `Demandes en attente : ${auth.user.pendingRequestCount}` : 'Notifications'"
        >
          <AppIcon name="bell" :size="22" />
          <span v-if="auth.user?.pendingRequestCount" class="badge-count" data-cy="home-bell-count">{{ auth.user.pendingRequestCount }}</span>
        </RouterLink>
        <RouterLink to="/profile" aria-label="Mon profil" class="home-avatar">
          <UserAvatar :name="auth.user?.username ?? ''" :src="auth.user?.avatar" :size="44" />
        </RouterLink>
      </div>
    </header>

    <div class="home-hello">
      <h1 class="display page-title">Bonjour <span class="home-name" data-cy="home-username">{{ auth.user?.username }}</span></h1>
      <DecoStar class="home-star" :size="22" />
      <p class="home-tagline">Tout ce que tu possèdes, rangé et à portée de main.</p>
    </div>

    <form role="search" @submit.prevent="searchItems">
      <label for="home-search" class="visually-hidden">Rechercher un objet</label>
      <span class="input-wrap">
        <span class="input-icon"><AppIcon name="search" :size="18" :stroke="2.2" /></span>
        <input
          id="home-search"
          v-model.trim="search"
          class="input input--search"
          type="search"
          placeholder="Un pull, un vinyle, la perceuse…"
        />
      </span>
    </form>

    <!-- Look du jour : l'écran pour poster arrive avec le lot 2 du volet social -->
    <section class="card look-card" aria-labelledby="look-title">
      <div class="look-card-visual">
        <FlowerShape fill="var(--cyan)" />
        <svg class="look-card-icon" width="56" height="56" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round" aria-hidden="true">
          <path d="M17 7l-9 5 3 8 4-2v22h18V18l4 2 3-8-9-5c-1 3-4 5-7 5s-6-2-7-5z" />
        </svg>
        <DecoStar class="look-card-star" :size="16" color="var(--glaucous)" />
      </div>
      <div class="look-card-text">
        <h2 id="look-title" class="section-title">look du jour</h2>
        <p class="look-card-desc">Montre ta tenue à tes amis et retrouve les pièces portées.</p>
        <span class="btn btn--primary btn--small" role="link" aria-disabled="true" title="Bientôt disponible" data-cy="home-post-look">
          <AppIcon name="plus" :size="16" :stroke="2.4" /> Poster mon look
        </span>
      </div>
    </section>

    <p v-if="error" class="error" role="alert">{{ error }}</p>

    <section v-if="placesStore.loaded && !placesStore.places.length" class="empty-state">
      <p><strong>Bienvenue !</strong> Commence par ajouter ton logement.</p>
      <button type="button" class="btn btn--primary" @click="openSheet('place-create')">
        <AppIcon name="plus" :stroke="2.4" /> Ajouter un logement
      </button>
    </section>

    <section v-else-if="place" class="stack" aria-labelledby="rooms-title">
      <div class="section-head">
        <h2 id="rooms-title" class="section-title home-rooms-title">mes pièces</h2>
        <span class="home-rooms-links">
          <!-- Le dressing n'a plus d'entrée dans la barre de navigation (remplacée par Looks) -->
          <RouterLink to="/dressing" class="text-link" data-cy="home-dressing">Mon dressing</RouterLink>
          <button type="button" class="text-link link-reset" :aria-label="`Changer de logement (actuel : ${place.name})`" @click="openSheet('switch')">
            <span data-cy="place-name">{{ place.name }}</span><span aria-hidden="true"> ▾</span>
          </button>
        </span>
      </div>

      <p v-if="loading" class="loading">Chargement…</p>
      <div v-else class="tile-grid">
        <RouterLink v-for="(room, index) in rooms" :key="room.id" :to="`/rooms/${room.id}`" class="card room-tile" data-cy="room-tile">
          <span class="room-tile-media scallop" :class="TONES[index % TONES.length]">
            <span class="chip chip--white room-tile-chip">{{ visibilityLabel(room) }}</span>
            <AppIcon :name="roomIcon(room.name)" :size="44" :stroke="1.6" />
          </span>
          <span class="room-tile-text">
            <span class="room-tile-name">{{ room.name }}</span>
            <span class="room-tile-meta">
              {{ plural(room.itemCount ?? 0, 'objet') }} · {{ plural(room.storageCount ?? 0, 'rangement') }}
            </span>
          </span>
        </RouterLink>
        <button type="button" class="card room-tile room-tile--add" @click="openSheet('room-create')">
          <span class="room-tile-media scallop">
            <AppIcon name="plus" :size="32" :stroke="2" />
          </span>
          <span class="room-tile-text">
            <span class="room-tile-name">ajouter une pièce</span>
            <span class="room-tile-meta">Cuisine, cave, grenier…</span>
          </span>
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
