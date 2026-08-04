<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  ChevronLeftIcon, UserIcon, PhoneIcon, EnvelopeIcon,
} from '@heroicons/vue/24/solid'
import client from '../api/client'
import ErrorState from '../components/ui/ErrorState.vue'

const router = useRouter()
const toast = useToast()

const loading = ref(true)
const error = ref('')
const saving = ref(false)

const firstname = ref('')
const lastname = ref('')
const phone = ref('')
const emailReadonly = ref('')
const acctNoReadonly = ref('')

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
    emailReadonly.value = d.email ?? ''
    acctNoReadonly.value = d.acct_no ?? ''
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
          <p class="subtitle">Update the name and phone on your account</p>
        </div>
      </header>

      <template v-if="loading">
        <div class="skeleton skeleton-form" />
      </template>

      <ErrorState v-else-if="error" :message="error" />

      <template v-else>
        <section class="card">
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

          <div class="field">
            <label class="label" for="pe-phone">Phone number</label>
            <div class="input-wrap">
              <PhoneIcon class="input-icon" aria-hidden="true" />
              <input id="pe-phone" v-model="phone" type="tel" autocomplete="tel"
                     placeholder="+1 555 000 0000"
                     class="input input--with-icon" :disabled="saving" />
            </div>
          </div>

          <!-- Read-only reference fields — help user confirm the account
               they're editing without opening another tab. Not editable
               here (email + account number require support intervention). -->
          <div class="field field--locked">
            <label class="label" for="pe-email">Email <span class="lock-tag">locked</span></label>
            <div class="input-wrap">
              <EnvelopeIcon class="input-icon" aria-hidden="true" />
              <input id="pe-email" :value="emailReadonly" type="email"
                     class="input input--with-icon input--readonly" readonly />
            </div>
            <p class="hint">To change your email, contact support.</p>
          </div>

          <button type="button" class="submit" :disabled="!canSave" @click="save">
            {{ saving ? 'Saving…' : 'Save changes' }}
          </button>
        </section>
      </template>
    </div>
  </div>
</template>

<style scoped>
.page { min-height: 100vh; background: var(--bg-gradient); padding: var(--space-5) var(--space-4) 7rem; }
.content { max-width: 30rem; margin: 0 auto; display: flex; flex-direction: column; gap: var(--space-4); }
.header { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) 0 var(--space-1); }
.back { width: 2.5rem; height: 2.5rem; border-radius: 0.7rem; border: 1px solid var(--border); background: transparent; color: var(--text-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.back:hover { background: color-mix(in srgb, var(--text-primary) 6%, transparent); }
.back:active { transform: scale(0.95); }
.back-icon { width: 1.15rem; height: 1.15rem; }
.title { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; line-height: 1.1; }
.subtitle { font-size: 0.8rem; color: var(--text-secondary); margin: 0.15rem 0 0; }

.card { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-5) var(--space-4); box-shadow: var(--shadow-card); display: flex; flex-direction: column; gap: var(--space-4); }
.field { display: flex; flex-direction: column; gap: 0.4rem; }
.field--locked { opacity: 0.9; }
.label { font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; display: inline-flex; align-items: center; gap: 0.5rem; }
.lock-tag { font-size: 0.55rem; padding: 0.1rem 0.4rem; border-radius: var(--radius-pill); background: var(--surface-muted); color: var(--text-muted); letter-spacing: 0.04em; }
.hint { font-size: 0.75rem; color: var(--text-muted); margin: 0.1rem 0 0; }

.input-wrap { position: relative; display: flex; align-items: center; }
.input { width: 100%; padding: 0.85rem 1rem; border-radius: var(--radius-md); border: 1px solid transparent; background: var(--surface-muted); color: var(--text-primary); font-size: 0.95rem; font-family: inherit; outline: none; transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease; }
.input:focus { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent); }
.input:disabled { opacity: 0.6; cursor: not-allowed; }
.input--readonly { background: var(--surface-muted); color: var(--text-secondary); cursor: default; }
.input-icon { position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%); width: 1.25rem; height: 1.25rem; color: var(--text-primary); pointer-events: none; }
.input--with-icon { padding-left: 2.75rem; }

.submit { width: 100%; height: 3rem; padding: 0 var(--space-4); border-radius: var(--radius-pill); border: 1px solid transparent; background: var(--btn-primary-bg); color: var(--btn-primary-fg); font-weight: 600; font-size: 0.9rem; font-family: inherit; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; transition: transform 0.08s ease, opacity 0.15s ease; margin-top: var(--space-2); }
.submit:hover:not(:disabled) { opacity: 0.92; }
.submit:active:not(:disabled) { transform: scale(0.98); }
.submit:disabled { opacity: 0.5; cursor: not-allowed; }

.skeleton { border-radius: var(--radius-lg); background: var(--surface-muted); animation: pulse 1.6s ease-in-out infinite; }
.skeleton-form { height: 22rem; }
@keyframes pulse { 0%, 100% { opacity: 0.5; } 50% { opacity: 0.85; } }
</style>
