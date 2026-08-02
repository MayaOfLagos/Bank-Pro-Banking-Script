import { ref, computed } from 'vue'
import client from '../api/client'

/**
 * Loads /api/user/card.php. Keeps data + loading + error state.
 * View-layer agnostic — the same composable is reused by the mobile
 * dashboard, the card detail view, and (later) the desktop layout.
 */
export function useCards() {
  const cards = ref([])
  const canRequest = ref(false)
  const hasRequestInProgress = ref(false)
  const loading = ref(false)
  const error = ref('')

  const primaryCard = computed(() => cards.value[0] ?? null)

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const { data } = await client.get('/api/user/card.php')
      if (!data?.ok) throw new Error(data?.message || 'Unable to load cards')
      cards.value = Array.isArray(data.data?.cards) ? data.data.cards : []
      canRequest.value = Boolean(data.data?.can_request)
      hasRequestInProgress.value = Boolean(data.data?.has_request_in_progress)
    } catch (err) {
      error.value = err?.response?.data?.message || err.message || 'Unable to load cards'
      cards.value = []
    } finally {
      loading.value = false
    }
  }

  return { cards, primaryCard, canRequest, hasRequestInProgress, loading, error, load }
}
