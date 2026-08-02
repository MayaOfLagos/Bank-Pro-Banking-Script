import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '../api/auth'

// Auth state machine: guest | pending_pin | authenticated
// The server is the source of truth. Client caches state to avoid a
// status.php roundtrip on every navigation.
export const useAuthStore = defineStore('auth', () => {
  const state = ref('guest')
  const nextRoute = ref('/login')
  const csrfToken = ref('')
  const initialized = ref(false)

  const isAuthenticated = computed(() => state.value === 'authenticated')
  const isPendingPin = computed(() => state.value === 'pending_pin')
  const isGuest = computed(() => state.value === 'guest')

  async function refresh() {
    try {
      const { data } = await authApi.status()
      state.value = data?.data?.state || 'guest'
      nextRoute.value = data?.data?.next_route || '/login'
      const token = data?.data?.csrf_token
      if (typeof token === 'string' && token) csrfToken.value = token
    } catch {
      state.value = 'guest'
      nextRoute.value = '/login'
    } finally {
      initialized.value = true
    }
    return state.value
  }

  function setState(next, route) {
    state.value = next
    if (route) nextRoute.value = route
  }

  function setCsrfToken(token) {
    if (typeof token === 'string' && token) csrfToken.value = token
  }

  function reset() {
    state.value = 'guest'
    nextRoute.value = '/login'
    csrfToken.value = ''
  }

  return {
    state,
    nextRoute,
    csrfToken,
    initialized,
    isAuthenticated,
    isPendingPin,
    isGuest,
    refresh,
    setState,
    setCsrfToken,
    reset,
  }
})
