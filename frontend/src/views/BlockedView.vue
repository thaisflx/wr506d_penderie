<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import BottomSheet from '../components/BottomSheet.vue'
import UserAvatar from '../components/UserAvatar.vue'

// Comptes que j'ai bloqués : le seul endroit où je les vois encore, pour les débloquer
const router = useRouter()

const blocks = ref([])
const loading = ref(true)
const error = ref('')
const confirm = ref(null)

async function load() {
  error.value = ''
  try {
    blocks.value = await api.list('/blocks')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
load()

async function unblock() {
  const block = confirm.value
  confirm.value = null
  try {
    await api.delete(`/blocks/${block.id}`)
    await load()
  } catch (e) {
    error.value = e.message
  }
}
</script>

<template>
  <main class="screen">
    <div class="page-head">
      <button type="button" class="icon-btn icon-btn--solid" aria-label="Retour" @click="router.back()">
        <AppIcon name="back" :stroke="2.6" />
      </button>
      <h1 class="display page-title">Comptes bloqués</h1>
    </div>

    <p v-if="error" class="error" role="alert">{{ error }}</p>
    <p v-if="loading" class="loading">Chargement…</p>
    <ul v-else-if="blocks.length" class="person-list">
      <li v-for="block in blocks" :key="block.id" class="person-row" data-cy="blocked-row">
        <UserAvatar :name="block.blocked.username" :src="block.blocked.avatar" :size="44" />
        <span class="person-row-text">
          <span class="person-row-name" data-cy="blocked-name">{{ block.blocked.username }}</span>
        </span>
        <span class="person-row-actions">
          <button type="button" class="btn btn--soft btn--small" data-cy="unblock" @click="confirm = block">Débloquer</button>
        </span>
      </li>
    </ul>
    <div v-else class="empty-state" data-cy="blocked-empty">
      <p>Tu n'as bloqué personne.</p>
    </div>

    <BottomSheet v-if="confirm" :title="`Débloquer ${confirm.blocked.username}`" @close="confirm = null">
      <div class="confirm-box" role="alert">
        <p>Vous pourrez de nouveau vous voir et vous trouver. Vos anciennes relations ne seront pas rétablies.</p>
        <button type="button" class="btn btn--primary btn--block" data-cy="unblock-confirm" @click="unblock">Débloquer</button>
        <button type="button" class="btn btn--soft btn--block" @click="confirm = null">Annuler</button>
      </div>
    </BottomSheet>
  </main>
</template>
