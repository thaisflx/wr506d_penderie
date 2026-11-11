<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import BottomSheet from '../components/BottomSheet.vue'
import DecoStar from '../components/DecoStar.vue'
import UserAvatar from '../components/UserAvatar.vue'

// Profil public d'un autre compte : pseudo, avatar, bio, et mon lien avec lui (suivre, ami proche, bloquer)
const props = defineProps({
  id: { type: String, required: true },
})
const router = useRouter()

const profile = ref(null)
const loading = ref(true)
const notFound = ref(false)
const error = ref('')
const busy = ref(false)
// Panneau ouvert : { kind: 'remove', type, title, text, action } ou { kind: 'block' }
const sheet = ref(null)

async function load() {
  error.value = ''
  try {
    profile.value = await api.get(`/users/${props.id}`)
    // Mon propre profil public : la page Profil le montre déjà
    if (profile.value.viewerRelationship.isMe) router.replace('/profile')
  } catch (e) {
    // Introuvable, suspendu ou bloqué : rien n'indique pourquoi
    if (e.status === 404) notFound.value = true
    else error.value = e.message
  } finally {
    loading.value = false
  }
}

watch(
  () => props.id,
  () => {
    loading.value = true
    notFound.value = false
    load()
  },
  { immediate: true },
)

const link = computed(() => profile.value?.viewerRelationship)

async function run(action) {
  busy.value = true
  error.value = ''
  try {
    await action()
    sheet.value = null
    await load()
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

function send(type) {
  return run(() => api.post('/relationships', { addressee: `/api/users/${props.id}`, type }))
}

// Annuler une demande ou quitter une relation acceptée : confirmation d'abord
function askRemove(type) {
  const state = link.value[type]
  const name = profile.value.username
  if (state.status === 'pending') {
    sheet.value = { kind: 'remove', type, title: 'Annuler la demande', text: `Annuler ta demande à ${name} ?`, action: 'Annuler la demande' }
  } else if (type === 'follower') {
    sheet.value = { kind: 'remove', type, title: 'Ne plus suivre', text: `Tu ne verras plus ce que ${name} réserve à ses abonnés.`, action: 'Ne plus suivre' }
  } else {
    sheet.value = { kind: 'remove', type, title: 'Quitter les amis proches', text: `Tu ne verras plus ce que ${name} réserve à ses amis proches.`, action: 'Quitter' }
  }
}

const confirmRemove = () => run(() => api.delete(`/relationships/${link.value[sheet.value.type].id}`))

async function block() {
  busy.value = true
  error.value = ''
  try {
    await api.post('/blocks', { blocked: `/api/users/${props.id}` })
    router.push('/friends')
  } catch (e) {
    error.value = e.message
  } finally {
    busy.value = false
  }
}

function openBlock() {
  error.value = ''
  sheet.value = { kind: 'block' }
}
</script>

<template>
  <div class="screen--flush profile-page">
    <!-- Même bandeau rose pâle festonné que mon profil -->
    <div class="profile-band scallop">
      <DecoStar class="profile-star profile-star--white" :size="18" color="var(--white)" />
      <DecoStar class="profile-star profile-star--wine" :size="22" color="var(--wine)" />
      <button type="button" class="icon-btn profile-band-btn profile-band-btn--start" aria-label="Retour" @click="router.back()">
        <AppIcon name="back" :stroke="2.4" />
      </button>
    </div>

    <main class="profile-content profile-public">
      <p v-if="loading" class="loading">Chargement…</p>

      <div v-else-if="notFound" class="empty-state" data-cy="profile-not-found">
        <p>Ce profil n'est pas disponible.</p>
        <RouterLink to="/friends" class="btn btn--soft">Retour aux amis</RouterLink>
      </div>

      <template v-else-if="profile">
        <span class="profile-avatar">
          <UserAvatar :name="profile.username" :src="profile.avatar" :size="96" />
        </span>
        <div class="profile-identity">
          <h1 class="display profile-name" data-cy="public-username">{{ profile.username }}</h1>
          <p v-if="profile.bio" class="muted profile-bio" data-cy="public-bio">{{ profile.bio }}</p>
        </div>

        <p v-if="error && !sheet" class="error" role="alert" data-cy="public-error">{{ error }}</p>

        <div class="profile-actions">
          <!-- Abonnement -->
          <button
            v-if="link.follower.status === 'none'"
            type="button"
            class="btn btn--primary"
            data-cy="follow"
            :disabled="busy"
            @click="send('follower')"
          >
            Suivre
          </button>
          <button v-else type="button" class="btn btn--soft" data-cy="follow" :disabled="busy" @click="askRemove('follower')">
            {{ link.follower.status === 'pending' ? 'Demande envoyée' : 'Abonné·e' }}
          </button>

          <!-- Ami proche -->
          <button
            v-if="link.closeFriend.status === 'none'"
            type="button"
            class="btn btn--soft"
            data-cy="close-friend"
            :disabled="busy"
            @click="send('close_friend')"
          >
            Demander en ami proche
          </button>
          <button v-else type="button" class="btn btn--soft" data-cy="close-friend" :disabled="busy" @click="askRemove('closeFriend')">
            {{ link.closeFriend.status === 'pending' ? 'Ami proche : en attente' : 'Ami·e proche' }}
          </button>
        </div>

        <button type="button" class="link-btn link-btn--danger profile-block" data-cy="block-open" @click="openBlock">
          Bloquer {{ profile.username }}
        </button>
      </template>
    </main>

    <BottomSheet v-if="sheet?.kind === 'remove'" :title="sheet.title" @close="sheet = null">
      <div class="confirm-box" role="alert">
        <p>{{ sheet.text }}</p>
        <p v-if="error" class="error">{{ error }}</p>
        <button type="button" class="btn btn--primary btn--block" data-cy="confirm-remove" :disabled="busy" @click="confirmRemove">{{ sheet.action }}</button>
        <button type="button" class="btn btn--soft btn--block" @click="sheet = null">Garder</button>
      </div>
    </BottomSheet>

    <BottomSheet v-if="sheet?.kind === 'block'" :title="`Bloquer ${profile.username}`" @close="sheet = null">
      <div class="confirm-box" role="alert">
        <p>Vos relations seront supprimées et vous ne pourrez plus vous voir, ni vous trouver. {{ profile.username }} ne sera pas prévenu·e.</p>
        <p v-if="error" class="error" data-cy="block-error">{{ error }}</p>
        <button type="button" class="btn btn--danger btn--block" data-cy="block-confirm" :disabled="busy" @click="block">Bloquer</button>
        <button type="button" class="btn btn--soft btn--block" @click="sheet = null">Annuler</button>
      </div>
    </BottomSheet>
  </div>
</template>
