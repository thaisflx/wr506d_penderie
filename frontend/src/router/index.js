import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import DressingView from '../views/DressingView.vue'
import HomeView from '../views/HomeView.vue'
import ItemView from '../views/ItemView.vue'
import LoginView from '../views/LoginView.vue'
import ProfileView from '../views/ProfileView.vue'
import RoomView from '../views/RoomView.vue'
import ScanView from '../views/ScanView.vue'

// meta.nav : onglet actif de la barre du bas (false = barre masquée)
const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: LoginView, meta: { nav: false } },
    { path: '/', name: 'home', component: HomeView, meta: { requiresAuth: true, nav: 'home' } },
    { path: '/rooms/:id', name: 'room', component: RoomView, props: true, meta: { requiresAuth: true, nav: 'home' } },
    { path: '/items/:id', name: 'item', component: ItemView, props: true, meta: { requiresAuth: true, nav: false } },
    { path: '/dressing', name: 'dressing', component: DressingView, meta: { requiresAuth: true, nav: 'dressing' } },
    { path: '/profile', name: 'profile', component: ProfileView, meta: { requiresAuth: true, nav: 'profile' } },
    { path: '/scan', name: 'scan', component: ScanView, meta: { requiresAuth: true, nav: false } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
  ],
  scrollBehavior: () => ({ top: 0 }),
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }
  if (to.name === 'login' && auth.isAuthenticated) {
    return { name: 'home' }
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
