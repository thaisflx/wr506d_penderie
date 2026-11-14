<script setup>
import { RouterLink } from 'vue-router'
import NavIcon from './NavIcon.vue'

// Barre de navigation principale : pilule blanche flottante, Scanner au centre.
// Accueil = fil des looks du jour ; Mon dressing = logement, pièces et objets (docs/decisions.md, lot 2)
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
    <!-- Libellé court pour laisser de la place au Scanner ; le nom complet reste lu par les lecteurs d'écran -->
    <RouterLink to="/dressing" class="nav-link" data-cy="nav-dressing" aria-label="Mon dressing" :aria-current="current === 'dressing' ? 'page' : undefined">
      <NavIcon name="dressing" />
      <span>Dressing</span>
    </RouterLink>
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
