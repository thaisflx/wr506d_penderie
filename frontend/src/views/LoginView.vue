<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    router.push(route.query.redirect || '/')
  } catch (e) {
    error.value = e.status === 401 ? 'Email ou mot de passe incorrect.' : e.message
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="login">
    <h1>Connexion</h1>
    <p class="muted">Range, retrouve et partage tes objets.</p>
    <form class="form" @submit.prevent="submit">
      <label class="field">
        <span>Email</span>
        <input v-model="email" type="email" autocomplete="username" required />
      </label>
      <label class="field">
        <span>Mot de passe</span>
        <input v-model="password" type="password" autocomplete="current-password" required />
      </label>
      <p v-if="error" class="error" role="alert">{{ error }}</p>
      <button type="submit" class="btn btn-primary" :disabled="loading">
        {{ loading ? 'Connexion…' : 'Se connecter' }}
      </button>
    </form>
  </section>
</template>
