import { ref } from 'vue'
import { t } from '../i18n'
import { toJpegDataUrl } from '../utils/image'

// Caméra arrière du téléphone dans un <video> : partagée par le Scanner et l'écran « Poster un look ».
// La photo prise est réduite à 1024 px et exportée en JPEG base64 (utils/image.js).
export function useCamera() {
  const video = ref(null)
  const ready = ref(false)
  const error = ref('')
  const torchAvailable = ref(false)
  const torchOn = ref(false)
  let stream = null

  async function start() {
    error.value = ''
    if (!navigator.mediaDevices?.getUserMedia) {
      error.value = t('camera.unavailable')
      return
    }
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } },
        audio: false,
      })
      video.value.srcObject = stream
      await video.value.play()
      ready.value = true
      const track = stream.getVideoTracks()[0]
      torchAvailable.value = !!track.getCapabilities?.().torch
    } catch {
      error.value = t('camera.denied')
    }
  }

  function stop() {
    stream?.getTracks().forEach((track) => track.stop())
    stream = null
    ready.value = false
  }

  async function toggleTorch() {
    const track = stream?.getVideoTracks()[0]
    if (!track) return
    torchOn.value = !torchOn.value
    await track.applyConstraints({ advanced: [{ torch: torchOn.value }] }).catch(() => (torchOn.value = false))
  }

  // Image actuelle de la caméra, en JPEG base64 ; null si la caméra n'est pas prête
  function capture() {
    return ready.value ? toJpegDataUrl(video.value) : null
  }

  return { video, ready, error, torchAvailable, torchOn, start, stop, toggleTorch, capture }
}
