<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  ChevronLeftIcon, UserIcon, PhoneIcon, EnvelopeIcon,
  CalendarDaysIcon, BriefcaseIcon, GlobeAltIcon, MapPinIcon,
  HomeIcon, HeartIcon, IdentificationIcon, PencilSquareIcon,
} from '@heroicons/vue/24/solid'
import client from '../api/client'
import ErrorState from '../components/ui/ErrorState.vue'
import EmailChangeModal from '../components/EmailChangeModal.vue'
import LoadingRegion from '../components/skeletons/LoadingRegion.vue'
import SkeletonText from '../components/skeletons/SkeletonText.vue'
import SkeletonField from '../components/skeletons/SkeletonField.vue'
import { countryList, countryName } from '../utils/countries'

const router = useRouter()
const toast = useToast()

const loading = ref(true)
const error = ref('')
const saving = ref(false)

// Form fields — every column the profile.php endpoint can update.
const firstname = ref('')
const lastname = ref('')
const phone = ref('')
const dob = ref('')
const gender = ref('')
const marital = ref('')
const occupation = ref('')
const country = ref('')
const state = ref('')
const address = ref('')

// Read-only reference data.
const emailReadonly = ref('')
const acctNoReadonly = ref('')
const pendingEmail = ref(null)

const emailModalOpen = ref(false)

const countries = computed(() => countryList())

// dob max = today, min = 120 years ago. Prevents accidental future dates
// and matches the server-side range check.
const dobMax = computed(() => new Date().toISOString().slice(0, 10))
const dobMin = computed(() => {
  const d = new Date(); d.setFullYear(d.getFullYear() - 120)
  return d.toISOString().slice(0, 10)
})

async function loadProfile() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/api/user/profile.php')
    if (!data?.ok) throw new Error(data?.message || 'Unable to load profile')
    const d = data.data || {}
    firstname.value = d.firstname ?? ''
    lastname.value = d.lastname ?? ''
    phone.value = d.phone ?? ''
    dob.value = d.acct_dob ?? ''
    gender.value = d.acct_gender ?? ''
    marital.value = d.marital_status ?? ''
    occupation.value = d.acct_occupation ?? ''
    // Server stores full name. If a legacy row still has an ISO code,
    // resolve to a name so the <select> matches an option.
    country.value = d.country ? countryName(d.country) : ''
    state.value = d.state ?? ''
    address.value = d.acct_address ?? ''
    emailReadonly.value = d.email ?? ''
    acctNoReadonly.value = d.acct_no ?? ''
    pendingEmail.value = d.pending_email || null
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'Unable to load profile'
  } finally {
    loading.value = false
  }
}
onMounted(loadProfile)

const canSave = computed(() =>
  firstname.value.trim().length >= 1 &&
  lastname.value.trim().length >= 1 &&
  !saving.value,
)

async function save() {
  if (!canSave.value) {
    toast.error('First and last name are required.')
    return
  }
  saving.value = true
  try {
    const { data } = await client.post('/api/user/profile.php', {
      action: 'update',
      firstname: firstname.value.trim(),
      lastname: lastname.value.trim(),
      phone: phone.value.trim(),
      acct_dob: dob.value || '',
      acct_gender: gender.value || '',
      marital_status: marital.value || '',
      acct_occupation: occupation.value.trim(),
      country: country.value || '',
      state: state.value.trim(),
      acct_address: address.value.trim(),
    })
    if (!data?.ok) throw new Error(data?.message || 'Update failed')
    toast.success('Profile updated.')
    router.push('/profile')
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Could not update profile.')
  } finally {
    saving.value = false
  }
}

// This view hand-rolls `.field` rather than using <FormField>: a 0.72rem
// uppercase label, a 0.4rem gap and a 0.8rem-padded input (3.15rem tall).
const fieldMetrics = { labelSize: '0.72rem', gap: '0.4rem', height: '3.15rem' }

function openEmailModal() { emailModalOpen.value = true }
function onEmailUpdated(newAddress) {
  if (newAddress) emailReadonly.value = newAddress
  loadProfile()
}
</script>

<template>
  <div class="page">
    <div class="content">
      <header class="header">
        <button type="button" class="back" aria-label="Go back" @click="router.push('/profile')">
          <ChevronLeftIcon class="back-icon" aria-hidden="true" />
        </button>
        <div class="titles">
          <h1 class="title">Personal details</h1>
          <p class="subtitle">Keep your account information accurate and up to date</p>
        </div>
      </header>

      <!-- The real form is fixed — every field renders regardless of the payload
           — so the skeleton can mirror it row for row. `.card`, `.grid-2`,
           `.field` and `.ref-rows` are reused so padding, gaps and the 480px
           column break stay in sync with the live layout. -->
      <LoadingRegion v-if="loading" label="your personal details">
        <section class="card">
          <SkeletonText size="1rem" width="4.5rem" />
          <div v-for="row in 3" :key="row" class="grid-2">
            <SkeletonField v-bind="fieldMetrics" label="5.5rem" />
            <SkeletonField v-bind="fieldMetrics" label="5rem" />
          </div>
        </section>

        <section class="card">
          <SkeletonText size="1rem" width="4rem" />
          <SkeletonField v-bind="fieldMetrics" label="7rem" />
          <SkeletonField v-bind="fieldMetrics" label="9.5rem" hint="16rem" hint-gap="0.1rem" />
          <div class="grid-2">
            <SkeletonField v-bind="fieldMetrics" label="4.5rem" />
            <SkeletonField v-bind="fieldMetrics" label="6.5rem" />
          </div>
          <SkeletonField v-bind="fieldMetrics" label="7.5rem" height="5.9rem" />
        </section>

        <section class="card card--muted">
          <SkeletonText size="1rem" width="9rem" />
          <SkeletonText class="sk-card-sub" size="0.8rem" :line-height="1.45" :lines="2"
                        :width="['100%', '58%']" />
          <div class="ref-rows">
            <div class="ref-row">
              <SkeletonText size="0.8rem" width="8rem" />
              <SkeletonText size="0.9rem" width="7rem" />
            </div>
          </div>
        </section>

        <div class="skeleton sk-submit" />
      </LoadingRegion>

      <ErrorState v-else-if="error" :message="error" />

      <template v-else>
        <!-- Identity + contact -->
        <section class="card">
          <h3 class="card-title">Identity</h3>

          <div class="grid-2">
            <div class="field">
              <label class="label" for="pe-firstname">First name</label>
              <div class="input-wrap">
                <UserIcon class="input-icon" aria-hidden="true" />
                <input id="pe-firstname" v-model="firstname" type="text" autocomplete="given-name"
                       class="input input--with-icon" :disabled="saving" />
              </div>
            </div>

            <div class="field">
              <label class="label" for="pe-lastname">Last name</label>
              <div class="input-wrap">
                <UserIcon class="input-icon" aria-hidden="true" />
                <input id="pe-lastname" v-model="lastname" type="text" autocomplete="family-name"
                       class="input input--with-icon" :disabled="saving" />
              </div>
            </div>
          </div>

          <div class="grid-2">
            <div class="field">
              <label class="label" for="pe-dob">Date of birth</label>
              <div class="input-wrap">
                <CalendarDaysIcon class="input-icon" aria-hidden="true" />
                <input id="pe-dob" v-model="dob" type="date" :min="dobMin" :max="dobMax"
                       class="input input--with-icon" :disabled="saving" />
              </div>
            </div>

            <div class="field">
              <label class="label" for="pe-gender">Gender</label>
              <div class="input-wrap">
                <IdentificationIcon class="input-icon" aria-hidden="true" />
                <select id="pe-gender" v-model="gender" class="input input--with-icon" :disabled="saving">
                  <option value="">Prefer not to say</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                  <option value="prefer_not_to_say">Prefer not to say</option>
                </select>
              </div>
            </div>
          </div>

          <div class="grid-2">
            <div class="field">
              <label class="label" for="pe-marital">Marital status</label>
              <div class="input-wrap">
                <HeartIcon class="input-icon" aria-hidden="true" />
                <select id="pe-marital" v-model="marital" class="input input--with-icon" :disabled="saving">
                  <option value="">—</option>
                  <option value="single">Single</option>
                  <option value="married">Married</option>
                  <option value="divorced">Divorced</option>
                  <option value="widowed">Widowed</option>
                  <option value="other">Other</option>
                </select>
              </div>
            </div>

            <div class="field">
              <label class="label" for="pe-occupation">Occupation</label>
              <div class="input-wrap">
                <BriefcaseIcon class="input-icon" aria-hidden="true" />
                <input id="pe-occupation" v-model="occupation" type="text" maxlength="100"
                       placeholder="e.g. Software Engineer"
                       class="input input--with-icon" :disabled="saving" />
              </div>
            </div>
          </div>
        </section>

        <!-- Contact + location -->
        <section class="card">
          <h3 class="card-title">Contact</h3>

          <div class="field">
            <label class="label" for="pe-phone">Phone number</label>
            <div class="input-wrap">
              <PhoneIcon class="input-icon" aria-hidden="true" />
              <input id="pe-phone" v-model="phone" type="tel" autocomplete="tel"
                     placeholder="+1 555 000 0000"
                     class="input input--with-icon" :disabled="saving" />
            </div>
          </div>

          <!-- Email is disabled. The pencil button on the right opens the
               OTP-verified change flow so a compromised session can't
               silently rewrite the login email. -->
          <div class="field">
            <label class="label" for="pe-email">
              Email <span class="lock-tag">verified change</span>
            </label>
            <div class="input-wrap">
              <EnvelopeIcon class="input-icon" aria-hidden="true" />
              <input id="pe-email" :value="emailReadonly" type="email"
                     class="input input--with-icon input--with-toggle input--readonly" readonly />
              <button type="button" class="toggle" aria-label="Change email"
                      title="Change email (requires verification)"
                      @click="openEmailModal">
                <PencilSquareIcon class="toggle-icon" aria-hidden="true" />
              </button>
            </div>
            <p v-if="pendingEmail" class="pending">
              Pending verification for <strong>{{ pendingEmail.new_email }}</strong>.
              <button type="button" class="pending-link" @click="openEmailModal">Enter code</button>
            </p>
            <p v-else class="hint">Changing your sign-in email needs a code sent to the new address.</p>
          </div>

          <div class="grid-2">
            <div class="field">
              <label class="label" for="pe-country">Country</label>
              <div class="input-wrap">
                <GlobeAltIcon class="input-icon" aria-hidden="true" />
                <select id="pe-country" v-model="country" class="input input--with-icon" :disabled="saving">
                  <option value="">Select country</option>
                  <option v-for="c in countries" :key="c.code" :value="c.name">{{ c.name }}</option>
                </select>
              </div>
            </div>

            <div class="field">
              <label class="label" for="pe-state">State / Region</label>
              <div class="input-wrap">
                <MapPinIcon class="input-icon" aria-hidden="true" />
                <input id="pe-state" v-model="state" type="text" maxlength="100"
                       class="input input--with-icon" :disabled="saving" />
              </div>
            </div>
          </div>

          <div class="field">
            <label class="label" for="pe-address">Street address</label>
            <div class="input-wrap input-wrap--textarea">
              <HomeIcon class="input-icon input-icon--top" aria-hidden="true" />
              <textarea id="pe-address" v-model="address" rows="3" maxlength="300"
                        placeholder="Number, street, apartment"
                        class="input input--with-icon input--textarea" :disabled="saving"></textarea>
            </div>
          </div>
        </section>

        <!-- Locked / reference -->
        <section class="card card--muted" aria-label="Account reference">
          <h3 class="card-title">Account reference</h3>
          <p class="card-sub">These identifiers cannot be edited. Contact support if they need to change.</p>

          <div class="ref-rows">
            <div class="ref-row">
              <span class="ref-label">Account number</span>
              <span class="ref-value ref-value--mono">{{ acctNoReadonly || '—' }}</span>
            </div>
          </div>
        </section>

        <button type="button" class="submit" :disabled="!canSave" @click="save">
          {{ saving ? 'Saving…' : 'Save changes' }}
        </button>
      </template>
    </div>

    <EmailChangeModal
      :open="emailModalOpen"
      :current-email="emailReadonly"
      :pending="pendingEmail"
      @close="emailModalOpen = false"
      @updated="onEmailUpdated"
    />
  </div>
</template>

<style scoped>
.page { min-height: 100vh; background: var(--bg-gradient); padding: var(--space-5) var(--space-4) 7rem; }
.content { max-width: 32rem; margin: 0 auto; display: flex; flex-direction: column; gap: var(--space-4); }
.header { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) 0 var(--space-1); }
.back { width: 2.5rem; height: 2.5rem; border-radius: 1.7rem; border: 1px solid var(--border); background: transparent; color: var(--text-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.back:hover { background: color-mix(in srgb, var(--text-primary) 6%, transparent); }
.back:active { transform: scale(0.95); }
.back-icon { width: 1.15rem; height: 1.15rem; }
.title { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; line-height: 1.1; }
.subtitle { font-size: 0.8rem; color: var(--text-secondary); margin: 0.15rem 0 0; }

.card { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-5) var(--space-4); box-shadow: var(--shadow-card); display: flex; flex-direction: column; gap: var(--space-3); }
.card--muted { background: var(--surface-muted); box-shadow: none; }
.card-title { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.card-sub { font-size: 0.8rem; color: var(--text-secondary); margin: -0.2rem 0 var(--space-2); line-height: 1.45; }

.grid-2 { display: grid; grid-template-columns: 1fr; gap: var(--space-3); }
@media (min-width: 480px) { .grid-2 { grid-template-columns: 1fr 1fr; } }

.field { display: flex; flex-direction: column; gap: 0.4rem; min-width: 0; }
.label { font-size: 0.72rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 0.5rem; }
.lock-tag { font-size: 0.55rem; padding: 0.1rem 0.4rem; border-radius: var(--radius-pill); background: var(--surface-muted); color: var(--text-muted); letter-spacing: 0.04em; text-transform: uppercase; font-weight: 700; }
.hint { font-size: 0.72rem; color: var(--text-muted); margin: 0.1rem 0 0; line-height: 1.45; }
.pending { font-size: 0.75rem; color: var(--warning-fg); background: color-mix(in srgb, var(--warning-fg) 10%, transparent); padding: 0.5rem 0.7rem; border-radius: var(--radius-md); margin: 0.1rem 0 0; line-height: 1.4; }
.pending-link { background: transparent; border: none; color: var(--accent-strong); font-weight: 600; cursor: pointer; padding: 0 0 0 0.2rem; font-size: 0.75rem; text-decoration: underline; text-underline-offset: 2px; }

.input-wrap { position: relative; display: flex; align-items: center; }
.input-wrap--textarea { align-items: stretch; }
.input { width: 100%; padding: 0.8rem 1rem; border-radius: var(--radius-md); border: 1px solid transparent; background: var(--surface-muted); color: var(--text-primary); font-size: 0.95rem; font-family: inherit; outline: none; transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease; }
.input:focus { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent); }
.input:disabled { opacity: 0.6; cursor: not-allowed; }
.input--readonly { background: var(--surface-muted); color: var(--text-secondary); cursor: default; }
.input--textarea { resize: vertical; min-height: 4.5rem; padding-top: 0.85rem; line-height: 1.45; }
.input-icon { position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%); width: 1.2rem; height: 1.2rem; color: var(--text-primary); pointer-events: none; }
.input-icon--top { top: 0.9rem; transform: none; }
.input--with-icon { padding-left: 2.75rem; }
.input--with-toggle { padding-right: 2.8rem; }

.toggle { position: absolute; right: 0.4rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--accent-strong); cursor: pointer; padding: 0.4rem; border-radius: var(--radius-sm); display: inline-flex; align-items: center; justify-content: center; }
.toggle:hover { background: color-mix(in srgb, var(--accent) 10%, transparent); }
.toggle-icon { width: 1.15rem; height: 1.15rem; }

.ref-rows { display: flex; flex-direction: column; gap: 0.4rem; }
.ref-row { display: flex; justify-content: space-between; align-items: baseline; gap: var(--space-4); padding: 0.35rem 0; }
.ref-label { color: var(--text-secondary); font-size: 0.8rem; }
.ref-value { color: var(--text-primary); font-size: 0.9rem; font-weight: 600; text-align: right; word-break: break-word; }
.ref-value--mono { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; letter-spacing: 0.02em; }

.submit { width: 100%; height: 3rem; padding: 0 var(--space-4); border-radius: var(--radius-pill); border: 1px solid transparent; background: var(--btn-primary-bg); color: var(--btn-primary-fg); font-weight: 600; font-size: 0.9rem; font-family: inherit; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; transition: transform 0.08s ease, opacity 0.15s ease; margin-top: var(--space-1); }
.submit:hover:not(:disabled) { opacity: 0.92; }
.submit:active:not(:disabled) { transform: scale(0.98); }
.submit:disabled { opacity: 0.5; cursor: not-allowed; }

/* Skeleton — .card, .grid-2 and .ref-rows are reused as-is. */
.sk-card-sub { margin: -0.2rem 0 var(--space-2); }
.sk-submit { width: 100%; height: 3rem; border-radius: var(--radius-pill); margin-top: var(--space-1); }
</style>
