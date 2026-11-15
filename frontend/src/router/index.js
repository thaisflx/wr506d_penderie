import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import BlockedView from '../views/BlockedView.vue'
import FeedView from '../views/FeedView.vue'
import FriendsView from '../views/FriendsView.vue'
import ItemView from '../views/ItemView.vue'
import ItemsView from '../views/ItemsView.vue'
import LoginView from '../views/LoginView.vue'
import MyDressingView from '../views/MyDressingView.vue'
import PostLookView from '../views/PostLookView.vue'
import ProfileView from '../views/ProfileView.vue'
import PublicProfileView from '../views/PublicProfileView.vue'
import RoomView from '../views/RoomView.vue'
import ScanView from '../views/ScanView.vue'

// meta.nav : onglet actif de la barre du bas (false = barre masquée).
// Navigation du lot 2 (docs/decisions.md) : Accueil = fil des looks du jour, Mon dressing = logement et pièces.
const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/login', name: 'login', component: LoginView, meta: { nav: false } },
    { path: '/', name: 'home', component: FeedView, meta: { requiresAuth: true, nav: 'home' } },
    { path: '/dressing', name: 'dressing', component: MyDressingView, meta: { requiresAuth: true, nav: 'dressing' } },
    { path: '/dressing/objets', name: 'items', component: ItemsView, meta: { requiresAuth: true, nav: 'dressing' } },
    { path: '/rooms/:id', name: 'room', component: RoomView, props: true, meta: { requiresAuth: true, nav: 'dressing' } },
    { path: '/items/:id', name: 'item', component: ItemView, props: true, meta: { requiresAuth: true, nav: false } },
    { path: '/friends', name: 'friends', component: FriendsView, meta: { requiresAuth: true, nav: 'friends' } },
    { path: '/users/:id', name: 'user', component: PublicProfileView, props: true, meta: { requiresAuth: true, nav: 'friends' } },
    { path: '/blocked', name: 'blocked', component: BlockedView, meta: { requiresAuth: true, nav: 'profile' } },
    { path: '/profile', name: 'profile', component: ProfileView, meta: { requiresAuth: true, nav: 'profile' } },
    { path: '/scan', name: 'scan', component: ScanView, meta: { requiresAuth: true, nav: false } },
    { path: '/looks/nouveau', name: 'look-new', component: PostLookView, meta: { requiresAuth: true, nav: false } },
    { path: '/looks/:id/modifier', name: 'look-edit', component: PostLookView, props: true, meta: { requiresAuth: true, nav: false } },
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
