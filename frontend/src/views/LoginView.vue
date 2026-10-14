<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const tab = ref('login')
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
  <main class="auth">
    <section class="auth-brand">
      <h1 class="display auth-title">Penderie</h1>
    </section>

    <section class="auth-panel">
      <div class="segmented segmented--large" role="tablist">
        <button type="button" role="tab" :aria-selected="tab === 'login'" @click="tab = 'login'">Connexion</button>
        <button type="button" role="tab" :aria-selected="tab === 'signup'" @click="tab = 'signup'">Inscription</button>
      </div>

      <form v-if="tab === 'login'" class="form form--loose" @submit.prevent="submit">
        <div class="form">
          <label class="field">
            <span class="field-label">Email</span>
            <span class="input-wrap">
              <span class="input-icon"><AppIcon name="mail" /></span>
              <input
                v-model="email"
                class="input input--with-icon"
                type="email"
                autocomplete="username"
                placeholder="toi@exemple.fr"
                required
              />
            </span>
          </label>
          <label class="field">
            <span class="field-label">Mot de passe</span>
            <span class="input-wrap">
              <span class="input-icon"><AppIcon name="lock" /></span>
              <input
                v-model="password"
                class="input input--with-icon"
                type="password"
                autocomplete="current-password"
                placeholder="••••••••"
                required
              />
            </span>
          </label>
        </div>
        <p v-if="error" class="error" role="alert">{{ error }}</p>
        <button type="submit" class="btn btn--primary btn--large btn--block" :disabled="loading">
          {{ loading ? 'Connexion…' : 'Me connecter' }}
          <AppIcon v-if="!loading" name="arrow" :stroke="2.4" />
        </button>
      </form>

      <!-- L'inscription n'a pas encore d'endpoint côté API -->
      <div v-else class="empty-state" role="tabpanel">
        <p><strong>L'inscription arrive bientôt.</strong></p>
        <p>En attendant, connecte-toi avec un compte de test.</p>
        <button type="button" class="btn btn--soft" @click="tab = 'login'">Aller à la connexion</button>
      </div>
    </section>
  </main>
</template>
