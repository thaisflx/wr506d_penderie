<script setup>
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppIcon from '../components/AppIcon.vue'
import AppLogo from '../components/AppLogo.vue'
import { LOCALES, currentLocale, setLocale, t } from '../i18n'
import { useAuthStore } from '../stores/auth'

const auth = useAuthStore()
const route = useRoute()
const router = useRouter()

const tab = ref('login')
const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)
// Langue avant la connexion : gardée dans le navigateur et envoyée à l'API ; une fois connecté, celle du compte
const locale = ref(currentLocale())

async function submit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    router.push(route.query.redirect || '/')
  } catch (e) {
    // Reconnu par le code HTTP, jamais par le texte (traduit) : 401 = identifiants refusés ou compte suspendu, et
    // l'API renvoie alors son message dans la langue choisie ; sinon, un message générique
    error.value = e.status === 401 ? e.message : t('login.error_generic')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <main class="auth">
    <section class="auth-brand">
      <h1 class="auth-title"><AppLogo /></h1>
    </section>

    <section class="auth-panel">
      <label class="field auth-language">
        <span class="field-label">{{ $t('language.label') }}</span>
        <select v-model="locale" class="input" data-cy="login-locale" @change="setLocale(locale)">
          <option v-for="code in LOCALES" :key="code" :value="code" :lang="code">{{ $t(`language.${code}`) }}</option>
        </select>
      </label>

      <div class="segmented segmented--large" role="tablist" :aria-label="$t('login.tabs')">
        <button type="button" role="tab" :aria-selected="tab === 'login'" @click="tab = 'login'">{{ $t('login.tab_login') }}</button>
        <button type="button" role="tab" :aria-selected="tab === 'signup'" @click="tab = 'signup'">{{ $t('login.tab_signup') }}</button>
      </div>

      <form v-if="tab === 'login'" class="form form--loose" data-cy="login-form" @submit.prevent="submit">
        <div class="form">
          <label class="field">
            <span class="field-label">{{ $t('login.email') }}</span>
            <span class="input-wrap">
              <span class="input-icon"><AppIcon name="mail" /></span>
              <input
                v-model="email"
                data-cy="login-email"
                class="input input--with-icon"
                type="email"
                autocomplete="username"
                :placeholder="$t('login.email_placeholder')"
                required
              />
            </span>
          </label>
          <label class="field">
            <span class="field-label">{{ $t('login.password') }}</span>
            <span class="input-wrap">
              <span class="input-icon"><AppIcon name="lock" /></span>
              <input
                v-model="password"
                data-cy="login-password"
                class="input input--with-icon"
                type="password"
                autocomplete="current-password"
                placeholder="••••••••"
                required
              />
            </span>
          </label>
        </div>
        <p v-if="error" class="error" role="alert" data-cy="login-error">{{ error }}</p>
        <button type="submit" class="btn btn--primary btn--large btn--block" data-cy="login-submit" :disabled="loading">
          {{ loading ? $t('login.signing_in') : $t('login.submit') }}
          <AppIcon v-if="!loading" name="arrow" :stroke="2.4" />
        </button>
      </form>

      <!-- L'inscription n'a pas encore d'endpoint côté API -->
      <div v-else class="empty-state" role="tabpanel">
        <p><strong>{{ $t('login.signup_soon') }}</strong></p>
        <p>{{ $t('login.signup_meanwhile') }}</p>
        <button type="button" class="btn btn--soft" @click="tab = 'login'">{{ $t('login.go_to_login') }}</button>
      </div>
    </section>
  </main>
</template>
