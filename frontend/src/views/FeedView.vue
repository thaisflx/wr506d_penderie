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

// Mon look supprimé : il disparaît du fil sans recharger
const removeLook = (id) => (ootds.value = ootds.value.filter((ootd) => ootd.id !== id))
</script>

<template>
  <main class="screen feed">
    <AppHeader bell />

    <div class="feed-head">
      <h1 class="display page-title" data-cy="feed-title">{{ $t('feed.title') }}</h1>
      <RouterLink to="/looks/nouveau" class="btn btn--primary" data-cy="feed-post">
        <AppIcon name="plus" :size="18" :stroke="2.4" /> {{ $t('feed.post') }}
      </RouterLink>
    </div>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="loading" class="loading">{{ $t('common.loading') }}</p>

    <section v-else-if="!ootds.length" class="empty-state" data-cy="feed-empty">
      <p><strong>{{ $t('feed.empty_title') }}</strong></p>
      <p>{{ $t('feed.empty_text') }}</p>
      <div class="feed-empty-actions">
        <RouterLink :to="{ path: '/friends', query: { tab: 'search' } }" class="btn" data-cy="feed-find-friends">{{ $t('feed.find_friends') }}</RouterLink>
        <RouterLink to="/looks/nouveau" class="btn btn--primary">{{ $t('look.post_mine') }}</RouterLink>
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
          @deleted="removeLook"
        />
      </div>
      <button v-if="hasMore" type="button" class="btn btn--block" data-cy="feed-more" :disabled="loadingMore" @click="loadMore">
        {{ loadingMore ? $t('common.loading') : $t('common.see_more') }}
      </button>
    </template>

    <ItemPreviewSheet v-if="preview" :item="preview" @close="preview = null" />
  </main>
</template>
