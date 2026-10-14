<script setup>
import { computed } from 'vue'

// Avatar : la photo si elle existe, sinon l'initiale sur une couleur de la palette, stable selon le pseudo
const props = defineProps({
  name: { type: String, default: '' },
  src: { type: String, default: null },
  size: { type: Number, default: 48 },
})

const PALETTE = [
  { background: '#EEB4B3', color: '#71103C' },
  { background: '#7180B9', color: '#FFFFFF' },
  { background: '#DADFF1', color: '#71103C' },
  { background: '#71103C', color: '#EEB4B3' },
]

const colors = computed(() => {
  const hash = [...props.name].reduce((sum, char) => sum + char.charCodeAt(0), 0)
  return PALETTE[hash % PALETTE.length]
})

const initial = computed(() => (props.name.trim()[0] ?? '?').toUpperCase())
</script>

<template>
  <img
    v-if="src"
    :src="src"
    :alt="name"
    :width="size"
    :height="size"
    :style="{ borderRadius: '999px', objectFit: 'cover' }"
  />
  <span
    v-else
    class="avatar-initial"
    role="img"
    :aria-label="name"
    :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${Math.round(size * 0.46)}px`, ...colors }"
  >
    <span aria-hidden="true">{{ initial }}</span>
  </span>
</template>
