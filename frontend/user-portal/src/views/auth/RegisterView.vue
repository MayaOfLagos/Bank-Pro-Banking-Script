<template>
  <AuthShell>
    <div class="reg-stepper" role="progressbar" :aria-valuenow="step" aria-valuemin="1" aria-valuemax="3">
      <div v-for="i in 3" :key="i" class="reg-step-dot" :class="{
        'reg-step-dot--done': i < step,
        'reg-step-dot--active': i === step,
      }">
        <CheckIcon v-if="i < step" class="reg-step-check" aria-hidden="true" />
        <span v-else>{{ i }}</span>
      </div>
    </div>

    <h2 class="auth-heading">{{ stepMeta.title }}</h2>
    <p class="auth-subheading">{{ stepMeta.subtitle }}</p>

    <form class="auth-form" @submit.prevent="onNext">
      <!-- Honeypot: legitimate users never see or fill this. Any bot that
           auto-fills every input in the DOM triggers a silent server-side
           reject. tabindex=-1 + aria-hidden keeps it out of a11y trees. -->
      <input
        v-model="hp"
        type="text"
        name="hp_website"
        tabindex="-1"
        autocomplete="off"
        aria-hidden="true"
        style="position:absolute;left:-9999px;top:-9999px;opacity:0;pointer-events:none;height:0;width:0;"
      />
      <!-- ─── Step 1 · About you ─── -->
      <template v-if="step === 1">
        <div class="reg-row">
          <FormField label="First name" :error="s1Errors.firstname" required>
            <template #default="{ id, describedBy }">
              <div class="auth-input-wrap">
                <UserIcon class="auth-input-icon" aria-hidden="true" />
                <input :id="id" v-bind="firstnameAttrs" v-model="firstname" type="text" autocomplete="given-name" placeholder="Jane" :aria-describedby="describedBy" :aria-invalid="!!s1Errors.firstname || null" class="auth-input auth-input--with-icon" />
              </div>
            </template>
          </FormField>
          <FormField label="Last name" :error="s1Errors.lastname" required>
            <template #default="{ id, describedBy }">
              <div class="auth-input-wrap">
                <UserIcon class="auth-input-icon" aria-hidden="true" />
                <input :id="id" v-bind="lastnameAttrs" v-model="lastname" type="text" autocomplete="family-name" placeholder="Doe" :aria-describedby="describedBy" :aria-invalid="!!s1Errors.lastname || null" class="auth-input auth-input--with-icon" />
              </div>
            </template>
          </FormField>
        </div>

        <FormField label="Date of birth" :error="s1Errors.dob" required>
          <template #default="{ id, describedBy }">
            <div class="auth-input-wrap">
              <CakeIcon class="auth-input-icon" aria-hidden="true" />
              <input :id="id" v-bind="dobAttrs" v-model="dob" type="date" :max="maxDob" autocomplete="bday" :aria-describedby="describedBy" :aria-invalid="!!s1Errors.dob || null" class="auth-input auth-input--with-icon" />
            </div>
          </template>
        </FormField>

        <FormField label="Country" :error="s1Errors.country" required>
          <template #default="{ id, describedBy }">
            <div class="auth-input-wrap">
              <GlobeAltIcon class="auth-input-icon" aria-hidden="true" />
              <select :id="id" v-bind="countryAttrs" v-model="country" :aria-describedby="describedBy" :aria-invalid="!!s1Errors.country || null" class="auth-input auth-input--with-icon">
                <option value="" disabled>Select your country</option>
                <option v-for="c in COUNTRY_OPTIONS" :key="c.code" :value="c.code">{{ c.name }}</option>
              </select>
            </div>
          </template>
        </FormField>
      </template>

      <!-- ─── Step 2 · Account ─── -->
      <template v-else-if="step === 2">
        <FormField label="Email address" :error="s2Errors.email" required>
          <template #default="{ id, describedBy }">
            <div class="auth-input-wrap">
              <EnvelopeIcon class="auth-input-icon" aria-hidden="true" />
              <input :id="id" v-bind="emailAttrs" v-model="email" type="email" autocomplete="email" placeholder="you@example.com" :aria-describedby="describedBy" :aria-invalid="!!s2Errors.email || null" class="auth-input auth-input--with-icon" />
            </div>
          </template>
        </FormField>

        <FormField label="Phone number" :error="s2Errors.phone" required>
          <template #default="{ id, describedBy }">
            <div class="auth-input-wrap">
              <PhoneIcon class="auth-input-icon" aria-hidden="true" />
              <input :id="id" v-bind="phoneAttrs" v-model="phone" type="tel" autocomplete="tel" placeholder="+1 555 555 0100" :aria-describedby="describedBy" :aria-invalid="!!s2Errors.phone || null" class="auth-input auth-input--with-icon" />
            </div>
          </template>
        </FormField>

        <div class="reg-row">
          <FormField label="Currency" :error="s2Errors.currency" required>
            <template #default="{ id, describedBy }">
              <div class="auth-input-wrap">
                <BanknotesIcon class="auth-input-icon" aria-hidden="true" />
                <select :id="id" v-bind="currencyAttrs" v-model="currency" :aria-describedby="describedBy" :aria-invalid="!!s2Errors.currency || null" class="auth-input auth-input--with-icon">
                  <option value="" disabled>Select</option>
                  <option v-for="c in CURRENCY_OPTIONS" :key="c.code" :value="c.code">
                    {{ c.code }} · {{ c.symbol }} — {{ c.name }}
                  </option>
                </select>
              </div>
            </template>
          </FormField>

          <FormField label="Account type" :error="s2Errors.acct_type" required>
            <template #default="{ id, describedBy }">
              <div class="auth-input-wrap">
                <BuildingLibraryIcon class="auth-input-icon" aria-hidden="true" />
                <select :id="id" v-bind="acctTypeAttrs" v-model="acctType" :aria-describedby="describedBy" :aria-invalid="!!s2Errors.acct_type || null" class="auth-input auth-input--with-icon">
                  <option value="" disabled>Select</option>
                  <option v-for="t in ACCOUNT_TYPE_OPTIONS" :key="t.code" :value="t.code">{{ t.name }}</option>
                </select>
              </div>
            </template>
          </FormField>
        </div>
      </template>

      <!-- ─── Step 3 · Secure your account ─── -->
      <template v-else>
        <FormField label="Password" :error="s3Errors.password" required>
          <template #default="{ id, describedBy }">
            <div class="auth-input-wrap">
              <LockClosedIcon class="auth-input-icon" aria-hidden="true" />
              <input :id="id" v-bind="passwordAttrs" v-model="password" :type="showPw ? 'text' : 'password'" autocomplete="new-password" placeholder="At least 8 characters" :aria-describedby="describedBy" :aria-invalid="!!s3Errors.password || null" class="auth-input auth-input--with-icon auth-input--with-toggle" />
              <button type="button" class="auth-toggle" :aria-label="showPw ? 'Hide password' : 'Show password'" :aria-pressed="showPw" @click="showPw = !showPw">
                <EyeIcon v-if="!showPw" class="auth-toggle-icon" aria-hidden="true" />
                <EyeSlashIcon v-else class="auth-toggle-icon" aria-hidden="true" />
              </button>
            </div>
          </template>
        </FormField>

        <div v-if="password" aria-live="polite">
          <div class="auth-strength-bars">
            <div v-for="segment in 4" :key="segment" class="auth-strength-bar" :class="segment <= strength.score ? `auth-strength-bar--${strength.tier}` : ''" />
          </div>
          <p class="auth-strength-label" :class="`auth-strength-label--${strength.tier}`">{{ strength.label }}</p>
        </div>

        <FormField label="Confirm password" :error="s3Errors.confirm_password" required>
          <template #default="{ id, describedBy }">
            <div class="auth-input-wrap">
              <LockClosedIcon class="auth-input-icon" aria-hidden="true" />
              <input :id="id" v-bind="confirmAttrs" v-model="confirmPassword" :type="showConfirm ? 'text' : 'password'" autocomplete="new-password" placeholder="Re-enter password" :aria-describedby="describedBy" :aria-invalid="!!s3Errors.confirm_password || null" class="auth-input auth-input--with-icon auth-input--with-toggle" />
              <button type="button" class="auth-toggle" :aria-label="showConfirm ? 'Hide password' : 'Show password'" :aria-pressed="showConfirm" @click="showConfirm = !showConfirm">
                <EyeIcon v-if="!showConfirm" class="auth-toggle-icon" aria-hidden="true" />
                <EyeSlashIcon v-else class="auth-toggle-icon" aria-hidden="true" />
              </button>
            </div>
          </template>
        </FormField>

        <FormField label="Transaction PIN (4 digits)" :error="s3Errors.pin" required>
          <template #default="{ id, describedBy }">
            <div class="auth-input-wrap">
              <ShieldCheckIcon class="auth-input-icon" aria-hidden="true" />
              <input :id="id" v-bind="pinAttrs" v-model="pin" :type="showPin ? 'text' : 'password'" inputmode="numeric" maxlength="4" autocomplete="off" placeholder="••••" :aria-describedby="describedBy" :aria-invalid="!!s3Errors.pin || null" class="auth-input auth-input--with-icon auth-input--with-toggle" />
              <button type="button" class="auth-toggle" :aria-label="showPin ? 'Hide PIN' : 'Show PIN'" :aria-pressed="showPin" @click="showPin = !showPin">
                <EyeIcon v-if="!showPin" class="auth-toggle-icon" aria-hidden="true" />
                <EyeSlashIcon v-else class="auth-toggle-icon" aria-hidden="true" />
              </button>
            </div>
          </template>
        </FormField>

        <label class="reg-terms">
          <input v-bind="termsAttrs" v-model="termsAccepted" type="checkbox" class="reg-terms-check" />
          <span>I agree to the <a href="#" class="auth-link">Terms of Service</a> and <a href="#" class="auth-link">Privacy Policy</a>.</span>
        </label>
        <p v-if="s3Errors.terms_accepted" class="reg-terms-error">{{ s3Errors.terms_accepted }}</p>
      </template>

      <ErrorState v-if="serverError" :message="serverError" compact />

      <div class="reg-actions">
        <button v-if="step > 1" type="button" class="reg-prev" :disabled="submitting" @click="onPrev">Back</button>
        <button type="submit" class="auth-submit reg-next" :disabled="submitting">
          {{ submitting ? 'Creating…' : step < 3 ? 'Next' : 'Create account' }}
        </button>
      </div>
    </form>

    <template #footer>
      <p class="auth-footer-note">
        Already have an account?
        <RouterLink to="/login">Sign in</RouterLink>
      </p>
    </template>
  </AuthShell>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  UserIcon, EnvelopeIcon, PhoneIcon, LockClosedIcon, ShieldCheckIcon,
  EyeIcon, EyeSlashIcon, CheckIcon, GlobeAltIcon, BuildingLibraryIcon,
  BanknotesIcon, CakeIcon,
} from '@heroicons/vue/24/solid'
import AuthShell from '../../components/auth/AuthShell.vue'
import FormField from '../../components/ui/FormField.vue'
import ErrorState from '../../components/ui/ErrorState.vue'
import { authApi } from '../../api/auth'
import { useSiteStore } from '../../stores/site'
import { useValidatedForm } from '../../composables/useValidatedForm'
import {
  registerStep1Schema, registerStep2Schema, registerStep3Schema,
} from '../../validation/schemas'
import accountTypes from '../../data/account-types.json'
import { currencyList } from '../../utils/currency'
import { countryList, detectCountryCode } from '../../utils/countries'

// Shared catalogs sorted for stable dropdown order. All three come from
// their canonical JSON so a change in include/data/*.json cascades here
// via HMR without a code edit.
const CURRENCY_OPTIONS = currencyList()
const COUNTRY_OPTIONS = countryList()
const ACCOUNT_TYPE_OPTIONS = accountTypes

const router = useRouter()
const toast = useToast()
const site = useSiteStore()

const step = ref(1)
const hp = ref('') // honeypot — real users never touch this
const showPw = ref(false)
const showConfirm = ref(false)
const showPin = ref(false)
const submitting = ref(false)
const serverError = ref('')

// 18 years ago today — cap the date picker so browsers pre-limit it.
const maxDob = computed(() => {
  const d = new Date()
  d.setFullYear(d.getFullYear() - 18)
  return d.toISOString().slice(0, 10)
})

const stepMeta = computed(() => [
  { title: 'Create your account', subtitle: 'Tell us a bit about yourself.' },
  { title: 'Account details', subtitle: 'How we\'ll reach you and set up your account.' },
  { title: 'Secure your account', subtitle: 'Choose a password and a transaction PIN.' },
][step.value - 1])

// Per-step forms so validate() can be triggered against each step in
// isolation without early-failing on fields that belong to another step.
const step1Form = useValidatedForm(registerStep1Schema, {
  initialValues: { firstname: '', lastname: '', dob: '', country: '' },
})
const [firstname, firstnameAttrs] = step1Form.defineField('firstname')
const [lastname, lastnameAttrs] = step1Form.defineField('lastname')
const [dob, dobAttrs] = step1Form.defineField('dob')
const [country, countryAttrs] = step1Form.defineField('country')
const s1Errors = step1Form.errors

const step2Form = useValidatedForm(registerStep2Schema, {
  initialValues: { email: '', phone: '', currency: '', acct_type: '' },
})
const [email, emailAttrs] = step2Form.defineField('email')
const [phone, phoneAttrs] = step2Form.defineField('phone')
const [currency, currencyAttrs] = step2Form.defineField('currency')
const [acctType, acctTypeAttrs] = step2Form.defineField('acct_type')
const s2Errors = step2Form.errors

const step3Form = useValidatedForm(registerStep3Schema, {
  initialValues: { password: '', confirm_password: '', pin: '', terms_accepted: false },
})
const [password, passwordAttrs] = step3Form.defineField('password')
const [confirmPassword, confirmAttrs] = step3Form.defineField('confirm_password')
const [pin, pinAttrs] = step3Form.defineField('pin')
const [termsAccepted, termsAttrs] = step3Form.defineField('terms_accepted')
const s3Errors = step3Form.errors

const strength = computed(() => {
  const pw = password.value || ''
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

// Best-effort auto-fill of the country dropdown so the user doesn't have
// to scroll through 240+ options first. Never overrides a user's manual
// choice — only sets a value while the field is still empty.
//   1. Prefer the server-side IP hint from /api/site-config.php if the
//      backend was able to resolve it (accurate, no third-party call).
//   2. Fall back to the browser's own Intl locale / timezone.
const applyDetectedCountry = () => {
  if (country.value) return
  const codes = [site.detectedCountryCode, detectCountryCode()].filter(Boolean)
  for (const code of codes) {
    const known = COUNTRY_OPTIONS.find((c) => c.code === String(code).toUpperCase())
    if (known) {
      country.value = known.code
      return
    }
  }
}

onMounted(() => {
  applyDetectedCountry()
})

const onPrev = () => {
  serverError.value = ''
  if (step.value > 1) step.value -= 1
}

const onNext = async () => {
  serverError.value = ''
  const form = step.value === 1 ? step1Form : step.value === 2 ? step2Form : step3Form
  const result = await form.validate()
  if (!result.valid) return

  if (step.value < 3) {
    step.value += 1
    return
  }

  await submit()
}

const submit = async () => {
  submitting.value = true
  try {
    const { data } = await authApi.register({
      firstname: firstname.value,
      lastname: lastname.value,
      dob: dob.value,
      country: country.value,
      email: email.value,
      phone: phone.value,
      currency: currency.value,
      acct_type: acctType.value,
      password: password.value,
      confirm_password: confirmPassword.value,
      pin: pin.value,
      terms_accepted: termsAccepted.value,
      hp_website: hp.value,
    })
    if (!data?.ok) throw new Error(data?.message || 'Sign-up failed')
    toast.success(data?.message || 'Account created — check your email once approved.')
    await router.push(data?.data?.next_route || '/login')
  } catch (err) {
    serverError.value = err?.response?.data?.message || err.message || 'Unable to create account'
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped>
.reg-stepper {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.6rem;
  margin: 0 0 var(--space-4);
}
.reg-step-dot {
  width: 1.9rem;
  height: 1.9rem;
  border-radius: var(--radius-pill);
  background: var(--surface-muted);
  color: var(--text-muted);
  font-weight: 700;
  font-size: 0.8rem;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid var(--border);
  transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease;
}
.reg-step-dot--active {
  background: var(--accent);
  color: var(--text-on-accent);
  border-color: var(--accent);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 18%, transparent);
}
.reg-step-dot--done {
  background: var(--accent-strong);
  color: var(--text-on-accent);
  border-color: var(--accent-strong);
}
.reg-step-check { width: 1rem; height: 1rem; }
.reg-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--space-3);
}
@media (max-width: 480px) {
  .reg-row { grid-template-columns: 1fr; }
}
.reg-terms {
  display: flex;
  align-items: flex-start;
  gap: 0.6rem;
  font-size: 0.85rem;
  color: var(--text-secondary);
  line-height: 1.45;
  cursor: pointer;
  padding: 0.25rem 0;
}
.reg-terms-check {
  margin-top: 0.15rem;
  width: 1.05rem;
  height: 1.05rem;
  accent-color: var(--accent);
  flex-shrink: 0;
}
.reg-terms-error {
  color: var(--danger-fg);
  font-size: 0.8rem;
  margin: -0.4rem 0 0;
}
.reg-actions {
  display: flex;
  gap: var(--space-3);
  align-items: center;
  margin-top: var(--space-2);
}
.reg-prev {
  height: 3rem;
  padding: 0 var(--space-4);
  border-radius: var(--radius-pill);
  border: 1px solid var(--btn-outline-border);
  background: var(--btn-outline-bg);
  color: var(--btn-outline-fg);
  font-weight: 600;
  font-size: 0.9rem;
  font-family: inherit;
  cursor: pointer;
  transition: transform 0.08s ease, background-color 0.15s ease;
}
.reg-prev:hover:not(:disabled) {
  background: color-mix(in srgb, var(--text-primary) 6%, transparent);
}
.reg-prev:active:not(:disabled) { transform: scale(0.98); }
.reg-prev:disabled { opacity: 0.5; cursor: not-allowed; }
.reg-next { flex: 1; margin-top: 0; }
</style>
