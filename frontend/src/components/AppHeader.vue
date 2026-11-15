<script setup>
import { RouterLink } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import AppIcon from './AppIcon.vue'
import AppLogo from './AppLogo.vue'
import UserAvatar from './UserAvatar.vue'

// En-tête des écrans principaux : logo à gauche ; cloche des demandes d'amis (fil seulement) et avatar à droite
defineProps({
  bell: { type: Boolean, default: false },
})

const auth = useAuthStore()
</script>

<template>
  <header class="home-header">
    <AppLogo class="home-logo" />
    <div class="home-header-actions">
      <RouterLink
        v-if="bell"
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
</template>
