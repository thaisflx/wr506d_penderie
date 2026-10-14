<script setup>
// Visuel d'un objet : sa photo, sinon un vinyle (média) ou un cintre comme dans les maquettes
defineProps({
  item: { type: Object, required: true },
  categoryType: { type: String, default: null },
  size: { type: Number, default: 44 },
})
</script>

<template>
  <img v-if="item.photo" :src="item.photo" :alt="item.name" />
  <svg v-else-if="categoryType === 'media'" :width="size" :height="size" viewBox="0 0 40 40" aria-hidden="true">
    <circle cx="20" cy="20" r="18" fill="#71103C" />
    <circle cx="20" cy="20" r="13" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="1" />
    <circle cx="20" cy="20" r="9" fill="none" stroke="rgba(255,255,255,0.18)" stroke-width="1" />
    <circle cx="20" cy="20" r="5.5" fill="#EEB4B3" />
    <circle cx="20" cy="20" r="1.4" fill="#71103C" />
  </svg>
  <svg
    v-else
    :width="size"
    :height="size"
    viewBox="0 0 48 48"
    fill="none"
    stroke="currentColor"
    stroke-width="2.2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
  >
    <path d="M20 14a4 4 0 1 1 5.5 3.7c-1 .4-1.5 1.3-1.5 2.3v2" />
    <path d="M24 22 L42 34 a2 2 0 0 1 -1 3.6 H7 a2 2 0 0 1 -1 -3.6 Z" />
  </svg>
</template>
