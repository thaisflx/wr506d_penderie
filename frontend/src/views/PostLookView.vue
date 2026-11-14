<script setup>
import { useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'

// Poster ou reprendre un look du jour (/looks/nouveau, /looks/:id/modifier), plein écran sans barre.
// Le formulaire (photo, légende, objets portés, Brouillon / Publier) arrive au commit suivant du lot 2.
defineProps({
  id: { type: String, default: null },
})
const router = useRouter()

function close() {
  if (window.history.state?.back) router.back()
  else router.push('/')
}
</script>

<template>
  <main class="screen post-look" data-cy="post-look">
    <div class="page-head">
      <button type="button" class="icon-btn" aria-label="Fermer" data-cy="post-look-close" @click="close">
        <AppIcon name="close" :stroke="2.4" />
      </button>
    </div>
    <h1 class="display page-title">{{ id ? 'Mon brouillon' : 'Mon look du jour' }}</h1>
    <section class="empty-state">
      <p><strong>Bientôt disponible.</strong></p>
      <p>Tu pourras prendre ta tenue en photo, choisir les pièces portées et la publier pour tes amis.</p>
    </section>
  </main>
</template>
