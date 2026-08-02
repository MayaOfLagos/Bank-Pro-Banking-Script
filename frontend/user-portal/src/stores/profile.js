import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import client from '../api/client'

// Single source of truth for the signed-in user's profile + balances.
// Views subscribe instead of each running their own fetch.
export const useProfileStore = defineStore('profile', () => {
  const profile = ref(null)
  const balances = ref(null)
  const loading = ref(false)
  const error = ref('')

  const fullName = computed(() => profile.value?.full_name || '')
  const initials = computed(() => {
    const f = String(profile.value?.firstname || '').charAt(0).toUpperCase()
    const l = String(profile.value?.lastname || '').charAt(0).toUpperCase()
    return f + l || '?'
  })
  const currencySymbol = computed(() => profile.value?.currency || '$')

  async function loadProfile(force = false) {
    if (profile.value && !force) return profile.value
    loading.value = true
    error.value = ''
    try {
      const { data } = await client.get('/api/user/profile.php')
      if (!data?.ok) throw new Error(data?.message || 'Unable to load profile')
      profile.value = data.data
      return profile.value
    } catch (err) {
      error.value = err?.response?.data?.message || err.message || 'Unable to load profile'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function loadDashboard(force = false) {
    if (balances.value && !force) return balances.value
    loading.value = true
    error.value = ''
    try {
      const { data } = await client.get('/api/user/dashboard.php')
      if (!data?.ok) throw new Error(data?.message || 'Unable to load dashboard')
      balances.value = data.data
      // Dashboard also carries a profile subset — hydrate if the main
      // profile hasn't been fetched yet.
      if (!profile.value && data.data?.profile) {
        profile.value = data.data.profile
      }
      return balances.value
    } catch (err) {
      error.value = err?.response?.data?.message || err.message || 'Unable to load dashboard'
      throw err
    } finally {
      loading.value = false
    }
  }

  function patch(partial) {
    if (!profile.value) profile.value = {}
    profile.value = { ...profile.value, ...partial }
  }

  function reset() {
    profile.value = null
    balances.value = null
    loading.value = false
    error.value = ''
  }

  return {
    profile,
    balances,
    loading,
    error,
    fullName,
    initials,
    currencySymbol,
    loadProfile,
    loadDashboard,
    patch,
    reset,
  }
})
