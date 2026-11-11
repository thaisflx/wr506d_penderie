<script setup>
import { RouterLink } from 'vue-router'
import NavIcon from './NavIcon.vue'

// Barre de navigation principale : pilule blanche flottante, Scanner au centre
defineProps({
  current: { type: String, default: null },
})
</script>

<template>
  <nav class="bottom-nav" aria-label="Navigation principale">
    <RouterLink to="/" class="nav-link" data-cy="nav-home" :aria-current="current === 'home' ? 'page' : undefined">
      <NavIcon name="home" />
      <span>Accueil</span>
    </RouterLink>
    <!-- Fil des looks du jour : arrive avec le lot 2 du volet social (route /feed) -->
    <span class="nav-link" role="link" aria-disabled="true" title="Bientôt disponible" data-cy="nav-looks">
      <NavIcon name="looks" />
      <span>Looks</span>
    </span>
    <RouterLink to="/scan" class="nav-scan" aria-label="Scanner un objet" title="Scanner un objet">
      <NavIcon name="scan" :size="76" />
    </RouterLink>
    <RouterLink to="/friends" class="nav-link" data-cy="nav-friends" :aria-current="current === 'friends' ? 'page' : undefined">
      <NavIcon name="friends" />
      <span>Amis</span>
    </RouterLink>
    <RouterLink to="/profile" class="nav-link" data-cy="nav-profile" :aria-current="current === 'profile' ? 'page' : undefined">
      <NavIcon name="profile" />
      <span>Profil</span>
    </RouterLink>
  </nav>
</template>
