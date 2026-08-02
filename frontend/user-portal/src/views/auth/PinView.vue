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

      <form @submit.prevent="onSubmit" class="space-y-4">
        <FormField label="PIN code" :error="errors.pin" required>
          <template #default="{ id, describedBy }">
            <input
              :id="id"
              v-bind="pinAttrs"
              v-model="pin"
              type="password"
              inputmode="numeric"
              autocomplete="off"
              maxlength="4"
              placeholder="••••"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.pin || null"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-center text-2xl tracking-[0.4em] font-bold"
            />
          </template>
        </FormField>

        <ErrorState v-if="serverError" :message="serverError" compact />

        <p v-if="attemptsRemaining !== null" class="text-xs text-amber-400 text-center">
          {{ attemptsRemaining === 0
            ? 'Account locked. Try again later.'
            : `${attemptsRemaining} attempt${attemptsRemaining === 1 ? '' : 's'} remaining before the account is locked.` }}
        </p>

        <button
          type="submit"
          :disabled="isSubmitting"
          class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-4 transition disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ isSubmitting ? 'Verifying...' : 'Verify PIN' }}
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
import FormField from '../../components/ui/FormField.vue'
import ErrorState from '../../components/ui/ErrorState.vue'
import { authApi } from '../../api/auth'
import { useAuthStore } from '../../stores/auth'
import { useProfileStore } from '../../stores/profile'
import { useValidatedForm } from '../../composables/useValidatedForm'
import { z } from 'zod'
import { pin as pinSchema } from '../../validation/schemas'

const router = useRouter()
const auth = useAuthStore()
const profileStore = useProfileStore()

const contextLoading = ref(true)
const serverError = ref('')
const attemptsRemaining = ref(null)

const profile = reactive({
  fullname: '',
  acct_no: '',
})

const initials = computed(() =>
  profile.fullname
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('')
)

const { defineField, handleSubmit, errors, isSubmitting, resetForm } = useValidatedForm(
  z.object({ pin: pinSchema }),
  { initialValues: { pin: '' } }
)
const [pin, pinAttrs] = defineField('pin')

onMounted(async () => {
  try {
    const { data } = await authApi.pinContext()
    if (data?.data) {
      profile.fullname = data.data.fullname || ''
      profile.acct_no = data.data.acct_no || ''
    }
  } catch (err) {
    serverError.value = err?.response?.data?.message || 'Session expired. Please log in again.'
  } finally {
    contextLoading.value = false
  }
})

const onSubmit = handleSubmit(async (values) => {
  serverError.value = ''
  attemptsRemaining.value = null
  try {
    const { data } = await authApi.verifyPin({ pin: values.pin })
    if (!data?.ok) throw new Error(data?.message || 'PIN verification failed')
    auth.setState('authenticated', '/dashboard')
    auth.setCsrfToken(data.data?.csrf_token || '')
    // Warm the profile store so the dashboard doesn't do a cold fetch.
    profileStore.loadProfile().catch(() => {})
    await router.push(data.data?.next_route || '/dashboard')
  } catch (err) {
    const payload = err?.response?.data
    serverError.value = payload?.message || err.message || 'Incorrect PIN'
    if (typeof payload?.data?.attempts_remaining === 'number') {
      attemptsRemaining.value = payload.data.attempts_remaining
    }
    resetForm({ values: { pin: '' } })
  }
})

const logout = async () => {
  try {
    await authApi.logout()
  } finally {
    auth.reset()
    profileStore.reset()
    await router.push('/login')
  }
}
</script>
