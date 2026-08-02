<template>
  <AuthShell>
    <h2 class="text-xl font-bold text-white mb-1">New password</h2>
    <p class="text-slate-400 text-sm mb-6">Set a new secure password for your account</p>

    <div v-if="tokenLoading" class="flex justify-center py-6">
      <div class="h-8 w-8 rounded-full border-2 border-blue-500 border-t-transparent animate-spin"></div>
    </div>

    <ErrorState v-else-if="!tokenValid && serverError" :message="serverError" compact />

    <form v-else @submit.prevent="onSubmit" class="space-y-4">
      <FormField label="New password" :error="errors.new_password" required>
        <template #default="{ id, describedBy }">
          <div class="relative">
            <input
              :id="id"
              v-bind="newPasswordAttrs"
              v-model="newPassword"
              :type="showPassword ? 'text' : 'password'"
              autocomplete="new-password"
              placeholder="Enter new password"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.new_password || null"
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

      <!-- Strength meter -->
      <div v-if="newPassword" aria-live="polite">
        <div class="flex gap-1 mb-1">
          <div
            v-for="segment in 4"
            :key="segment"
            class="h-1.5 flex-1 rounded-full transition-colors"
            :class="segment <= strength.score ? strength.color : 'bg-[#1e2d44]'"
          />
        </div>
        <p class="text-xs" :class="strength.textColor">{{ strength.label }}</p>
      </div>

      <FormField label="Confirm password" :error="errors.confirm_password" required>
        <template #default="{ id, describedBy }">
          <div class="relative">
            <input
              :id="id"
              v-bind="confirmPasswordAttrs"
              v-model="confirmPassword"
              :type="showConfirm ? 'text' : 'password'"
              autocomplete="new-password"
              placeholder="Re-enter new password"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.confirm_password || null"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm pr-10"
            />
            <button
              type="button"
              @click="showConfirm = !showConfirm"
              :aria-label="showConfirm ? 'Hide password' : 'Show password'"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200 transition"
            >
              <EyeIcon v-if="!showConfirm" class="h-4 w-4" />
              <EyeSlashIcon v-else class="h-4 w-4" />
            </button>
          </div>
        </template>
      </FormField>

      <ErrorState v-if="serverError" :message="serverError" compact />

      <button
        type="submit"
        :disabled="isSubmitting"
        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-4 transition disabled:opacity-50 disabled:cursor-not-allowed"
      >
        {{ isSubmitting ? 'Updating...' : 'Update password' }}
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
import { ref, computed, onMounted } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { EyeIcon, EyeSlashIcon } from '@heroicons/vue/24/outline'
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

  if (score <= 1) return { score: 1, label: 'Weak — add length and a digit', color: 'bg-red-500', textColor: 'text-red-400' }
  if (score === 2) return { score: 2, label: 'Fair — mix upper/lower case and add a digit', color: 'bg-amber-500', textColor: 'text-amber-400' }
  if (score === 3) return { score: 3, label: 'Good password', color: 'bg-emerald-500', textColor: 'text-emerald-400' }
  return { score: 4, label: 'Strong password', color: 'bg-emerald-500', textColor: 'text-emerald-400' }
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
