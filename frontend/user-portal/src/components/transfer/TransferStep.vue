<template>
  <div class="page">
    <div class="content">
      <header class="header">
        <button type="button" class="back" aria-label="Go back" @click="router.back()">
          <ChevronLeftIcon class="back-icon" aria-hidden="true" />
        </button>
        <div class="titles">
          <p v-if="stepLabel" class="step">{{ stepLabel }}</p>
          <h1 class="title">{{ title }}</h1>
          <p v-if="subtitle" class="subtitle">{{ subtitle }}</p>
        </div>
      </header>

      <form class="form" @submit.prevent="onSubmit">
        <section class="form-card">
          <p v-if="description" class="description">{{ description }}</p>

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
        </section>

        <ErrorState v-if="serverError" :message="serverError" compact />

        <p v-if="attemptsRemaining !== null" class="attempts">
          {{ attemptsRemaining === 0
            ? 'Account locked. Try again later.'
            : `${attemptsRemaining} attempt${attemptsRemaining === 1 ? '' : 's'} remaining before the account is locked.` }}
        </p>

        <button type="submit" class="submit" :disabled="isSubmitting">
          {{ isSubmitting ? 'Verifying…' : submitLabel }}
        </button>
      </form>
    </div>
  </div>
</template>

<style scoped>
/* Mirrors the canonical page chrome used by DomesticTransferView /
   WireTransferView so the verification steps read as the same product. */
.page { min-height: 100vh; background: var(--bg-gradient); padding: var(--space-5) var(--space-4) 7rem; }
.content { max-width: 30rem; margin: 0 auto; display: flex; flex-direction: column; gap: var(--space-4); }

.header { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) 0 var(--space-1); }
.back { width: 2.5rem; height: 2.5rem; border-radius: 0.7rem; border: 1px solid var(--border); background: transparent; color: var(--text-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.back:hover { background: color-mix(in srgb, var(--text-primary) 6%, transparent); }
.back:active { transform: scale(0.95); }
.back-icon { width: 1.15rem; height: 1.15rem; }
.step { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; margin: 0 0 0.2rem; }
.title { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; line-height: 1.1; }
.subtitle { font-size: 0.8rem; color: var(--text-secondary); margin: 0.15rem 0 0; }

.form { display: flex; flex-direction: column; gap: var(--space-4); }
.form-card { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-5) var(--space-4); box-shadow: var(--shadow-card); display: flex; flex-direction: column; gap: var(--space-4); }
.description { font-size: 0.85rem; color: var(--text-secondary); margin: 0; line-height: 1.5; }

.input { width: 100%; padding: 0.85rem 1rem; border-radius: var(--radius-md); border: 1px solid transparent; background: var(--surface-muted); color: var(--text-primary); font-size: 0.95rem; font-family: inherit; outline: none; transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease; }
.input::placeholder { color: var(--text-muted); }
.input:focus { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent); }
/* 6-digit OTP: centred, wide-tracked so each character reads as its own slot. */
.input--code { text-align: center; font-size: 1.5rem; font-weight: 700; letter-spacing: 0.45em; padding-inline: 1.45rem 1rem; }

.attempts { color: var(--warning-fg); font-size: 0.75rem; text-align: center; margin: 0; padding: 0.5rem 0.75rem; background: color-mix(in srgb, var(--warning-fg) 10%, transparent); border-radius: var(--radius-sm); }

.submit { width: 100%; height: 3.1rem; padding: 0 var(--space-4); border-radius: var(--radius-pill); border: 1px solid transparent; background: var(--btn-primary-bg); color: var(--btn-primary-fg); font-weight: 700; font-size: 0.95rem; font-family: inherit; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; transition: transform 0.08s ease, opacity 0.15s ease; }
.submit:hover:not(:disabled) { opacity: 0.92; }
.submit:active:not(:disabled) { transform: scale(0.98); }
.submit:disabled { opacity: 0.5; cursor: not-allowed; }
</style>

<script setup>
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { z } from 'zod'
import { ChevronLeftIcon } from '@heroicons/vue/24/solid'
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
const inputClasses = computed(() => (isOtp.value ? 'input input--code' : 'input'))

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
