<template>
  <AuthShell>
    <h2 class="text-xl font-bold text-white mb-1">New password</h2>
    <p class="text-slate-400 text-sm mb-6">Set a new secure password for your account</p>

    <div v-if="tokenLoading" class="flex justify-center py-6">
      <div class="h-8 w-8 rounded-full border-2 border-blue-500 border-t-transparent animate-spin"></div>
    </div>

    <form v-else @submit.prevent="submit" class="space-y-4">
      <div>
        <label class="block text-xs text-slate-400 mb-1.5">New password</label>
        <div class="relative">
          <input
            v-model="form.password"
            :type="showPassword ? 'text' : 'password'"
            required
            minlength="6"
            placeholder="Enter new password"
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

      <div>
        <label class="block text-xs text-slate-400 mb-1.5">Confirm password</label>
        <div class="relative">
          <input
            v-model="form.password_confirmation"
            :type="showConfirm ? 'text' : 'password'"
            required
            minlength="6"
            placeholder="Re-enter new password"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm pr-10"
          />
          <button
            type="button"
            @click="showConfirm = !showConfirm"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 transition"
          >
            <EyeIcon v-if="!showConfirm" class="h-4 w-4" />
            <EyeSlashIcon v-else class="h-4 w-4" />
          </button>
        </div>
      </div>

      <div v-if="error" class="bg-red-500/10 border border-red-500/20 text-red-400 text-sm rounded-xl px-4 py-3">
        {{ error }}
      </div>

      <button
        type="submit"
        :disabled="loading || !tokenValid"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-4 transition disabled:opacity-50"
      >
        {{ loading ? 'Updating...' : 'Update password' }}
      </button>
    </form>

    <template #footer>
      <RouterLink to="/login" class="text-blue-400 text-sm hover:underline">
        Back to sign in
      </RouterLink>
    </template>
  </AuthShell>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'
import AuthShell from '../../components/auth/AuthShell.vue'
import { authApi } from '../../api/auth'

const route = useRoute()
const router = useRouter()

const tokenLoading = ref(true)
const tokenValid = ref(false)
const loading = ref(false)
const showPassword = ref(false)
const showConfirm = ref(false)
const error = ref('')

const email = ref('')
const resetToken = ref('')

const form = reactive({
  password: '',
  password_confirmation: ''
})

onMounted(async () => {
  email.value = String(route.query.email || '')
  resetToken.value = String(route.query.reset_token || '')

  if (!email.value || !resetToken.value) {
    error.value = 'Invalid reset link. Please request a new one.'
    tokenLoading.value = false
    return
  }

  try {
    await authApi.validateResetToken({ email: email.value, reset_token: resetToken.value })
    tokenValid.value = true
  } catch (err) {
    error.value = err?.response?.data?.message || 'Reset link is invalid or has expired'
  } finally {
    tokenLoading.value = false
  }
})

const submit = async () => {
  error.value = ''

  if (form.password !== form.password_confirmation) {
    error.value = 'Passwords do not match'
    return
  }

  loading.value = true
  try {
    const { data } = await authApi.resetPassword({
      email: email.value,
      reset_token: resetToken.value,
      password: form.password,
      password_confirmation: form.password_confirmation
    })
    if (!data?.ok) throw new Error(data?.message || 'Failed to update password')
    await router.push(data.data?.next_route || '/login')
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'Unable to update password'
  } finally {
    loading.value = false
  }
}
</script>
