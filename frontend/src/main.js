import { createPinia } from 'pinia'
import { createApp } from 'vue'
import { setUnauthorizedHandler } from './api/client'
import App from './App.vue'
import router from './router'
import { useAuthStore } from './stores/auth'
import { usePlacesStore } from './stores/places'
import './style.css'

const app = createApp(App)
app.use(createPinia())
app.use(router)

// Jeton expiré ou invalide : retour à la connexion
setUnauthorizedHandler(() => {
  useAuthStore().logout()
  usePlacesStore().reset()
  router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } })
})

app.mount('#app')
