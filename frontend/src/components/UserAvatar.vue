<script setup>
import { computed } from 'vue'

// Avatar : la photo si elle existe, sinon l'initiale sur une couleur de la palette, stable selon le pseudo.
// --avatar-override et --avatar-override-font (posés par un parent en CSS) agrandissent l'avatar sans changer
// la prop, ex. profil sur ordinateur.
// L'initiale est en minuscule : Mono Scribble l'affiche en capitale grasse (règle de casse de la maquette)
const props = defineProps({
  name: { type: String, default: '' },
  src: { type: String, default: null },
  size: { type: Number, default: 48 },
  // Forme fleur (demandes d'amis, maquette v2) au lieu du rond
  flower: { type: Boolean, default: false },
})

// Fonds pastel de la maquette v2, initiale bordeaux (contraste 8:1 à 10:1)
const PALETTE = [
  { background: '#F6D6D5', color: '#71103C' },
  { background: '#D5F5FA', color: '#71103C' },
  { background: '#DADFF1', color: '#71103C' },
]

const colors = computed(() => {
  const hash = [...props.name].reduce((sum, char) => sum + char.charCodeAt(0), 0)
  return PALETTE[hash % PALETTE.length]
})

const initial = computed(() => (props.name.trim()[0] ?? '?').toLowerCase())
</script>

<template>
  <img
    v-if="src"
    :src="src"
    :alt="name"
    :width="size"
    :height="size"
    :class="{ 'flower-mask': flower }"
    :style="{ width: `var(--avatar-override, ${size}px)`, height: `var(--avatar-override, ${size}px)`, borderRadius: flower ? 0 : '999px', objectFit: 'cover' }"
  />
  <span
    v-else
    class="avatar-initial"
    :class="{ 'avatar-initial--flower flower-mask': flower }"
    role="img"
    :aria-label="name"
    :style="{ width: `var(--avatar-override, ${size}px)`, height: `var(--avatar-override, ${size}px)`, fontSize: `var(--avatar-override-font, ${Math.round(size * 0.46)}px)`, ...colors }"
  >
    <span aria-hidden="true">{{ initial }}</span>
  </span>
</template>
