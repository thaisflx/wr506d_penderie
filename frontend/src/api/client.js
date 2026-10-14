// Client HTTP minimal pour l'API Platform (JSON-LD) avec le jeton JWT

const TOKEN_KEY = 'penderie_token'

export function getToken() {
  try {
    return localStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export function setToken(token) {
  try {
    if (token) localStorage.setItem(TOKEN_KEY, token)
    else localStorage.removeItem(TOKEN_KEY)
  } catch {
    // stockage indisponible (navigation privée) : la session ne survivra pas au rechargement
  }
}

// Appelée quand l'API répond 401 (jeton expiré ou invalide) ; définie par le store d'authentification
let onUnauthorized = () => {}
export function setUnauthorizedHandler(handler) {
  onUnauthorized = handler
}

export class ApiError extends Error {
  constructor(message, status, violations = []) {
    super(message)
    this.status = status
    this.violations = violations
  }
}

// Transforme une IRI (/api/places/1) ou un chemin (/places/1) en URL appelable
function toUrl(path) {
  return path.startsWith('/api') ? path : `/api${path}`
}

export async function request(path, { method = 'GET', body, auth = true } = {}) {
  const headers = { Accept: 'application/ld+json' }
  if (body !== undefined) {
    headers['Content-Type'] = method === 'PATCH' ? 'application/merge-patch+json' : 'application/ld+json'
  }
  const token = getToken()
  if (auth && token) headers.Authorization = `Bearer ${token}`

  const response = await fetch(toUrl(path), {
    method,
    headers,
    body: body !== undefined ? JSON.stringify(body) : undefined,
  })

  if (response.status === 204) return null

  const data = await response.json().catch(() => null)

  if (!response.ok) {
    if (response.status === 401 && auth) onUnauthorized()
    const violations = data?.violations ?? []
    const message = data?.detail || data?.description || data?.message || `Erreur ${response.status}`
    throw new ApiError(message, response.status, violations)
  }

  return data
}

export const api = {
  get: (path) => request(path),
  // Renvoie directement la liste des éléments d'une collection Hydra
  list: async (path) => (await request(path))?.member ?? [],
  post: (path, body) => request(path, { method: 'POST', body }),
  patch: (path, body) => request(path, { method: 'PATCH', body }),
  delete: (path) => request(path, { method: 'DELETE' }),
}
