<script setup>
import { computed, ref, watch } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import BottomSheet from '../components/BottomSheet.vue'
import DecoStar from '../components/DecoStar.vue'
import ItemPreviewSheet from '../components/ItemPreviewSheet.vue'
import OotdCard from '../components/OotdCard.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { useOotdFeed } from '../composables/useOotdFeed'
import { t } from '../i18n'

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

// Ses looks du jour publiés, avec la même règle de visibilité que le fil (GET /api/users/{id}/ootds)
const looks = useOotdFeed(() => `/users/${props.id}/ootds`)
const { ootds, loading: looksLoading, loadingMore, hasMore, loadMore, relationOf } = looks
const preview = ref(null)

async function load() {
  error.value = ''
  try {
    profile.value = await api.get(`/users/${props.id}`)
    // Mon propre profil public : la page Profil le montre déjà
    if (profile.value.viewerRelationship.isMe) router.replace('/profile')
    else looks.load()
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
    sheet.value = { kind: 'remove', type, title: t('friends.confirm.cancel_title'), text: t('friends.confirm.cancel_text', { name }), action: t('friends.confirm.cancel_title') }
  } else if (type === 'follower') {
    sheet.value = { kind: 'remove', type, title: t('friends.unfollow'), text: t('public.unfollow_text', { name }), action: t('friends.unfollow') }
  } else {
    sheet.value = { kind: 'remove', type, title: t('friends.confirm.leave_title'), text: t('friends.confirm.leave_text', { name }), action: t('friends.confirm.leave_action') }
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
      <button type="button" class="icon-btn profile-band-btn profile-band-btn--start" :aria-label="$t('common.back')" @click="router.back()">
        <AppIcon name="back" :stroke="2.4" />
      </button>
    </div>

    <main class="profile-content profile-public">
      <p v-if="loading" class="loading">{{ $t('common.loading') }}</p>

      <div v-else-if="notFound" class="empty-state" data-cy="profile-not-found">
        <p>{{ $t('public.not_available') }}</p>
        <RouterLink to="/friends" class="btn btn--soft">{{ $t('public.back_to_friends') }}</RouterLink>
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
            {{ $t('public.follow') }}
          </button>
          <button v-else type="button" class="btn btn--soft" data-cy="follow" :disabled="busy" @click="askRemove('follower')">
            {{ link.follower.status === 'pending' ? $t('public.request_sent') : $t('public.following') }}
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
            {{ $t('public.ask_close_friend') }}
          </button>
          <button v-else type="button" class="btn btn--soft" data-cy="close-friend" :disabled="busy" @click="askRemove('closeFriend')">
            {{ link.closeFriend.status === 'pending' ? $t('public.close_friend_pending') : $t('public.close_friend') }}
          </button>
        </div>

        <button type="button" class="link-btn link-btn--danger profile-block" data-cy="block-open" @click="openBlock">
          {{ $t('public.block_name', { name: profile.username }) }}
        </button>

        <section class="stack profile-looks" aria-labelledby="public-looks-title" data-cy="public-looks">
          <h2 id="public-looks-title" class="section-title">{{ $t('public.looks') }}</h2>
          <p v-if="looksLoading" class="loading">{{ $t('common.loading') }}</p>
          <p v-else-if="!ootds.length" class="small muted" data-cy="public-looks-empty">{{ $t('public.no_looks') }}</p>
          <template v-else>
            <div class="ootd-list">
              <OotdCard
                v-for="ootd in ootds"
                :key="ootd.id"
                :ootd="ootd"
                :relation="relationOf(ootd)"
                hide-author
                @preview-item="preview = $event"
              />
            </div>
            <button v-if="hasMore" type="button" class="btn btn--block" :disabled="loadingMore" @click="loadMore">
              {{ loadingMore ? $t('common.loading') : $t('common.see_more') }}
            </button>
          </template>
        </section>
      </template>
    </main>

    <ItemPreviewSheet v-if="preview" :item="preview" @close="preview = null" />

    <BottomSheet v-if="sheet?.kind === 'remove'" :title="sheet.title" @close="sheet = null">
      <div class="confirm-box" role="alert">
        <p>{{ sheet.text }}</p>
        <p v-if="error" class="error">{{ error }}</p>
        <button type="button" class="btn btn--primary btn--block" data-cy="confirm-remove" :disabled="busy" @click="confirmRemove">{{ sheet.action }}</button>
        <button type="button" class="btn btn--soft btn--block" @click="sheet = null">{{ $t('look.keep') }}</button>
      </div>
    </BottomSheet>

    <BottomSheet v-if="sheet?.kind === 'block'" :title="$t('public.block_name', { name: profile.username })" @close="sheet = null">
      <div class="confirm-box" role="alert">
        <p>{{ $t('public.block_warning', { name: profile.username }) }}</p>
        <p v-if="error" class="error" data-cy="block-error">{{ error }}</p>
        <button type="button" class="btn btn--danger btn--block" data-cy="block-confirm" :disabled="busy" @click="block">{{ $t('public.block') }}</button>
        <button type="button" class="btn btn--soft btn--block" @click="sheet = null">{{ $t('common.cancel') }}</button>
      </div>
    </BottomSheet>
  </div>
</template>
