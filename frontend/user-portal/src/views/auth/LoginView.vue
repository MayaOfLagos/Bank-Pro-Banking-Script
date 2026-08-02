<template>
  <AuthShell>
    <h2 class="text-xl font-bold text-white mb-1">Welcome back</h2>
    <p class="text-slate-400 text-sm mb-6">Sign in to your account</p>

    <form @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="block text-xs text-slate-400 mb-1.5">Account number</label>
        <input
          v-model="form.acct_no"
          type="text"
          inputmode="numeric"
          autocomplete="username"
          required
          placeholder="Enter account number"
          class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
        />
      </div>

      <div>
        <label class="block text-xs text-slate-400 mb-1.5">Password</label>
        <div class="relative">
          <input
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            required
            placeholder="Enter password"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm pr-10"
          />
          <button
            type="button"
            @click="showPassword = !showPassword"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 transition"
          >
            <EyeIcon v-if="!showPassword" class="h-4 w-4" />
            <EyeSlashIcon v-else class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div v-if="error" class="bg-red-500/10 border border-red-500/20 text-red-400 text-sm rounded-xl px-4 py-3">
        {{ error }}
      </div>

      <button
        type="submit"
        :disabled="loading"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-4 transition disabled:opacity-50"
      >
        {{ loading ? 'Signing in...' : 'Sign in' }}
      </button>
    </form>

    <template #footer>
      <RouterLink to="/reset-password" class="text-blue-400 text-sm hover:underline">
        Forgot password?
      </RouterLink>
    </template>
  </AuthShell>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'
import AuthShell from '../../components/auth/AuthShell.vue'
import { authApi } from '../../api/auth'

const router = useRouter()

const loading = ref(false)
const showPassword = ref(false)
const error = ref('')

const form = reactive({
  acct_no: '',
  password: ''
})

const submit = async () => {
  error.value = ''
  loading.value = true
  try {
    const { data } = await authApi.login({ acct_no: form.acct_no.trim(), password: form.password })
    if (!data?.ok) throw new Error(data?.message || 'Login failed')
    await router.push(data.data?.next_route || '/pin')
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'Unable to sign in'
  } finally {
    loading.value = false
  }
}
</script>
