<script setup>
import { RouterLink, RouterView, useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'

const auth = useAuthStore()
const router = useRouter()

function logout() {
  auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <header class="topbar">
    <RouterLink to="/" class="brand">Penderie</RouterLink>
    <div v-if="auth.user" class="topbar-user">
      <span>{{ auth.user.username }}</span>
      <button type="button" class="btn btn-ghost" @click="logout">Se déconnecter</button>
    </div>
  </header>
  <main class="page">
    <RouterView />
  </main>
</template>
