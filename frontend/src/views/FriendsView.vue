<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import BottomSheet from '../components/BottomSheet.vue'
import DecoStar from '../components/DecoStar.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { t } from '../i18n'
import { useAuthStore } from '../stores/auth'

// Page Amis : demandes reçues (accepter, refuser), relations en cours (retirer, se désabonner, annuler)
// et recherche d'un compte par pseudo
const auth = useAuthStore()

const relationships = ref([])
const loading = ref(true)
const error = ref('')
// Onglet ouvert : ?tab=search depuis le lien « Trouver des amis » du fil
const TABS = ['requests', 'relations', 'search']
const route = useRoute()
const tab = ref(TABS.includes(route.query.tab) ? route.query.tab : 'requests')
// Relation en cours de traitement : ses boutons sont désactivés
const busyId = ref(null)
// Suppression à confirmer : { relationship, title, text, action }
const confirm = ref(null)

// Phrase de la carte de demande (« veut s'abonner à toi ») : début, verbe en gras, fin, selon le type et la langue

// Recherche : lancée 300 ms après la dernière frappe, à partir de 2 caractères (comme l'API)
const search = ref('')
const results = ref([])
const searching = ref(false)
let searchTimer = null

watch(search, (value) => {
  clearTimeout(searchTimer)
  const query = value.trim()
  if (query.length < 2) {
    results.value = []
    searching.value = false
    return
  }
  searching.value = true
  searchTimer = setTimeout(async () => {
    try {
      results.value = await api.list(`/users?username=${encodeURIComponent(query)}`)
      error.value = ''
    } catch (e) {
      error.value = e.message
    } finally {
      searching.value = false
    }
  }, 300)
})

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
  { key: 'close-friends', title: t('friends.section.close_friends'), people: closeFriends.value.map((r) => ({ r, user: r.requester })), empty: t('friends.section.close_friends_empty') },
  { key: 'followers', title: t('friends.section.followers'), people: followers.value.map((r) => ({ r, user: r.requester })), empty: t('friends.section.followers_empty') },
  { key: 'sent', title: t('friends.section.sent'), people: sent.value.map((r) => ({ r, user: r.addressee })), empty: t('friends.section.sent_empty') },
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
      ? { r, title: t('friends.confirm.cancel_title'), text: t('friends.confirm.cancel_text', { name }), action: t('friends.confirm.cancel_title') }
      : r.type === 'close_friend'
        ? { r, title: t('friends.confirm.leave_title'), text: t('friends.confirm.leave_text', { name }), action: t('friends.confirm.leave_action') }
        : { r, title: t('friends.confirm.unfollow_title'), text: t('friends.confirm.unfollow_text', { name }), action: t('friends.confirm.unfollow_title') }
  } else {
    const text = r.type === 'close_friend' ? 'friends.confirm.remove_close_friend_text' : 'friends.confirm.remove_follower_text'
    confirm.value = { r, title: t('friends.confirm.remove_title'), text: t(text, { name }), action: t('friends.confirm.remove_title') }
  }
}

async function confirmRemove() {
  const r = confirm.value.r
  confirm.value = null
  await run(r, () => api.delete(`/relationships/${r.id}`))
}

function statusLabel(r, mine) {
  if (r.status === 'pending') return t('friends.pending')
  return mine ? t(`friends.type.${r.type}`) : null
}
</script>

<template>
  <main class="screen friends">
    <div class="page-intro">
      <h1 class="display page-title">{{ $t('friends.title') }}</h1>
      <DecoStar class="page-intro-star" :size="22" />
      <p class="page-intro-text">{{ $t('friends.intro') }}</p>
    </div>

    <div class="segmented" role="tablist" :aria-label="$t('friends.tabs')">
      <button type="button" role="tab" data-cy="friends-tab-requests" :aria-selected="tab === 'requests'" @click="tab = 'requests'">
        {{ $t('friends.tab_requests') }}<template v-if="requests.length"> · {{ requests.length }}</template>
      </button>
      <button type="button" role="tab" data-cy="friends-tab-relations" :aria-selected="tab === 'relations'" @click="tab = 'relations'">
        {{ $t('friends.tab_relations') }}
      </button>
      <button type="button" role="tab" data-cy="friends-tab-search" :aria-selected="tab === 'search'" @click="tab = 'search'">
        {{ $t('friends.tab_search') }}
      </button>
    </div>

    <p v-if="error" class="error" role="alert" data-cy="friends-error">{{ error }}</p>
    <!-- Recherche par pseudo -->
    <section v-if="tab === 'search'" class="stack" :aria-label="$t('friends.search_section')">
      <div role="search">
        <label for="friends-search" class="visually-hidden">{{ $t('friends.search_label') }}</label>
        <span class="input-wrap">
          <span class="input-icon input-icon--search"><AppIcon name="search" :stroke="2.2" /></span>
          <input
            id="friends-search"
            v-model="search"
            class="input input--pill input--search"
            type="search"
            :placeholder="$t('friends.search_placeholder')"
            autocomplete="off"
            data-cy="friends-search"
          />
        </span>
      </div>
      <p v-if="search.trim().length < 2" class="small muted">{{ $t('friends.search_min') }}</p>
      <p v-else-if="searching" class="loading">{{ $t('friends.searching') }}</p>
      <ul v-else-if="results.length" class="person-list">
        <li v-for="user in results" :key="user.id">
          <RouterLink :to="`/users/${user.id}`" class="person-row person-row--link" data-cy="search-result">
            <UserAvatar :name="user.username" :src="user.avatar" :size="44" />
            <span class="person-row-text">
              <span class="person-row-name" data-cy="search-name">{{ user.username }}</span>
              <span v-if="user.bio" class="small muted">{{ user.bio }}</span>
            </span>
            <AppIcon name="next" :size="16" :stroke="2.2" />
          </RouterLink>
        </li>
      </ul>
      <p v-else class="small muted" data-cy="search-empty">{{ $t('friends.no_result') }}</p>
    </section>

    <p v-else-if="loading" class="loading">{{ $t('common.loading') }}</p>

    <!-- Demandes reçues en attente -->
    <section v-else-if="tab === 'requests'" class="stack" :aria-label="$t('friends.requests_section')">
      <ul v-if="requests.length" class="person-list">
        <li v-for="r in requests" :key="r.id" class="card request-card" data-cy="request-row">
          <div class="request-card-head">
            <UserAvatar :name="r.requester.username" :src="r.requester.avatar" :size="52" flower />
            <span class="person-row-text">
              <RouterLink :to="`/users/${r.requester.id}`" class="person-row-name person-row-name--link" data-cy="request-name">{{ r.requester.username }}</RouterLink>
              <span class="small muted">{{ $t(`friends.request.${r.type}.before`) }} <strong class="request-verb">{{ $t(`friends.request.${r.type}.verb`) }}</strong>{{ $t(`friends.request.${r.type}.after`) }}</span>
            </span>
            <span class="chip" :class="r.type === 'close_friend' ? 'chip--peach' : 'chip--glaucous'" data-cy="request-type">
              {{ $t(`friends.type.${r.type}`) }}
            </span>
          </div>
          <div class="request-card-actions">
            <button type="button" class="btn btn--primary" data-cy="request-accept" :disabled="busyId === r.id" @click="accept(r)">
              {{ $t('friends.accept') }}
            </button>
            <button type="button" class="btn" data-cy="request-refuse" :disabled="busyId === r.id" @click="refuse(r)">
              {{ $t('friends.refuse') }}
            </button>
          </div>
        </li>
      </ul>
      <div v-else class="empty-state" data-cy="requests-empty">
        <p>{{ $t('friends.no_request') }}</p>
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
              <RouterLink :to="`/users/${user.id}`" class="person-row-name person-row-name--link" data-cy="relation-name">{{ user.username }}</RouterLink>
              <span v-if="statusLabel(r, section.key === 'sent')" class="small muted">{{ statusLabel(r, section.key === 'sent') }}</span>
            </span>
            <span class="person-row-actions">
              <button type="button" class="btn btn--soft btn--small" data-cy="relation-remove" :disabled="busyId === r.id" @click="askRemove(r, user)">
                {{ section.key === 'sent' ? (r.status === 'pending' ? $t('common.cancel') : $t('friends.unfollow')) : $t('friends.confirm.remove_title') }}
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
        <button type="button" class="btn btn--soft btn--block" @click="confirm = null">{{ $t('look.keep') }}</button>
      </div>
    </BottomSheet>
  </main>
</template>
