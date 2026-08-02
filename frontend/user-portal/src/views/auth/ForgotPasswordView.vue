<template>
  <AuthShell>
    <h2 class="text-xl font-bold text-white mb-1">Reset password</h2>
    <p class="text-slate-400 text-sm mb-6">Enter your email to receive a reset link</p>

    <form v-if="!sent" @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="block text-xs text-slate-400 mb-1.5">Email address</label>
        <input
          v-model="email"
          type="email"
          required
          placeholder="example@email.com"
          autocomplete="email"
          class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
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
        {{ loading ? 'Sending...' : 'Send reset link' }}
      </button>
    </form>

    <div v-else class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm rounded-xl px-4 py-3">
      Reset link sent to your email. Check your inbox and follow the link to set a new password.
    </div>

    <template #footer>
      <RouterLink to="/login" class="text-blue-400 text-sm hover:underline">
        Back to sign in
      </RouterLink>
    </template>
  </AuthShell>
</template>

<script setup>
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import AuthShell from '../../components/auth/AuthShell.vue'
import { authApi } from '../../api/auth'

const email = ref('')
const loading = ref(false)
const error = ref('')
const sent = ref(false)

const submit = async () => {
  error.value = ''
  loading.value = true
  try {
    const { data } = await authApi.forgotPassword({ email: email.value.trim() })
    if (!data?.ok) throw new Error(data?.message || 'Failed to send reset link')
    sent.value = true
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'Unable to send reset link'
  } finally {
    loading.value = false
  }
}
</script>
