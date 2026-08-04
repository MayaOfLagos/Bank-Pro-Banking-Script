<template>
  <AuthShell>
    <h2 class="auth-heading">Forgot password?</h2>
    <p class="auth-subheading">Enter the email on your account and we'll send you a reset link.</p>

    <form @submit.prevent="onSubmit" class="auth-form">
      <FormField label="Email address" :error="errors.email" required>
        <template #default="{ id, describedBy }">
          <div class="auth-input-wrap">
            <EnvelopeIcon class="auth-input-icon" aria-hidden="true" />
            <input
              :id="id"
              v-bind="emailAttrs"
              v-model="email"
              type="email"
              placeholder="you@example.com"
              autocomplete="email"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.email || null"
              class="auth-input auth-input--with-icon"
            />
          </div>
        </template>
      </FormField>

      <ErrorState v-if="serverError" :message="serverError" compact />

      <div v-if="sent" class="auth-success">
        If an account matches that email, a password reset link has been sent. Check your inbox.
      </div>

      <button
        type="submit"
        :disabled="isSubmitting || cooldown > 0"
        class="auth-submit"
      >
        {{ buttonLabel }}
      </button>
    </form>

    <template #footer>
      <p class="auth-footer-note">
        Remembered it?
        <RouterLink to="/login">Back to sign in</RouterLink>
      </p>
    </template>
  </AuthShell>
</template>

<script setup>
import { ref, computed, onBeforeUnmount } from 'vue'
import { RouterLink } from 'vue-router'
import { EnvelopeIcon } from '@heroicons/vue/24/solid'
import AuthShell from '../../components/auth/AuthShell.vue'
import FormField from '../../components/ui/FormField.vue'
import ErrorState from '../../components/ui/ErrorState.vue'
import { authApi } from '../../api/auth'
import { useValidatedForm } from '../../composables/useValidatedForm'
import { forgotPasswordSchema } from '../../validation/schemas'

const COOLDOWN_SECONDS = 60

const sent = ref(false)
const serverError = ref('')
const cooldown = ref(0)
let cooldownTimer = null

const { defineField, handleSubmit, errors, isSubmitting } = useValidatedForm(forgotPasswordSchema, {
  initialValues: { email: '' },
})
const [email, emailAttrs] = defineField('email')

const buttonLabel = computed(() => {
  if (isSubmitting.value) return 'Sending…'
  if (cooldown.value > 0) return `Resend in ${cooldown.value}s`
  return sent.value ? 'Resend link' : 'Send reset link'
})

const startCooldown = () => {
  cooldown.value = COOLDOWN_SECONDS
  if (cooldownTimer) clearInterval(cooldownTimer)
  cooldownTimer = setInterval(() => {
    cooldown.value -= 1
    if (cooldown.value <= 0) {
      clearInterval(cooldownTimer)
      cooldownTimer = null
    }
  }, 1000)
}

onBeforeUnmount(() => {
  if (cooldownTimer) clearInterval(cooldownTimer)
})

const onSubmit = handleSubmit(async (values) => {
  serverError.value = ''
  try {
    const { data } = await authApi.forgotPassword({ email: values.email })
    if (!data?.ok) throw new Error(data?.message || 'Failed to send reset link')
    sent.value = true
    startCooldown()
  } catch (err) {
    serverError.value = err?.response?.data?.message || err.message || 'Unable to send reset link'
  }
})
</script>
