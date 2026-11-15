import { computed, ref } from 'vue'
import { api, request } from '../api/client'
import { useAuthStore } from '../stores/auth'

// Liste paginée de looks du jour (fil ou profil public) : 10 par page, « Voir plus » charge la suivante.
// Calcule aussi mon lien avec chaque auteur (« Ami proche » l'emporte sur « Abonné ») à partir de mes relations.
export function useOotdFeed(firstPage) {
  const auth = useAuthStore()

  const ootds = ref([])
  const nextPage = ref(null)
  const loading = ref(true)
  const loadingMore = ref(false)
  const error = ref('')
  const links = ref({})

  async function loadPage(path) {
    const page = await request(path)
    ootds.value = [...ootds.value, ...(page?.member ?? [])]
    nextPage.value = page?.view?.next ?? null
  }

  async function load() {
    loading.value = true
    error.value = ''
    ootds.value = []
    try {
      const [, relationships] = await Promise.all([loadPage(firstPage()), api.list('/relationships')])
      const mine = relationships.filter((r) => r.requester.id === auth.user?.id && r.status === 'accepted')
      const byUser = {}
      for (const r of mine) {
        if (r.type === 'close_friend' || !byUser[r.addressee.id]) {
          byUser[r.addressee.id] = r.type === 'close_friend' ? 'Ami proche' : 'Abonné'
        }
      }
      links.value = byUser
    } catch (e) {
      error.value = e.message
    } finally {
      loading.value = false
    }
  }

  async function loadMore() {
    if (!nextPage.value) return
    loadingMore.value = true
    try {
      await loadPage(nextPage.value)
    } catch (e) {
      error.value = e.message
    } finally {
      loadingMore.value = false
    }
  }

  const isMine = (ootd) => ootd.owner.id === auth.user?.id
  const relationOf = (ootd) => (isMine(ootd) ? null : (links.value[ootd.owner.id] ?? null))

  return { ootds, loading, loadingMore, error, hasMore: computed(() => !!nextPage.value), load, loadMore, isMine, relationOf }
}
