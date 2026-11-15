<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import AppHeader from '../components/AppHeader.vue'
import AppIcon from '../components/AppIcon.vue'
import ItemPreviewSheet from '../components/ItemPreviewSheet.vue'
import OotdCard from '../components/OotdCard.vue'
import { useOotdFeed } from '../composables/useOotdFeed'

// Accueil : fil des looks du jour (docs/decisions.md, lot 2). Les looks publiés que je peux voir et les miens,
// du plus récent au plus ancien, 10 par page.
const { ootds, loading, loadingMore, error, hasMore, load, loadMore, isMine, relationOf } = useOotdFeed(() => '/ootds')
load()

// Objet d'une autre personne ouvert en lecture seule
const preview = ref(null)
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

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="loading" class="loading">Chargement…</p>

    <section v-else-if="!ootds.length" class="empty-state" data-cy="feed-empty">
      <p><strong>Aucun look du jour pour l'instant.</strong></p>
      <p>Suis tes amis pour voir leurs tenues, ou poste la tienne.</p>
      <div class="feed-empty-actions">
        <RouterLink :to="{ path: '/friends', query: { tab: 'search' } }" class="btn" data-cy="feed-find-friends">Trouver des amis</RouterLink>
        <RouterLink to="/looks/nouveau" class="btn btn--primary">Poster mon look</RouterLink>
      </div>
    </section>

    <template v-else>
      <div class="ootd-list">
        <OotdCard
          v-for="ootd in ootds"
          :key="ootd.id"
          :ootd="ootd"
          :mine="isMine(ootd)"
          :relation="relationOf(ootd)"
          @preview-item="preview = $event"
        />
      </div>
      <button v-if="hasMore" type="button" class="btn btn--block" data-cy="feed-more" :disabled="loadingMore" @click="loadMore">
        {{ loadingMore ? 'Chargement…' : 'Voir plus' }}
      </button>
    </template>

    <ItemPreviewSheet v-if="preview" :item="preview" @close="preview = null" />
  </main>
</template>
