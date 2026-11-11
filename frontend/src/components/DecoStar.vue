<script setup>
// Étoile décorative à 4 branches (maquette v2), à utiliser avec parcimonie. Purement décorative.
defineProps({
  size: { type: Number, default: 22 },
  color: { type: String, default: 'var(--rose)' },
})
</script>

<template>
  <svg class="deco-star" :width="size" :height="size" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <path d="M12 0c1 7 5 11 12 12-7 1-11 5-12 12-1-7-5-11-12-12 7-1 11-5 12-12z" :fill="color" />
  </svg>
</template>
