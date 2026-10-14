<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue'
import AppIcon from './AppIcon.vue'

// Panneau qui monte du bas de l'écran (formulaires, choix) ; Échap ou clic à côté pour fermer
defineProps({
  title: { type: String, required: true },
})
const emit = defineEmits(['close'])

const panel = ref(null)
let previousFocus = null

function onKeydown(event) {
  if (event.key === 'Escape') emit('close')
}

onMounted(async () => {
  previousFocus = document.activeElement
  document.addEventListener('keydown', onKeydown)
  await nextTick()
  panel.value?.querySelector('input, select, textarea, button')?.focus()
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
  previousFocus?.focus?.()
})
</script>

<template>
  <Teleport to="body">
    <div class="sheet-backdrop" @click.self="emit('close')">
      <section ref="panel" class="sheet" role="dialog" aria-modal="true" :aria-label="title">
        <span class="sheet-grip" aria-hidden="true"></span>
        <header class="sheet-head">
          <h2 class="display sheet-title">{{ title }}</h2>
          <button type="button" class="icon-btn" aria-label="Fermer" @click="emit('close')">
            <AppIcon name="close" />
          </button>
        </header>
        <slot />
      </section>
    </div>
  </Teleport>
</template>
