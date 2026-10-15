<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import BottomSheet from '../components/BottomSheet.vue'
import ItemTile from '../components/ItemTile.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { toneFor } from '../hierarchy'
import { useAuthStore } from '../stores/auth'
import { usePlacesStore } from '../stores/places'

const auth = useAuthStore()
const placesStore = usePlacesStore()
const router = useRouter()

const items = ref([])
const loading = ref(true)
const tab = ref('dressing')
const settingsOpen = ref(false)

// Profil à jour (compteurs) et objets pour les onglets
Promise.all([auth.fetchMe(), api.list('/items')])
  .then(([, list]) => (items.value = list))
  .finally(() => (loading.value = false))

const user = computed(() => auth.user)
const tabItems = computed(() =>
  tab.value === 'sale' ? items.value.filter((item) => item.status === 'for_sale') : items.value,
)

function logout() {
  auth.logout()
  placesStore.reset()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="screen--flush">
    <div class="hero profile-band">
      <div class="hero-inner profile-band-inner">
        <button type="button" class="icon-btn icon-btn--on-dark" aria-label="Paramètres" data-cy="profile-settings" @click="settingsOpen = true">
          <AppIcon name="settings" />
        </button>
      </div>
    </div>

    <div v-if="user" class="screen-body profile-layout">
      <aside class="profile-aside">
        <span class="profile-avatar">
          <UserAvatar :name="user.username" :src="user.avatar" :size="96" />
        </span>

        <div class="profile-identity">
          <h1 class="display profile-name">{{ user.username }}</h1>
          <span class="muted profile-bio">
            {{ user.email }}<template v-if="user.bio"> · {{ user.bio }}</template>
          </span>
        </div>

        <div class="stat-grid">
          <div class="card stat">
            <span class="display stat-number">{{ user.itemCount ?? 0 }}</span>
            <span class="stat-label">objets</span>
          </div>
          <div class="card stat">
            <span class="display stat-number">{{ user.followerCount ?? 0 }}</span>
            <span class="stat-label">abonnés</span>
          </div>
          <div class="card stat">
            <span class="display stat-number">{{ user.closeFriendCount ?? 0 }}</span>
            <span class="stat-label">amis proches</span>
          </div>
        </div>

        <!-- Bandeau d'information : la page de gestion des demandes viendra avec la page Amis -->
        <div v-if="user.pendingRequestCount" class="notice tone-orange">
          <span class="notice-icon"><AppIcon name="friends" /></span>
          <span class="notice-text">
            {{ user.pendingRequestCount }} demande{{ user.pendingRequestCount > 1 ? 's' : '' }} en attente
          </span>
        </div>

        <section v-if="user.children?.length" class="stack stack--tight">
          <h2 class="section-title">Comptes rattachés</h2>
          <ul class="children-list">
            <li v-for="child in user.children" :key="child.id" class="child">
              <UserAvatar :name="child.username" :src="child.avatar" :size="56" />
              {{ child.username }}
              <span v-if="child.isMinor" class="small muted child-age">-18</span>
            </li>
          </ul>
        </section>
      </aside>

      <section class="profile-main">
        <div class="segmented" role="tablist" aria-label="Mes objets">
          <button type="button" role="tab" :aria-selected="tab === 'dressing'" @click="tab = 'dressing'">Dressing</button>
          <button type="button" role="tab" :aria-selected="tab === 'sale'" @click="tab = 'sale'">En vente</button>
        </div>

        <p v-if="loading" class="loading">Chargement…</p>
        <div v-else-if="tabItems.length" class="thumb-grid">
          <ItemTile v-for="(item, index) in tabItems" :key="item.id" :item="item" :tone="toneFor(index)" compact />
        </div>
        <div v-else class="empty-state">
          <p>{{ tab === 'sale' ? 'Aucun objet en vente.' : 'Aucun objet pour l\'instant.' }}</p>
        </div>
      </section>
    </div>

    <BottomSheet v-if="settingsOpen" title="Paramètres" @close="settingsOpen = false">
      <button type="button" class="btn btn--primary btn--large btn--block" data-cy="logout" @click="logout">
        <AppIcon name="logout" /> Se déconnecter
      </button>
    </BottomSheet>
  </div>
</template>
