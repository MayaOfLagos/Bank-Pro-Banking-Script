<template>
  <div class="min-h-screen bg-[#080d18] text-white">
    <!-- Gradient header -->
    <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-6">
      <button
        @click="router.back()"
        class="flex items-center gap-2 text-slate-400 mb-4 hover:text-slate-200 transition"
      >
        <ArrowLeftIcon class="h-5 w-5" />
        <span class="text-sm">Back</span>
      </button>
      <p class="text-slate-500 text-xs mb-1">{{ stepLabel }}</p>
      <h1 class="text-2xl font-bold text-white">{{ title }}</h1>
      <p class="text-slate-400 text-sm mt-1">{{ subtitle }}</p>
    </div>

    <!-- Form card -->
    <div class="bg-[#111827] rounded-2xl mx-4 mt-4 p-5 border border-[#1e2d44]">
      <p class="text-slate-400 text-sm mb-5">{{ description }}</p>

      <form @submit.prevent="onSubmit" class="space-y-4">
        <FormField :label="inputLabel" :error="errors.code" required>
          <template #default="{ id, describedBy }">
            <input
              :id="id"
              v-bind="codeAttrs"
              v-model="code"
              :type="inputType"
              :inputmode="inputMode"
              :maxlength="maxLength"
              autocomplete="one-time-code"
              :placeholder="placeholder"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.code || null"
              :class="inputClasses"
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
          {{ isSubmitting ? 'Verifying...' : submitLabel }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { z } from 'zod'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import client from '../../api/client'
import FormField from '../ui/FormField.vue'
import ErrorState from '../ui/ErrorState.vue'
import { useValidatedForm } from '../../composables/useValidatedForm'
import { otp as otpSchema, shortCode } from '../../validation/schemas'

const props = defineProps({
  // Server endpoint to POST to (e.g. /api/user/transfer-verify-cot.php).
  endpoint: { type: String, required: true },
  // Field name the server expects in the body (e.g. 'cot_code').
  fieldName: { type: String, required: true },
  // Visual copy.
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  description: { type: String, default: '' },
  inputLabel: { type: String, required: true },
  submitLabel: { type: String, default: 'Verify' },
  placeholder: { type: String, default: '' },
  // Step counter in the header (e.g. "Step 1 of 3", "Final step").
  stepLabel: { type: String, default: '' },
  // Kind of code: 'otp' → 6-digit numeric, masked, monospace; 'short' → free-form 3-15 chars.
  kind: { type: String, default: 'short', validator: (v) => ['otp', 'short'].includes(v) },
})

const router = useRouter()
const serverError = ref('')
const attemptsRemaining = ref(null)

const isOtp = computed(() => props.kind === 'otp')
const inputType = computed(() => 'password')
const inputMode = computed(() => (isOtp.value ? 'numeric' : 'text'))
const maxLength = computed(() => (isOtp.value ? 6 : 15))
const inputClasses = computed(() =>
  isOtp.value
    ? 'w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-center text-2xl tracking-[0.5em] font-bold'
    : 'w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm'
)

const schema = z.object({ code: isOtp.value ? otpSchema : shortCode })
const { defineField, handleSubmit, errors, isSubmitting, resetForm } = useValidatedForm(schema, {
  initialValues: { code: '' },
})
const [code, codeAttrs] = defineField('code')

const onSubmit = handleSubmit(async (values) => {
  serverError.value = ''
  attemptsRemaining.value = null
  try {
    const { data } = await client.post(props.endpoint, { [props.fieldName]: values.code })
    if (!data?.ok) throw new Error(data?.message || 'Verification failed')
    const next = data.data?.next_route
    if (next) await router.push(next)
  } catch (err) {
    const payload = err?.response?.data
    serverError.value = payload?.message || err.message || 'Verification failed'
    if (typeof payload?.data?.attempts_remaining === 'number') {
      attemptsRemaining.value = payload.data.attempts_remaining
    }
    resetForm({ values: { code: '' } })
  }
})
</script>
