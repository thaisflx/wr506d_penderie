import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api } from '../api/client'

const CURRENT_KEY = 'penderie_current_place'

function readCurrentId() {
  try {
    return Number(localStorage.getItem(CURRENT_KEY)) || null
  } catch {
    return null
  }
}

// Logements de l'utilisateur et logement affiché sur l'accueil (bouton « Changer »)
export const usePlacesStore = defineStore('places', () => {
  const places = ref([])
  const loaded = ref(false)
  const currentId = ref(readCurrentId())

  const current = computed(
    () => places.value.find((place) => place.id === currentId.value) ?? places.value[0] ?? null,
  )

  async function load() {
    places.value = await api.list('/places')
    loaded.value = true
  }

  function select(id) {
    currentId.value = id
    try {
      localStorage.setItem(CURRENT_KEY, String(id))
    } catch {
      // préférence non mémorisée, sans conséquence
    }
  }

  function upsert(place) {
    const index = places.value.findIndex((existing) => existing.id === place.id)
    if (index === -1) places.value.push(place)
    else places.value[index] = place
  }

  function remove(id) {
    places.value = places.value.filter((place) => place.id !== id)
    if (currentId.value === id) currentId.value = null
  }

  // Vidé à la déconnexion
  function reset() {
    places.value = []
    loaded.value = false
  }

  return { places, loaded, current, load, select, upsert, remove, reset }
})
