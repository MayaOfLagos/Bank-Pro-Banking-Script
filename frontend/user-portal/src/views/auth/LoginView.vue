<template>
  <AuthShell>
    <h2 class="auth-heading">Welcome back</h2>
    <p class="auth-subheading">Sign in to continue to your account.</p>

    <form @submit.prevent="onSubmit" class="auth-form">
      <FormField label="Account number" :error="errors.acct_no" required>
        <template #default="{ id, describedBy }">
          <div class="auth-input-wrap">
            <UserIcon class="auth-input-icon" aria-hidden="true" />
            <input
              :id="id"
              v-bind="acctNoAttrs"
              v-model="acctNo"
              type="text"
              inputmode="numeric"
              autocomplete="username"
              placeholder="Account number, username, or email"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.acct_no || null"
              class="auth-input auth-input--with-icon"
            />
          </div>
        </template>
      </FormField>

      <FormField label="Password" :error="errors.acct_password" required>
        <template #default="{ id, describedBy }">
          <div class="auth-input-wrap">
            <LockClosedIcon class="auth-input-icon" aria-hidden="true" />
            <input
              :id="id"
              v-bind="passwordAttrs"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              placeholder="Enter password"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.acct_password || null"
              class="auth-input auth-input--with-icon auth-input--with-toggle"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
              :aria-pressed="showPassword"
              class="auth-toggle"
            >
              <EyeIcon v-if="!showPassword" class="auth-toggle-icon" aria-hidden="true" />
              <EyeSlashIcon v-else class="auth-toggle-icon" aria-hidden="true" />
            </button>
          </div>
        </template>
      </FormField>

      <div class="auth-inline-actions">
        <RouterLink to="/reset-password" class="auth-link">Forgot password?</RouterLink>
      </div>

      <ErrorState v-if="serverError" :message="serverError" compact />

      <p v-if="attemptsRemaining !== null" class="auth-attempts">
        {{ attemptsRemaining === 0
          ? 'Account locked. Try again later.'
          : `${attemptsRemaining} attempt${attemptsRemaining === 1 ? '' : 's'} remaining before the account is locked.` }}
      </p>

      <button type="submit" :disabled="isSubmitting" class="auth-submit">
        {{ isSubmitting ? 'Signing in…' : 'Sign in' }}
      </button>
    </form>

    <template #footer>
      <!-- Held until site config settles: `registrationEnabled` defaults to
           true, so rendering early would flash a "Create an account" link at
           customers of a bank that has registration switched off. -->
      <template v-if="site.settled">
        <p v-if="site.registrationEnabled" class="auth-footer-note">
          New to {{ site.brandName }}?
          <RouterLink to="/register">Create an account</RouterLink>
        </p>
        <p class="auth-footer-note auth-footer-note--muted">
          Need help signing in?
          <a v-if="site.supportEmail" :href="`mailto:${site.supportEmail}`">Contact support</a>
          <RouterLink v-else to="/reset-password">Contact support</RouterLink>
        </p>
      </template>
    </template>
  </AuthShell>
</template>

<script setup>
import { ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { EyeIcon, EyeSlashIcon, UserIcon, LockClosedIcon } from '@heroicons/vue/24/solid'
import AuthShell from '../../components/auth/AuthShell.vue'
import FormField from '../../components/ui/FormField.vue'
import ErrorState from '../../components/ui/ErrorState.vue'
import { authApi } from '../../api/auth'
import { useAuthStore } from '../../stores/auth'
import { useSiteStore } from '../../stores/site'
import { useValidatedForm } from '../../composables/useValidatedForm'
import { loginSchema } from '../../validation/schemas'

const router = useRouter()
const auth = useAuthStore()
const site = useSiteStore()

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
