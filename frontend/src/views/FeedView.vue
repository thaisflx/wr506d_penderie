<script setup>
import { RouterLink } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import AppIcon from '../components/AppIcon.vue'

// Accueil : fil des looks du jour (docs/decisions.md, lot 2). Les cartes arrivent avec l'API du fil ;
// en attendant, un état vide qui oriente vers les amis et la publication.
</script>

<template>
  <main class="screen feed">
    <AppHeader bell />

    <div class="feed-head">
      <h1 class="display page-title" data-cy="feed-title">Looks du jour</h1>
      <RouterLink to="/looks/nouveau" class="btn btn--primary" data-cy="feed-post">
        <AppIcon name="plus" :size="18" :stroke="2.4" /> Poster
      </RouterLink>
    </div>

    <section class="empty-state" data-cy="feed-empty">
      <p><strong>Aucun look du jour pour l'instant.</strong></p>
      <p>Suis tes amis pour voir leurs tenues, ou poste la tienne.</p>
      <div class="feed-empty-actions">
        <RouterLink :to="{ path: '/friends', query: { tab: 'search' } }" class="btn" data-cy="feed-find-friends">Trouver des amis</RouterLink>
        <RouterLink to="/looks/nouveau" class="btn btn--primary">Poster mon look</RouterLink>
      </div>
    </section>
  </main>
</template>
