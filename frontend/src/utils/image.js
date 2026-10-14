// Préparation d'une photo pour le scan IA :
// redimensionnement (1024 px max sur le plus grand côté) -> conversion JPEG -> base64 (data URL).

export const MAX_SIZE = 1024
const JPEG_QUALITY = 0.85

// Photos d'iPhone : Safari les lit nativement, Chrome et Firefox non
function looksLikeHeic(file) {
  return /image\/hei[cf]/i.test(file.type) || /\.hei[cf]$/i.test(file.name)
}

// Charge un fichier image (galerie, appareil photo du téléphone) dans un élément <img>
function loadImage(file) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file)
    const img = new Image()
    img.onload = () => {
      URL.revokeObjectURL(url)
      resolve(img)
    }
    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error("Impossible de lire cette image."))
    }
    img.src = url
  })
}

/**
 * Dessine la source (image, ImageBitmap ou flux vidéo) dans un canvas réduit puis l'exporte en JPEG base64.
 * @param {HTMLImageElement|HTMLVideoElement|ImageBitmap} source
 * @returns {string} data:image/jpeg;base64,...
 */
export function toJpegDataUrl(source, maxSize = MAX_SIZE) {
  const width = source.videoWidth || source.naturalWidth || source.width
  const height = source.videoHeight || source.naturalHeight || source.height
  // Jamais d'agrandissement : seules les images plus grandes que 1024 px sont réduites
  const scale = Math.min(1, maxSize / Math.max(width, height))

  const canvas = document.createElement('canvas')
  canvas.width = Math.round(width * scale)
  canvas.height = Math.round(height * scale)
  const context = canvas.getContext('2d')
  // Fond blanc : les zones transparentes (PNG) ne deviennent pas noires en JPEG
  context.fillStyle = '#ffffff'
  context.fillRect(0, 0, canvas.width, canvas.height)
  context.drawImage(source, 0, 0, canvas.width, canvas.height)

  return canvas.toDataURL('image/jpeg', JPEG_QUALITY)
}

export async function fileToJpegDataUrl(file, maxSize = MAX_SIZE) {
  try {
    return toJpegDataUrl(await loadImage(file), maxSize)
  } catch (error) {
    if (!looksLikeHeic(file)) throw error
    // Le navigateur ne sait pas lire le HEIC : décodage par libheif (WebAssembly), chargé seulement ici
    try {
      const { heicTo } = await import('heic-to')
      return toJpegDataUrl(await heicTo({ blob: file, type: 'bitmap' }), maxSize)
    } catch {
      throw new Error('Impossible de lire cette photo HEIC : exporte-la en JPEG puis réessaie.')
    }
  }
}
