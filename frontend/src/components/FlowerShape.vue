<script setup>
// Forme fleur de la maquette v2 : un cercle central (r=34) et 8 pétales (r=16) à 32 du centre, viewBox 100.
// Décorative : sert de fond derrière une icône, une initiale ou une photo (voir aussi la classe .flower-mask).
defineProps({
  // Couleur de remplissage (variable CSS ou couleur)
  fill: { type: String, default: 'var(--cyan)' },
})

const PETALS = Array.from({ length: 8 }, (_, i) => {
  const angle = (i * Math.PI) / 4 - Math.PI / 2
  return [50 + 32 * Math.cos(angle), 50 + 32 * Math.sin(angle)]
})
</script>

<template>
  <svg class="flower" viewBox="0 0 100 100" aria-hidden="true" focusable="false">
    <g :fill="fill">
      <circle cx="50" cy="50" r="34" />
      <circle v-for="([cx, cy], i) in PETALS" :key="i" :cx="cx" :cy="cy" r="16" />
    </g>
  </svg>
</template>
