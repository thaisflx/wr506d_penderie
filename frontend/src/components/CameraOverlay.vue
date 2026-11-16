<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import { useCamera } from '../composables/useCamera'
import AppIcon from './AppIcon.vue'

// Caméra plein écran (même rendu que le Scanner) : déclencheur au centre, fermer en haut à gauche
const emit = defineEmits(['photo', 'close'])
const { video, ready, error, torchAvailable, torchOn, start, stop, toggleTorch, capture } = useCamera()

onMounted(start)
onBeforeUnmount(stop)

function shoot() {
  const dataUrl = capture()
  if (dataUrl) emit('photo', dataUrl)
}
</script>

<template>
  <div class="scan camera-overlay" role="dialog" aria-modal="true" :aria-label="$t('camera.title_look')" data-cy="camera">
    <video ref="video" class="scan-video" :class="{ 'scan-video--hidden': !ready }" playsinline muted></video>
    <div class="scan-shade" aria-hidden="true"></div>
    <div class="scan-stage">
      <header class="scan-header">
        <button type="button" class="icon-btn icon-btn--on-dark" :aria-label="$t('camera.close')" data-cy="camera-close" @click="emit('close')">
          <AppIcon name="close" :stroke="2.4" />
        </button>
        <h2 class="scan-title">{{ $t('camera.my_outfit') }}</h2>
        <button v-if="torchAvailable" type="button" class="icon-btn icon-btn--on-dark" :aria-pressed="torchOn" :aria-label="$t('camera.flash')" @click="toggleTorch">
          <AppIcon name="flash" />
        </button>
        <span v-else class="scan-header-spacer" aria-hidden="true"></span>
      </header>
      <div class="scan-target">
        <p class="scan-hint" aria-live="polite">{{ error || (ready ? $t('camera.frame_outfit') : $t('camera.opening')) }}</p>
        <div class="scan-controls">
          <span class="scan-side-btn scan-side-btn--ghost" aria-hidden="true"></span>
          <button type="button" class="scan-shutter" :disabled="!ready" :aria-label="$t('camera.shoot')" data-cy="camera-shutter" @click="shoot"></button>
          <span class="scan-side-btn scan-side-btn--ghost" aria-hidden="true"></span>
        </div>
      </div>
    </div>
  </div>
</template>
