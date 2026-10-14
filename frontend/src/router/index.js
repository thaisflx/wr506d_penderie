import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import LevelView from '../views/LevelView.vue'
import LoginView from '../views/LoginView.vue'

// Chaque page affiche les enfants d'un élément : /places/1 liste les pièces du logement 1, etc.
const levelRoute = (path, name, level) => ({
  path,
  name,
  component: LevelView,
  props: (route) => ({ level, parentId: route.params.id ?? null }),
  meta: { requiresAuth: true },
})

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: LoginView },
    levelRoute('/', 'places', 'place'),
    levelRoute('/places/:id', 'place', 'room'),
    levelRoute('/rooms/:id', 'room', 'storage'),
    levelRoute('/storages/:id', 'storage', 'category'),
    levelRoute('/categories/:id', 'category', 'item'),
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'places' }
  }
  // Recharge le profil après un rafraîchissement de la page
  if (auth.isAuthenticated && !auth.user) {
    try {
      await auth.fetchMe()
    } catch {
      auth.logout()
      return { name: 'login', query: { redirect: to.fullPath } }
    }
  }
})

export default router
