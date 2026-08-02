<template>
  <AuthShell>
    <h2 class="auth-heading">Welcome back</h2>
    <p class="auth-subheading">Sign in to your account</p>

    <form @submit.prevent="onSubmit" class="auth-form">
      <FormField label="Account number" :error="errors.acct_no" required>
        <template #default="{ id, describedBy }">
          <input
            :id="id"
            v-bind="acctNoAttrs"
            v-model="acctNo"
            type="text"
            inputmode="numeric"
            autocomplete="username"
            placeholder="Enter account number, username, or email"
            :aria-describedby="describedBy"
            :aria-invalid="!!errors.acct_no || null"
            class="auth-input"
          />
        </template>
      </FormField>

      <FormField label="Password" :error="errors.acct_password" required>
        <template #default="{ id, describedBy }">
          <div class="auth-input-wrap">
            <input
              :id="id"
              v-bind="passwordAttrs"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              placeholder="Enter password"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.acct_password || null"
              class="auth-input auth-input--with-toggle"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
              class="auth-toggle"
            >
              <EyeIcon v-if="!showPassword" class="auth-toggle-icon" />
              <EyeSlashIcon v-else class="auth-toggle-icon" />
            </button>
          </div>
        </template>
      </FormField>

      <ErrorState v-if="serverError" :message="serverError" compact />

      <p v-if="attemptsRemaining !== null" class="auth-attempts">
        {{ attemptsRemaining === 0
          ? 'Account locked. Try again later.'
          : `${attemptsRemaining} attempt${attemptsRemaining === 1 ? '' : 's'} remaining before the account is locked.` }}
      </p>

      <button type="submit" :disabled="isSubmitting" class="auth-submit">
        {{ isSubmitting ? 'Signing in...' : 'Sign in' }}
      </button>
    </form>

    <template #footer>
      <RouterLink to="/reset-password" class="auth-link">Forgot password?</RouterLink>
    </template>
  </AuthShell>
</template>

<script setup>
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'
import AuthShell from '../../components/auth/AuthShell.vue'
import FormField from '../../components/ui/FormField.vue'
import ErrorState from '../../components/ui/ErrorState.vue'
import { authApi } from '../../api/auth'
import { useAuthStore } from '../../stores/auth'
import { useValidatedForm } from '../../composables/useValidatedForm'
import { loginSchema } from '../../validation/schemas'

const router = useRouter()
const auth = useAuthStore()

const showPassword = ref(false)
const serverError = ref('')
const attemptsRemaining = ref(null)

const { defineField, handleSubmit, errors, isSubmitting } = useValidatedForm(loginSchema, {
  initialValues: { acct_no: '', acct_password: '' },
})
const [acctNo, acctNoAttrs] = defineField('acct_no')
const [password, passwordAttrs] = defineField('acct_password')

const onSubmit = handleSubmit(async (values) => {
  serverError.value = ''
  attemptsRemaining.value = null
  try {
    const { data } = await authApi.login({
      acct_no: values.acct_no,
      password: values.acct_password,
    })
    if (!data?.ok) throw new Error(data?.message || 'Login failed')
    auth.setState('pending_pin', '/pin')
    auth.setCsrfToken(data.data?.csrf_token || '')
    await router.push(data.data?.next_route || '/pin')
  } catch (err) {
    const payload = err?.response?.data
    serverError.value = payload?.message || err.message || 'Unable to sign in'
    if (typeof payload?.data?.attempts_remaining === 'number') {
      attemptsRemaining.value = payload.data.attempts_remaining
    }
  }
})
</script>
