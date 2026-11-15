<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { relativeTime } from '../utils/relativeTime'
import DecoStar from './DecoStar.vue'
import ItemVisual from './ItemVisual.vue'
import UserAvatar from './UserAvatar.vue'

// Carte d'un look du jour (maquette v2 : Fil.png, sans ligne violette)
const props = defineProps({
  ootd: { type: Object, required: true },
  // « Ami proche », « Abonné » ou null : mon lien avec l'auteur
  relation: { type: String, default: null },
  // Mon look : mes objets ouvrent leur fiche ; ceux des autres, un panneau en lecture seule
  mine: { type: Boolean, default: false },
  // Profil public : l'auteur est déjà en tête de page
  hideAuthor: { type: Boolean, default: false },
})
const emit = defineEmits(['preview-item'])

const TONES = ['tone-glaucous', 'tone-sky', 'tone-peach']
const when = computed(() => relativeTime(props.ootd.publishedAt))
</script>

<template>
  <article class="ootd-card" data-cy="ootd-card">
    <header v-if="!hideAuthor" class="ootd-card-author">
      <RouterLink :to="mine ? '/profile' : `/users/${ootd.owner.id}`" class="ootd-card-avatar" :aria-label="`Profil de ${ootd.owner.username}`">
        <UserAvatar :name="ootd.owner.username" :src="ootd.owner.avatar" :size="40" />
      </RouterLink>
      <span class="ootd-card-who">
        <RouterLink :to="mine ? '/profile' : `/users/${ootd.owner.id}`" class="ootd-card-name" data-cy="ootd-author">{{ ootd.owner.username }}</RouterLink>
        <time class="ootd-card-when" :datetime="ootd.publishedAt" data-cy="ootd-when">{{ when }}</time>
      </span>
      <span v-if="relation" class="chip chip--peach" data-cy="ootd-relation">{{ relation }}</span>
    </header>
    <time v-else class="ootd-card-when" :datetime="ootd.publishedAt" data-cy="ootd-when">{{ when }}</time>

    <div class="ootd-card-photo scallop" :class="ootd.photo ? '' : 'tone-glaucous'">
      <img v-if="ootd.photo" :src="ootd.photo" :alt="`Tenue de ${ootd.owner.username}`" loading="lazy" />
      <DecoStar class="ootd-card-star" :size="26" color="var(--white)" />
    </div>

    <p v-if="ootd.caption" class="ootd-card-caption" data-cy="ootd-caption">{{ ootd.caption }}</p>

    <section v-if="ootd.wornItems.length" class="ootd-card-items" :aria-label="`Ce que porte ${ootd.owner.username}`">
      <h2 class="section-title ootd-card-items-title">ce que je porte</h2>
      <div class="pills">
        <template v-for="(item, index) in ootd.wornItems" :key="item.id">
          <RouterLink v-if="mine" :to="`/items/${item.id}`" class="worn-pill" data-cy="worn-item">
            <span class="worn-pill-thumb" :class="item.photo ? '' : TONES[index % TONES.length]"><ItemVisual :item="item" :size="18" /></span>
            {{ item.name }}
          </RouterLink>
          <button v-else type="button" class="worn-pill" data-cy="worn-item" @click="emit('preview-item', item)">
            <span class="worn-pill-thumb" :class="item.photo ? '' : TONES[index % TONES.length]"><ItemVisual :item="item" :size="18" /></span>
            {{ item.name }}
          </button>
        </template>
      </div>
    </section>
  </article>
</template>
