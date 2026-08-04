import { ref, computed } from 'vue'
import client from '../api/client'

/**
 * Loads /api/user/card.php. Keeps data + loading + error state.
 * View-layer agnostic — the same composable is reused by the mobile
 * dashboard, the card detail view, and (later) the desktop layout.
 */
export function useCards() {
  const cards = ref([])
  const requests = ref([])
  const canRequest = ref(false)
  const hasRequestInProgress = ref(false)
  const requestBlockedReason = ref('')
  const loading = ref(false)
  const error = ref('')
  const updating = ref(false)
  const requesting = ref(false)

  const primaryCard = computed(() => cards.value[0] ?? null)
  const latestRequest = computed(() => requests.value[0] ?? null)

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const { data } = await client.get('/api/user/card.php')
      if (!data?.ok) throw new Error(data?.message || 'Unable to load cards')
      cards.value = Array.isArray(data.data?.cards) ? data.data.cards : []
      requests.value = Array.isArray(data.data?.requests) ? data.data.requests : []
      canRequest.value = Boolean(data.data?.can_request)
      hasRequestInProgress.value = Boolean(data.data?.has_request_in_progress)
      requestBlockedReason.value = data.data?.request_blocked_reason || ''
    } catch (err) {
      error.value = err?.response?.data?.message || err.message || 'Unable to load cards'
      cards.value = []
      requests.value = []
    } finally {
      loading.value = false
    }
  }

  async function requestCard(payload) {
    requesting.value = true
    try {
      const { data } = await client.post('/api/user/card-request.php', payload)
      if (!data?.ok) throw new Error(data?.message || 'Card request failed')
      await load()
      return data
    } finally {
      requesting.value = false
    }
  }

  async function updateStatus(action) {
    if (!['pause', 'active'].includes(action)) throw new Error('Invalid card action')
    updating.value = true
    try {
      const { data } = await client.post('/api/user/card-status.php', { action })
      if (!data?.ok) throw new Error(data?.message || 'Card update failed')
      await load()
      return data
    } finally {
      updating.value = false
    }
  }

  return {
    cards,
    primaryCard,
    requests,
    latestRequest,
    canRequest,
    hasRequestInProgress,
    requestBlockedReason,
    loading,
    error,
    updating,
    requesting,
    load,
    updateStatus,
    requestCard,
  }
}
