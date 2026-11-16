<script setup>
import { computed, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { api } from '../api/client'
import { relativeTime } from '../utils/relativeTime'
import AppIcon from './AppIcon.vue'
import BottomSheet from './BottomSheet.vue'
import DecoStar from './DecoStar.vue'
import ItemVisual from './ItemVisual.vue'
import UserAvatar from './UserAvatar.vue'

// Carte d'un look du jour (maquette v2 : Fil.png, sans ligne violette)
const props = defineProps({
  ootd: { type: Object, required: true },
  // close_friend, follower ou null : mon lien avec l'auteur (libellé traduit à l'affichage)
  relation: { type: String, default: null },
  // Mon look : mes objets ouvrent leur fiche (ceux des autres, un panneau en lecture seule) et je peux le supprimer
  mine: { type: Boolean, default: false },
  // Profil public : l'auteur est déjà en tête de page
  hideAuthor: { type: Boolean, default: false },
})
const emit = defineEmits(['preview-item', 'deleted'])

const TONES = ['tone-glaucous', 'tone-sky', 'tone-peach']
const when = computed(() => relativeTime(props.ootd.publishedAt))

// Suppression de mon look (pas de retour en brouillon : docs/decisions.md, lot 2), après confirmation
const confirming = ref(false)
const deleting = ref(false)
const deleteError = ref('')

async function remove() {
  deleting.value = true
  deleteError.value = ''
  try {
    await api.delete(`/ootds/${props.ootd.id}`)
    confirming.value = false
    emit('deleted', props.ootd.id)
  } catch (e) {
    deleteError.value = e.message
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <article class="ootd-card" data-cy="ootd-card">
    <header v-if="!hideAuthor" class="ootd-card-author">
      <RouterLink :to="mine ? '/profile' : `/users/${ootd.owner.id}`" class="ootd-card-avatar" :aria-label="$t('look.profile_of', { name: ootd.owner.username })">
        <UserAvatar :name="ootd.owner.username" :src="ootd.owner.avatar" :size="40" />
      </RouterLink>
      <span class="ootd-card-who">
        <RouterLink :to="mine ? '/profile' : `/users/${ootd.owner.id}`" class="ootd-card-name" data-cy="ootd-author">{{ ootd.owner.username }}</RouterLink>
        <time class="ootd-card-when" :datetime="ootd.publishedAt" data-cy="ootd-when">{{ when }}</time>
      </span>
      <span v-if="relation" class="chip chip--peach" data-cy="ootd-relation">{{ $t(`relation.${relation}`) }}</span>
      <button v-if="mine" type="button" class="icon-btn ootd-card-delete" :aria-label="$t('look.delete')" data-cy="ootd-delete" @click="confirming = true">
        <AppIcon name="trash" :size="18" />
      </button>
    </header>
    <div v-else class="ootd-card-meta">
      <time class="ootd-card-when" :datetime="ootd.publishedAt" data-cy="ootd-when">{{ when }}</time>
      <button v-if="mine" type="button" class="icon-btn ootd-card-delete" :aria-label="$t('look.delete')" data-cy="ootd-delete" @click="confirming = true">
        <AppIcon name="trash" :size="18" />
      </button>
    </div>

    <div class="ootd-card-photo scallop" :class="ootd.photo ? '' : 'tone-glaucous'">
      <img v-if="ootd.photo" :src="ootd.photo" :alt="$t('look.outfit_of', { name: ootd.owner.username })" loading="lazy" />
      <DecoStar class="ootd-card-star" :size="26" color="var(--white)" />
    </div>

    <p v-if="ootd.caption" class="ootd-card-caption" data-cy="ootd-caption">{{ ootd.caption }}</p>

    <section v-if="ootd.wornItems.length" class="ootd-card-items" :aria-label="$t('look.worn_by', { name: ootd.owner.username })">
      <h2 class="section-title ootd-card-items-title">{{ $t('look.what_i_wear') }}</h2>
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

    <BottomSheet v-if="confirming" :title="$t('look.delete_title')" @close="confirming = false">
      <div class="confirm-box" role="alert">
        <p>{{ $t('look.delete_photo_erased') }}</p>
        <p v-if="deleteError" class="error">{{ deleteError }}</p>
        <button type="button" class="btn btn--danger btn--block" data-cy="ootd-delete-confirm" :disabled="deleting" @click="remove">{{ $t('common.delete') }}</button>
        <button type="button" class="btn btn--soft btn--block" @click="confirming = false">{{ $t('look.keep') }}</button>
      </div>
    </BottomSheet>
  </article>
</template>
