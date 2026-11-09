<script setup>
import { computed, ref } from 'vue'
import { api } from '../api/client'
import BottomSheet from '../components/BottomSheet.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { useAuthStore } from '../stores/auth'

// Page Amis : demandes reçues (accepter, refuser) et relations en cours (retirer, se désabonner, annuler)
const auth = useAuthStore()

const relationships = ref([])
const loading = ref(true)
const error = ref('')
const tab = ref('requests')
// Relation en cours de traitement : ses boutons sont désactivés
const busyId = ref(null)
// Suppression à confirmer : { relationship, title, text, action }
const confirm = ref(null)

const TYPE_LABELS = { follower: 'Abonnement', close_friend: 'Ami proche' }

async function load() {
  error.value = ''
  try {
    relationships.value = await api.list('/relationships')
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}
load()

const myId = computed(() => auth.user?.id)
const received = computed(() => relationships.value.filter((r) => r.addressee.id === myId.value))
const sent = computed(() => relationships.value.filter((r) => r.requester.id === myId.value))

const requests = computed(() => received.value.filter((r) => r.status === 'pending'))
const closeFriends = computed(() => received.value.filter((r) => r.status === 'accepted' && r.type === 'close_friend'))
const followers = computed(() => received.value.filter((r) => r.status === 'accepted' && r.type === 'follower'))

// Les sections de l'onglet « Mes relations » ; « Mes abonnements » regroupe les relations que j'ai envoyées
const sections = computed(() => [
  { key: 'close-friends', title: 'Amis proches', people: closeFriends.value.map((r) => ({ r, user: r.requester })), empty: 'Aucun ami proche pour l\'instant.' },
  { key: 'followers', title: 'Abonnés', people: followers.value.map((r) => ({ r, user: r.requester })), empty: 'Personne ne te suit encore.' },
  { key: 'sent', title: 'Mes abonnements', people: sent.value.map((r) => ({ r, user: r.addressee })), empty: 'Tu ne suis personne pour l\'instant.' },
])

// Après chaque action : la liste et les compteurs du profil (badge de la cloche) sont rechargés
async function run(relationship, action) {
  busyId.value = relationship.id
  error.value = ''
  try {
    await action()
    await Promise.all([load(), auth.fetchMe()])
  } catch (e) {
    error.value = e.message
  } finally {
    busyId.value = null
  }
}

const accept = (r) => run(r, () => api.patch(`/relationships/${r.id}`, { status: 'accepted' }))
const refuse = (r) => run(r, () => api.delete(`/relationships/${r.id}`))

function askRemove(r, user) {
  const name = user.username
  const mine = r.requester.id === myId.value
  if (mine) {
    confirm.value = r.status === 'pending'
      ? { r, title: 'Annuler la demande', text: `Annuler ta demande à ${name} ?`, action: 'Annuler la demande' }
      : { r, title: r.type === 'close_friend' ? 'Quitter les amis proches' : 'Se désabonner', text: `Tu ne verras plus ce que ${name} réserve à ses ${r.type === 'close_friend' ? 'amis proches' : 'abonnés'}.`, action: r.type === 'close_friend' ? 'Quitter' : 'Se désabonner' }
  } else {
    confirm.value = { r, title: 'Retirer', text: `Retirer ${name} de tes ${r.type === 'close_friend' ? 'amis proches' : 'abonnés'} ? Il ou elle ne sera pas prévenu·e.`, action: 'Retirer' }
  }
}

async function confirmRemove() {
  const r = confirm.value.r
  confirm.value = null
  await run(r, () => api.delete(`/relationships/${r.id}`))
}

function statusLabel(r, mine) {
  if (r.status === 'pending') return 'En attente'
  return mine ? TYPE_LABELS[r.type] : null
}
</script>

<template>
  <main class="screen">
    <h1 class="display page-title">Amis</h1>

    <div class="segmented" role="tablist" aria-label="Amis">
      <button type="button" role="tab" data-cy="friends-tab-requests" :aria-selected="tab === 'requests'" @click="tab = 'requests'">
        Demandes<template v-if="requests.length"> ({{ requests.length }})</template>
      </button>
      <button type="button" role="tab" data-cy="friends-tab-relations" :aria-selected="tab === 'relations'" @click="tab = 'relations'">
        Mes relations
      </button>
    </div>

    <p v-if="error" class="error" role="alert" data-cy="friends-error">{{ error }}</p>
    <p v-if="loading" class="loading">Chargement…</p>

    <!-- Demandes reçues en attente -->
    <section v-else-if="tab === 'requests'" class="stack" aria-label="Demandes reçues">
      <ul v-if="requests.length" class="person-list">
        <li v-for="r in requests" :key="r.id" class="person-row" data-cy="request-row">
          <UserAvatar :name="r.requester.username" :src="r.requester.avatar" :size="44" />
          <span class="person-row-text">
            <span class="person-row-name" data-cy="request-name">{{ r.requester.username }}</span>
            <span class="chips">
              <span class="chip" :class="r.type === 'close_friend' ? 'chip--wine' : 'chip--glaucous'" data-cy="request-type">
                {{ TYPE_LABELS[r.type] }}
              </span>
            </span>
          </span>
          <span class="person-row-actions">
            <button type="button" class="btn btn--primary btn--small" data-cy="request-accept" :disabled="busyId === r.id" @click="accept(r)">
              Accepter
            </button>
            <button type="button" class="btn btn--soft btn--small" data-cy="request-refuse" :disabled="busyId === r.id" @click="refuse(r)">
              Refuser
            </button>
          </span>
        </li>
      </ul>
      <div v-else class="empty-state" data-cy="requests-empty">
        <p>Aucune demande en attente.</p>
      </div>
    </section>

    <!-- Amis proches, abonnés, demandes envoyées -->
    <template v-else>
      <section v-for="section in sections" :key="section.key" class="stack stack--tight" :data-cy="`relations-${section.key}`">
        <h2 class="section-title">{{ section.title }}</h2>
        <ul v-if="section.people.length" class="person-list">
          <li v-for="{ r, user } in section.people" :key="r.id" class="person-row" data-cy="relation-row">
            <UserAvatar :name="user.username" :src="user.avatar" :size="44" />
            <span class="person-row-text">
              <span class="person-row-name" data-cy="relation-name">{{ user.username }}</span>
              <span v-if="statusLabel(r, section.key === 'sent')" class="small muted">{{ statusLabel(r, section.key === 'sent') }}</span>
            </span>
            <span class="person-row-actions">
              <button type="button" class="link-btn" data-cy="relation-remove" :disabled="busyId === r.id" @click="askRemove(r, user)">
                {{ section.key === 'sent' ? (r.status === 'pending' ? 'Annuler' : 'Ne plus suivre') : 'Retirer' }}
              </button>
            </span>
          </li>
        </ul>
        <p v-else class="small muted">{{ section.empty }}</p>
      </section>
    </template>

    <BottomSheet v-if="confirm" :title="confirm.title" @close="confirm = null">
      <div class="confirm-box" role="alert">
        <p>{{ confirm.text }}</p>
        <button type="button" class="btn btn--primary btn--block" data-cy="confirm-remove" @click="confirmRemove">{{ confirm.action }}</button>
        <button type="button" class="btn btn--soft btn--block" @click="confirm = null">Garder</button>
      </div>
    </BottomSheet>
  </main>
</template>
