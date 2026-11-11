<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import BottomSheet from '../components/BottomSheet.vue'
import DecoStar from '../components/DecoStar.vue'
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
  <div class="screen--flush profile-page">
    <!-- Bandeau rose pâle festonné -->
    <div class="profile-band scallop">
      <DecoStar class="profile-star profile-star--white" :size="18" color="var(--white)" />
      <DecoStar class="profile-star profile-star--wine" :size="22" color="var(--wine)" />
      <button type="button" class="icon-btn profile-band-btn" aria-label="Paramètres" data-cy="profile-settings" @click="settingsOpen = true">
        <AppIcon name="settings" />
      </button>
    </div>

    <div v-if="user" class="profile-content profile-layout">
      <aside class="profile-aside">
        <span class="profile-avatar">
          <UserAvatar :name="user.username" :src="user.avatar" :size="96" />
        </span>

        <div class="profile-identity">
          <h1 class="display profile-name">{{ user.username }}</h1>
          <p v-if="user.bio" class="profile-bio">{{ user.bio }}</p>
          <p class="small muted">{{ user.email }}</p>
        </div>

        <div class="stat-grid">
          <div class="card stat">
            <span class="display stat-number">{{ user.itemCount ?? 0 }}</span>
            <span class="stat-label">{{ (user.itemCount ?? 0) > 1 ? 'objets' : 'objet' }}</span>
          </div>
          <div class="card stat">
            <span class="display stat-number">{{ user.followerCount ?? 0 }}</span>
            <span class="stat-label">{{ (user.followerCount ?? 0) > 1 ? 'abonnés' : 'abonné' }}</span>
          </div>
          <div class="card stat">
            <span class="display stat-number">{{ user.closeFriendCount ?? 0 }}</span>
            <span class="stat-label">{{ (user.closeFriendCount ?? 0) > 1 ? 'amis proches' : 'ami proche' }}</span>
          </div>
        </div>

        <!-- Bandeau des demandes reçues : ouvre la page Amis -->
        <RouterLink v-if="user.pendingRequestCount" to="/friends" class="notice notice--link notice--request" data-cy="profile-requests-notice">
          <span class="notice-text">
            <span class="notice-count">{{ user.pendingRequestCount }}</span> demande{{ user.pendingRequestCount > 1 ? 's' : '' }} en attente
          </span>
          <AppIcon name="next" :size="18" :stroke="2.2" />
        </RouterLink>

        <section v-if="user.children?.length" class="stack stack--tight">
          <h2 class="section-title">comptes rattachés</h2>
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
        <div class="pills profile-tabs" role="tablist" aria-label="Mes objets">
          <button type="button" class="pill" role="tab" :aria-selected="tab === 'dressing'" :aria-pressed="tab === 'dressing'" @click="tab = 'dressing'">Dressing</button>
          <button type="button" class="pill" role="tab" :aria-selected="tab === 'sale'" :aria-pressed="tab === 'sale'" @click="tab = 'sale'">En vente</button>
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
      <RouterLink to="/blocked" class="btn btn--soft btn--large btn--block" data-cy="settings-blocked">
        <AppIcon name="lock" /> Comptes bloqués
      </RouterLink>
      <button type="button" class="btn btn--primary btn--large btn--block" data-cy="logout" @click="logout">
        <AppIcon name="logout" /> Se déconnecter
      </button>
    </BottomSheet>
  </div>
</template>
