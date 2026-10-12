import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import { api, getToken, request, setToken } from '../api/client'

export const useAuthStore = defineStore('auth', () => {
  const token = ref(getToken())
  const user = ref(null)

  const isAuthenticated = computed(() => !!token.value)

  async function login(email, password) {
    const data = await request('/login_check', {
      method: 'POST',
      body: { email, password },
      auth: false,
    })
    token.value = data.token
    setToken(data.token)
    await fetchMe()
  }

  // Profil de l'utilisateur connecté
  async function fetchMe() {
    if (!token.value) return null
    user.value = await api.get('/me')
    return user.value
  }

  function logout() {
    token.value = null
    user.value = null
    setToken(null)
  }

  return { token, user, isAuthenticated, login, fetchMe, logout }
})
