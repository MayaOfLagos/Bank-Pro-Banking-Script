<template>
  <AuthShell>
    <h2 class="auth-heading">Enter your PIN</h2>
    <p class="auth-subheading">Verify your identity to continue</p>

    <div v-if="contextLoading" class="loading-spinner">
      <div class="spinner"></div>
    </div>

    <template v-else>
      <div v-if="profile.fullname" class="auth-context">
        <div class="auth-context-avatar">{{ initials }}</div>
        <div>
          <p class="auth-context-name">{{ profile.fullname }}</p>
          <p class="auth-context-meta">Account {{ profile.acct_no }}</p>
        </div>
      </div>

      <form @submit.prevent="onSubmit" class="auth-form">
        <PinPad
          v-model="pin"
          :length="PIN_LENGTH"
          :disabled="isSubmitting"
          label="PIN entry keypad"
          @complete="() => onSubmit()"
        />

        <p v-if="errors.pin" class="pin-error" role="alert">{{ errors.pin }}</p>

        <ErrorState v-if="serverError" :message="serverError" compact />

        <p v-if="attemptsRemaining !== null" class="auth-attempts">
          {{ attemptsRemaining === 0
            ? 'Account locked. Try again later.'
            : `${attemptsRemaining} attempt${attemptsRemaining === 1 ? '' : 's'} remaining before the account is locked.` }}
        </p>

        <button type="submit" :disabled="isSubmitting || !isComplete" class="auth-submit">
          {{ isSubmitting ? 'Verifying...' : 'Verify PIN' }}
        </button>
      </form>

      <div class="cancel-row">
        <button type="button" @click="logout" class="auth-cancel">
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
import PinPad from '../../components/ui/PinPad.vue'
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

const profile = reactive({ fullname: '', acct_no: '' })

const initials = computed(() =>
  profile.fullname
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('')
)

const PIN_LENGTH = 4

const { defineField, handleSubmit, errors, isSubmitting, resetForm } = useValidatedForm(
  z.object({ pin: pinSchema }),
  { initialValues: { pin: '' } }
)
const [pin] = defineField('pin')

const isComplete = computed(() => (pin.value || '').length === PIN_LENGTH)

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

<style scoped>
.loading-spinner {
  display: flex;
  justify-content: center;
  padding: var(--space-5) 0;
}
.spinner {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-pill);
  border: 2px solid var(--border);
  border-top-color: var(--accent);
  animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.cancel-row {
  text-align: center;
  margin-top: var(--space-4);
}
.pin-error {
  margin: 0;
  text-align: center;
  font-size: 0.78rem;
  color: var(--danger-fg);
}
</style>
