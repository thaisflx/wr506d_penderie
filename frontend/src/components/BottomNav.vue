<script setup>
import { RouterLink } from 'vue-router'
import NavIcon from './NavIcon.vue'

// Barre de navigation principale
defineProps({
  current: { type: String, default: null },
})
</script>

<template>
  <nav class="bottom-nav" aria-label="Navigation principale">
    <RouterLink to="/" class="nav-link" aria-label="Accueil" title="Accueil" :aria-current="current === 'home' ? 'page' : undefined">
      <NavIcon name="home" :size="29" />
    </RouterLink>
    <span class="nav-link" role="link" aria-label="Amis (bientôt disponible)" aria-disabled="true" title="Bientôt disponible">
      <NavIcon name="friends" :size="33" />
    </span>
    <RouterLink to="/scan" class="nav-scan" aria-label="Scanner" title="Scanner">
      <NavIcon name="scan" :size="84" />
    </RouterLink>
    <RouterLink to="/dressing" class="nav-link" aria-label="Dressing" title="Dressing" :aria-current="current === 'dressing' ? 'page' : undefined">
      <NavIcon name="dressing" :size="31" />
    </RouterLink>
    <RouterLink to="/profile" class="nav-link" data-cy="nav-profile" aria-label="Profil" title="Profil" :aria-current="current === 'profile' ? 'page' : undefined">
      <NavIcon name="profile" :size="29" />
    </RouterLink>
  </nav>
</template>
