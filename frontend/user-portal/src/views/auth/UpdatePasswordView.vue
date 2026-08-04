<template>
  <AuthShell>
    <h2 class="auth-heading">New password</h2>
    <p class="auth-subheading">Choose a strong password you haven't used before.</p>

    <div v-if="tokenLoading" class="update-loading">
      <div class="update-spinner" aria-hidden="true"></div>
      <p class="update-loading-text">Verifying reset link…</p>
    </div>

    <ErrorState v-else-if="!tokenValid && serverError" :message="serverError" compact />

    <form v-else @submit.prevent="onSubmit" class="auth-form">
      <FormField label="New password" :error="errors.new_password" required>
        <template #default="{ id, describedBy }">
          <div class="auth-input-wrap">
            <LockClosedIcon class="auth-input-icon" aria-hidden="true" />
            <input
              :id="id"
              v-bind="newPasswordAttrs"
              v-model="newPassword"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="new-password"
              placeholder="At least 8 characters"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.new_password || null"
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

      <div v-if="newPassword" aria-live="polite">
        <div class="auth-strength-bars">
          <div
            v-for="segment in 4"
            :key="segment"
            class="auth-strength-bar"
            :class="segment <= strength.score ? `auth-strength-bar--${strength.tier}` : ''"
          />
        </div>
        <p class="auth-strength-label" :class="`auth-strength-label--${strength.tier}`">{{ strength.label }}</p>
      </div>

      <FormField label="Confirm password" :error="errors.confirm_password" required>
        <template #default="{ id, describedBy }">
          <div class="auth-input-wrap">
            <LockClosedIcon class="auth-input-icon" aria-hidden="true" />
            <input
              :id="id"
              v-bind="confirmPasswordAttrs"
              v-model="confirmPassword"
              :type="showConfirm ? 'text' : 'password'"
              autocomplete="new-password"
              placeholder="Re-enter new password"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.confirm_password || null"
              class="auth-input auth-input--with-icon auth-input--with-toggle"
            />
            <button
              type="button"
              @click="showConfirm = !showConfirm"
              :aria-label="showConfirm ? 'Hide password' : 'Show password'"
              :aria-pressed="showConfirm"
              class="auth-toggle"
            >
              <EyeIcon v-if="!showConfirm" class="auth-toggle-icon" aria-hidden="true" />
              <EyeSlashIcon v-else class="auth-toggle-icon" aria-hidden="true" />
            </button>
          </div>
        </template>
      </FormField>

      <ErrorState v-if="serverError" :message="serverError" compact />

      <button type="submit" :disabled="isSubmitting" class="auth-submit">
        {{ isSubmitting ? 'Updating…' : 'Update password' }}
      </button>
    </form>

    <template #footer>
      <p class="auth-footer-note">
        Changed your mind?
        <RouterLink to="/login">Back to sign in</RouterLink>
      </p>
    </template>
  </AuthShell>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { EyeIcon, EyeSlashIcon, LockClosedIcon } from '@heroicons/vue/24/solid'
import AuthShell from '../../components/auth/AuthShell.vue'
import FormField from '../../components/ui/FormField.vue'
import ErrorState from '../../components/ui/ErrorState.vue'
import { authApi } from '../../api/auth'
import { useValidatedForm } from '../../composables/useValidatedForm'
import { resetPasswordSchema } from '../../validation/schemas'

const route = useRoute()
const router = useRouter()

const tokenLoading = ref(true)
const tokenValid = ref(false)
const showPassword = ref(false)
const showConfirm = ref(false)
const serverError = ref('')

const email = ref('')
const resetToken = ref('')

const { defineField, handleSubmit, errors, isSubmitting, values } = useValidatedForm(resetPasswordSchema, {
  initialValues: { new_password: '', confirm_password: '' },
})
const [newPassword, newPasswordAttrs] = defineField('new_password')
const [confirmPassword, confirmPasswordAttrs] = defineField('confirm_password')

const strength = computed(() => {
  const pw = values.new_password || ''
  let score = 0
  if (pw.length >= 8) score += 1
  if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score += 1
  if (/\d/.test(pw)) score += 1
  if (/[^A-Za-z0-9]/.test(pw) || pw.length >= 12) score += 1

  if (score <= 1) return { score: 1, tier: 'weak', label: 'Weak — add length and a digit' }
  if (score === 2) return { score: 2, tier: 'fair', label: 'Fair — mix upper/lower case and add a digit' }
  if (score === 3) return { score: 3, tier: 'good', label: 'Good password' }
  return { score: 4, tier: 'strong', label: 'Strong password' }
})

onMounted(async () => {
  email.value = String(route.query.email || '')
  resetToken.value = String(route.query.reset_token || '')

  if (!email.value || !resetToken.value) {
    serverError.value = 'Invalid reset link. Please request a new one.'
    tokenLoading.value = false
    return
  }

  try {
    await authApi.validateResetToken({ email: email.value, reset_token: resetToken.value })
    tokenValid.value = true
  } catch (err) {
    serverError.value = err?.response?.data?.message || 'Reset link is invalid or has expired'
  } finally {
    tokenLoading.value = false
  }
})

const onSubmit = handleSubmit(async (formValues) => {
  serverError.value = ''
  try {
    const { data } = await authApi.resetPassword({
      email: email.value,
      reset_token: resetToken.value,
      new_password: formValues.new_password,
    })
    if (!data?.ok) throw new Error(data?.message || 'Failed to update password')
    await router.push(data.data?.next_route || '/login')
  } catch (err) {
    serverError.value = err?.response?.data?.message || err.message || 'Unable to update password'
  }
})
</script>

<style scoped>
.update-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-6) 0;
}
.update-spinner {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-pill);
  border: 3px solid var(--surface-muted);
  border-top-color: var(--accent);
  animation: update-spin 0.9s linear infinite;
}
.update-loading-text {
  color: var(--text-secondary);
  font-size: 0.85rem;
  margin: 0;
}
@keyframes update-spin { to { transform: rotate(360deg); } }
</style>
