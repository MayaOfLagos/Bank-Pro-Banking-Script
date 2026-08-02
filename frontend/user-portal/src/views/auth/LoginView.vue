<template>
  <AuthShell>
    <h2 class="text-xl font-bold text-white mb-1">Welcome back</h2>
    <p class="text-slate-400 text-sm mb-6">Sign in to your account</p>

    <form @submit.prevent="onSubmit" class="space-y-4">
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
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
          />
        </template>
      </FormField>

      <FormField label="Password" :error="errors.acct_password" required>
        <template #default="{ id, describedBy }">
          <div class="relative">
            <input
              :id="id"
              v-bind="passwordAttrs"
              v-model="password"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="current-password"
              placeholder="Enter password"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.acct_password || null"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm pr-10"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              :aria-label="showPassword ? 'Hide password' : 'Show password'"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 transition"
            >
              <EyeIcon v-if="!showPassword" class="h-4 w-4" />
              <EyeSlashIcon v-else class="h-4 w-4" />
            </button>
          </div>
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
        {{ isSubmitting ? 'Signing in...' : 'Sign in' }}
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
