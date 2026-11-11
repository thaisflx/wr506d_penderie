<script setup>
// Logo Lendly en texte (police Mono Scribble) : « len » et « l » en capitales roses, « D » et « Y. » manuscrits bordeaux
defineProps({
  // Taille en px ; sans valeur, c'est le CSS du parent qui la fixe
  size: { type: Number, default: null },
})
</script>

<template>
  <span class="app-logo" role="img" aria-label="Lendly" data-cy="app-logo" :style="size ? { fontSize: `${size}px` } : null">
    <span aria-hidden="true" class="app-logo-rose">len</span><span aria-hidden="true" class="app-logo-wine">D</span><span aria-hidden="true" class="app-logo-rose">l</span><span aria-hidden="true" class="app-logo-wine">Y.</span>
  </span>
</template>
