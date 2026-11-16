<script setup>
import { computed, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { api } from '../api/client'
import AppIcon from '../components/AppIcon.vue'
import BottomSheet from '../components/BottomSheet.vue'
import DecoStar from '../components/DecoStar.vue'
import OotdCard from '../components/OotdCard.vue'
import UserAvatar from '../components/UserAvatar.vue'
import { LOCALES, currentLocale } from '../i18n'
import { useAuthStore } from '../stores/auth'
import { formatDate } from '../utils/relativeTime'
import { usePlacesStore } from '../stores/places'

const auth = useAuthStore()
const placesStore = usePlacesStore()
const router = useRouter()

// Onglets « Mes looks » (publiés) et « Brouillons » (visibles par moi seul). Les objets en vente ou prêtés
// restent dans Dressing → Tous mes objets (docs/decisions.md, lot 2).
const looks = ref([])
const loading = ref(true)
const error = ref('')
const tab = ref('looks')
const settingsOpen = ref(false)

// Profil à jour (compteurs) et tous mes looks, brouillons compris (GET /api/me/ootds, non paginé)
Promise.all([auth.fetchMe(), api.list('/me/ootds')])
  .then(([, list]) => (looks.value = list))
  .catch((e) => (error.value = e.message))
  .finally(() => (loading.value = false))

const user = computed(() => auth.user)
// Publiés : du plus récent au plus ancien, comme dans le fil ; brouillons : derniers commencés en tête (ordre de l'API)
const published = computed(() =>
  looks.value.filter((ootd) => ootd.published).sort((a, b) => b.publishedAt.localeCompare(a.publishedAt)),
)
const drafts = computed(() => looks.value.filter((ootd) => !ootd.published))
// Mon look supprimé : il disparaît de la liste sans recharger
const removeLook = (id) => (looks.value = looks.value.filter((ootd) => ootd.id !== id))

const TONES = ['tone-glaucous', 'tone-sky', 'tone-peach']
const draftDate = (ootd) => formatDate(`${ootd.wornOn}T12:00:00`, { day: 'numeric', month: 'short' })

// Langue du compte : enregistrée par l'API (PATCH /api/me), puis appliquée à l'interface par fetchMe()
const locale = ref(currentLocale())
const localeSaved = ref(false)
const localeError = ref('')
async function changeLocale() {
  localeSaved.value = false
  localeError.value = ''
  try {
    await api.patch('/me', { locale: locale.value })
    await auth.fetchMe()
    localeSaved.value = true
  } catch (e) {
    localeError.value = e.message
  }
}

function logout() {
  auth.logout()
  placesStore.reset()
  router.push({ name: 'login' })
}
</script>

<template>
  <div class="screen--flush profile-page">
    <!-- Bandeau rose pâle festonné -->
    <div class="profile-band scallop">
      <DecoStar class="profile-star profile-star--white" :size="18" color="var(--white)" />
      <DecoStar class="profile-star profile-star--wine" :size="22" color="var(--wine)" />
      <button type="button" class="icon-btn profile-band-btn" :aria-label="$t('profile.settings')" data-cy="profile-settings" @click="settingsOpen = true">
        <AppIcon name="settings" />
      </button>
    </div>

    <div v-if="user" class="profile-content profile-layout">
      <aside class="profile-aside">
        <span class="profile-avatar">
          <UserAvatar :name="user.username" :src="user.avatar" :size="96" />
        </span>

        <div class="profile-identity">
          <h1 class="display profile-name">{{ user.username }}</h1>
          <p v-if="user.bio" class="profile-bio">{{ user.bio }}</p>
          <p class="small muted">{{ user.email }}</p>
        </div>

        <div class="stat-grid">
          <div class="card stat">
            <span class="display stat-number">{{ user.itemCount ?? 0 }}</span>
            <span class="stat-label">{{ $t('profile.stat_items', user.itemCount ?? 0) }}</span>
          </div>
          <div class="card stat">
            <span class="display stat-number">{{ user.followerCount ?? 0 }}</span>
            <span class="stat-label">{{ $t('profile.stat_followers', user.followerCount ?? 0) }}</span>
          </div>
          <div class="card stat">
            <span class="display stat-number">{{ user.closeFriendCount ?? 0 }}</span>
            <span class="stat-label">{{ $t('profile.stat_close_friends', user.closeFriendCount ?? 0) }}</span>
          </div>
        </div>

        <RouterLink to="/espace" class="btn btn--soft btn--block" data-cy="profile-my-space">
          <AppIcon name="box" :size="18" /> {{ $t('space.title') }}
        </RouterLink>

        <!-- Bandeau des demandes reçues : ouvre la page Amis -->
        <RouterLink v-if="user.pendingRequestCount" to="/friends" class="notice notice--link notice--request" data-cy="profile-requests-notice">
          <span class="notice-text">
            <span class="notice-count">{{ user.pendingRequestCount }}</span> {{ $t('profile.pending_requests', user.pendingRequestCount) }}
          </span>
          <AppIcon name="next" :size="18" :stroke="2.2" />
        </RouterLink>

        <section v-if="user.children?.length" class="stack stack--tight">
          <h2 class="section-title">{{ $t('profile.children') }}</h2>
          <ul class="children-list">
            <li v-for="child in user.children" :key="child.id" class="child">
              <UserAvatar :name="child.username" :src="child.avatar" :size="56" />
              {{ child.username }}
              <span v-if="child.isMinor" class="small muted child-age">-18</span>
            </li>
          </ul>
        </section>
      </aside>

      <section class="profile-main">
        <div class="pills profile-tabs" role="tablist" :aria-label="$t('profile.tabs')">
          <button
            id="profile-tab-looks"
            type="button"
            class="pill"
            role="tab"
            aria-controls="profile-panel"
            :aria-selected="tab === 'looks'"
            :aria-pressed="tab === 'looks'"
            data-cy="profile-tab-looks"
            @click="tab = 'looks'"
          >{{ $t('profile.tab_looks') }}</button>
          <button
            id="profile-tab-drafts"
            type="button"
            class="pill"
            role="tab"
            aria-controls="profile-panel"
            :aria-selected="tab === 'drafts'"
            :aria-pressed="tab === 'drafts'"
            data-cy="profile-tab-drafts"
            @click="tab = 'drafts'"
          >{{ $t('profile.tab_drafts') }}<template v-if="!loading"> · {{ drafts.length }}</template></button>
        </div>

        <div id="profile-panel" role="tabpanel" :aria-labelledby="`profile-tab-${tab}`" class="stack">
          <p v-if="loading" class="loading">{{ $t('common.loading') }}</p>
          <p v-else-if="error" class="error" role="alert">{{ error }}</p>

          <!-- Mes looks publiés : mes objets ouvrent leur fiche -->
          <template v-else-if="tab === 'looks'">
            <div v-if="published.length" class="ootd-list" data-cy="profile-looks">
              <OotdCard v-for="ootd in published" :key="ootd.id" :ootd="ootd" mine hide-author @deleted="removeLook" />
            </div>
            <div v-else class="empty-state" data-cy="profile-looks-empty">
              <p>{{ $t('profile.no_published') }}</p>
              <RouterLink to="/looks/nouveau" class="btn btn--primary btn--small">
                <AppIcon name="plus" :size="16" :stroke="2.4" /> {{ $t('look.post_mine') }}
              </RouterLink>
            </div>
          </template>

          <!-- Brouillons : visibles par moi seule, s'ouvrent pour être repris -->
          <template v-else>
            <div v-if="drafts.length" class="draft-grid">
              <RouterLink
                v-for="(ootd, index) in drafts"
                :key="ootd.id"
                :to="`/looks/${ootd.id}/modifier`"
                class="card draft-card"
                data-cy="draft-card"
              >
                <span class="draft-card-media scallop" :class="ootd.photo ? '' : TONES[index % TONES.length]">
                  <img v-if="ootd.photo" :src="ootd.photo" alt="" loading="lazy" />
                  <AppIcon v-else name="camera" :size="30" :stroke="1.8" />
                  <span class="chip chip--white draft-card-chip">{{ $t('profile.draft') }}</span>
                </span>
                <span class="draft-card-text">
                  <span class="draft-card-caption">{{ ootd.caption || $t('profile.no_caption') }}</span>
                  <span class="small muted">{{ draftDate(ootd) }} · {{ $t('count.items', ootd.wornItems.length) }}</span>
                </span>
              </RouterLink>
            </div>
            <div v-else class="empty-state" data-cy="profile-drafts-empty">
              <p>{{ $t('profile.no_drafts') }}</p>
            </div>
          </template>
        </div>
      </section>
    </div>

    <BottomSheet v-if="settingsOpen" :title="$t('profile.settings')" @close="settingsOpen = false">
      <!-- Langue enregistrée sur le compte : elle suit la personne sur tous ses appareils -->
      <label class="field">
        <span class="field-label">{{ $t('profile.language') }}</span>
        <select v-model="locale" class="input" data-cy="settings-locale" @change="changeLocale">
          <option v-for="code in LOCALES" :key="code" :value="code" :lang="code">{{ $t(`language.${code}`) }}</option>
        </select>
      </label>
      <p v-if="localeSaved" class="small muted" role="status" data-cy="settings-locale-saved">{{ $t('profile.language_saved') }}</p>
      <p v-if="localeError" class="error" role="alert">{{ localeError }}</p>
      <RouterLink to="/blocked" class="btn btn--soft btn--large btn--block" data-cy="settings-blocked">
        <AppIcon name="lock" /> {{ $t('profile.blocked_accounts') }}
      </RouterLink>
      <button type="button" class="btn btn--primary btn--large btn--block" data-cy="logout" @click="logout">
        <AppIcon name="logout" /> {{ $t('profile.logout') }}
      </button>
    </BottomSheet>
  </div>
</template>
