<template>
  <AuthShell>
    <h2 class="text-xl font-bold text-white mb-1">Enter your PIN</h2>
    <p class="text-slate-400 text-sm mb-6">Verify your identity to continue</p>

    <div v-if="contextLoading" class="flex justify-center py-6">
      <div class="h-8 w-8 rounded-full border-2 border-blue-500 border-t-transparent animate-spin"></div>
    </div>

    <template v-else>
      <div v-if="profile.fullname" class="flex items-center gap-3 bg-[#1a2436] border border-[#1e2d44] rounded-2xl px-4 py-3 mb-5">
        <div class="h-10 w-10 rounded-full bg-blue-600/20 flex items-center justify-center flex-shrink-0">
          <span class="text-blue-400 font-bold text-sm">{{ initials }}</span>
        </div>
        <div class="min-w-0">
          <p class="text-white text-sm font-semibold truncate">{{ profile.fullname }}</p>
          <p class="text-slate-500 text-xs">Account {{ profile.acct_no }}</p>
        </div>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">PIN code</label>
          <input
            v-model="pin"
            type="password"
            inputmode="numeric"
            maxlength="6"
            required
            placeholder="••••••"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-center text-2xl tracking-[0.4em] font-bold"
          />
        </div>

        <div v-if="error" class="bg-red-500/10 border border-red-500/20 text-red-400 text-sm rounded-xl px-4 py-3">
          {{ error }}
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-4 transition disabled:opacity-50"
        >
          {{ loading ? 'Verifying...' : 'Verify PIN' }}
        </button>
      </form>

      <div class="mt-5 text-center">
        <button
          type="button"
          @click="logout"
          class="text-slate-500 text-sm hover:text-slate-300 transition"
        >
          Cancel &amp; sign out
        </button>
      </div>
    </template>
  </AuthShell>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import AuthShell from '../../components/auth/AuthShell.vue'
import { authApi } from '../../api/auth'

const router = useRouter()

const contextLoading = ref(true)
const loading = ref(false)
const pin = ref('')
const error = ref('')

const profile = reactive({
  fullname: '',
  acct_no: ''
})

const initials = computed(() => {
  return profile.fullname
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('')
})

onMounted(async () => {
  try {
    const { data } = await authApi.pinContext()
    if (data?.data) {
      profile.fullname = data.data.fullname || ''
      profile.acct_no = data.data.acct_no || ''
    }
  } catch (err) {
    error.value = err?.response?.data?.message || 'Session expired. Please log in again.'
  } finally {
    contextLoading.value = false
  }
})

const submit = async () => {
  if (!pin.value) {
    error.value = 'Please enter your PIN'
    return
  }
  error.value = ''
  loading.value = true
  try {
    const { data } = await authApi.verifyPin({ pin: pin.value })
    if (!data?.ok) throw new Error(data?.message || 'PIN verification failed')
    await router.push(data.data?.next_route || '/dashboard')
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'Incorrect PIN'
    pin.value = ''
  } finally {
    loading.value = false
  }
}

const logout = async () => {
  try {
    await authApi.logout()
  } finally {
    await router.push('/login')
  }
}
</script>
