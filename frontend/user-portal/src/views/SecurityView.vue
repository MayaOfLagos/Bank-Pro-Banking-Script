<script setup>
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  ChevronLeftIcon, LockClosedIcon, ShieldCheckIcon,
  EyeIcon, EyeSlashIcon,
} from '@heroicons/vue/24/solid'
import client from '../api/client'

const router = useRouter()
const toast = useToast()

// ─── Password state ───────────────────────────────────────────────────────
const oldPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const showOld = ref(false)
const showNew = ref(false)
const showConfirm = ref(false)
const savingPassword = ref(false)

const strength = computed(() => {
  const pw = newPassword.value
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

async function savePassword() {
  if (!oldPassword.value || !newPassword.value || !confirmPassword.value) {
    toast.error('Fill in all password fields.')
    return
  }
  if (newPassword.value !== confirmPassword.value) {
    toast.error('New password and confirmation do not match.')
    return
  }
  if (newPassword.value.length < 8 || !/[A-Za-z]/.test(newPassword.value) || !/\d/.test(newPassword.value)) {
    toast.error('New password must be 8+ characters with a letter and a number.')
    return
  }
  savingPassword.value = true
  try {
    const { data } = await client.post('/api/user/profile-password.php', {
      old_password: oldPassword.value,
      new_password: newPassword.value,
      confirm_password: confirmPassword.value,
    })
    if (!data?.ok) throw new Error(data?.message || 'Password change failed')
    toast.success('Password updated.')
    oldPassword.value = ''; newPassword.value = ''; confirmPassword.value = ''
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Could not update password.')
  } finally {
    savingPassword.value = false
  }
}

// ─── PIN state ────────────────────────────────────────────────────────────
const currentPin = ref('')
const newPin = ref('')
const confirmPin = ref('')
const showCurrentPin = ref(false)
const showNewPin = ref(false)
const showConfirmPin = ref(false)
const savingPin = ref(false)

async function savePin() {
  if (!/^\d{4}$/.test(currentPin.value) || !/^\d{4}$/.test(newPin.value) || !/^\d{4}$/.test(confirmPin.value)) {
    toast.error('PINs must be exactly 4 digits.')
    return
  }
  if (newPin.value !== confirmPin.value) {
    toast.error('New PIN and confirmation do not match.')
    return
  }
  if (newPin.value === currentPin.value) {
    toast.error('New PIN matches the current one.')
    return
  }
  savingPin.value = true
  try {
    const { data } = await client.post('/api/user/profile-pin.php', {
      current_pin: currentPin.value,
      new_pin: newPin.value,
      confirm_pin: confirmPin.value,
    })
    if (!data?.ok) throw new Error(data?.message || 'PIN change failed')
    toast.success('Transaction PIN updated.')
    currentPin.value = ''; newPin.value = ''; confirmPin.value = ''
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Could not update PIN.')
  } finally {
    savingPin.value = false
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
          <h1 class="title">Security</h1>
          <p class="subtitle">Change your password and transaction PIN</p>
        </div>
      </header>

      <!-- Password card -->
      <section class="card" aria-label="Change password">
        <h3 class="card-title">Password</h3>
        <p class="card-sub">Sign-in password for your account.</p>

        <div class="field">
          <label class="label" for="sv-oldpw">Current password</label>
          <div class="input-wrap">
            <LockClosedIcon class="input-icon" aria-hidden="true" />
            <input id="sv-oldpw" v-model="oldPassword" :type="showOld ? 'text' : 'password'"
                   autocomplete="current-password" class="input input--with-icon input--with-toggle" :disabled="savingPassword" />
            <button type="button" class="toggle" :aria-label="showOld ? 'Hide password' : 'Show password'" @click="showOld = !showOld">
              <EyeIcon v-if="!showOld" class="toggle-icon" />
              <EyeSlashIcon v-else class="toggle-icon" />
            </button>
          </div>
        </div>

        <div class="field">
          <label class="label" for="sv-newpw">New password</label>
          <div class="input-wrap">
            <LockClosedIcon class="input-icon" aria-hidden="true" />
            <input id="sv-newpw" v-model="newPassword" :type="showNew ? 'text' : 'password'"
                   autocomplete="new-password" placeholder="At least 8 characters"
                   class="input input--with-icon input--with-toggle" :disabled="savingPassword" />
            <button type="button" class="toggle" :aria-label="showNew ? 'Hide password' : 'Show password'" @click="showNew = !showNew">
              <EyeIcon v-if="!showNew" class="toggle-icon" />
              <EyeSlashIcon v-else class="toggle-icon" />
            </button>
          </div>
        </div>

        <div v-if="newPassword" aria-live="polite" class="strength">
          <div class="strength-bars">
            <div v-for="segment in 4" :key="segment" class="strength-bar" :class="segment <= strength.score ? `strength-bar--${strength.tier}` : ''" />
          </div>
          <p class="strength-label" :class="`strength-label--${strength.tier}`">{{ strength.label }}</p>
        </div>

        <div class="field">
          <label class="label" for="sv-confirmpw">Confirm new password</label>
          <div class="input-wrap">
            <LockClosedIcon class="input-icon" aria-hidden="true" />
            <input id="sv-confirmpw" v-model="confirmPassword" :type="showConfirm ? 'text' : 'password'"
                   autocomplete="new-password" class="input input--with-icon input--with-toggle" :disabled="savingPassword" />
            <button type="button" class="toggle" :aria-label="showConfirm ? 'Hide password' : 'Show password'" @click="showConfirm = !showConfirm">
              <EyeIcon v-if="!showConfirm" class="toggle-icon" />
              <EyeSlashIcon v-else class="toggle-icon" />
            </button>
          </div>
        </div>

        <button type="button" class="submit" :disabled="savingPassword" @click="savePassword">
          {{ savingPassword ? 'Updating…' : 'Update password' }}
        </button>
      </section>

      <!-- PIN card -->
      <section class="card" aria-label="Change transaction PIN">
        <h3 class="card-title">Transaction PIN</h3>
        <p class="card-sub">4-digit PIN used to confirm transfers and withdrawals.</p>

        <div class="field">
          <label class="label" for="sv-oldpin">Current PIN</label>
          <div class="input-wrap">
            <ShieldCheckIcon class="input-icon" aria-hidden="true" />
            <input id="sv-oldpin" v-model="currentPin" :type="showCurrentPin ? 'text' : 'password'"
                   inputmode="numeric" maxlength="4" autocomplete="off" placeholder="••••"
                   class="input input--with-icon input--with-toggle input--pin" :disabled="savingPin" />
            <button type="button" class="toggle" :aria-label="showCurrentPin ? 'Hide PIN' : 'Show PIN'" @click="showCurrentPin = !showCurrentPin">
              <EyeIcon v-if="!showCurrentPin" class="toggle-icon" />
              <EyeSlashIcon v-else class="toggle-icon" />
            </button>
          </div>
        </div>

        <div class="field">
          <label class="label" for="sv-newpin">New PIN</label>
          <div class="input-wrap">
            <ShieldCheckIcon class="input-icon" aria-hidden="true" />
            <input id="sv-newpin" v-model="newPin" :type="showNewPin ? 'text' : 'password'"
                   inputmode="numeric" maxlength="4" autocomplete="off" placeholder="••••"
                   class="input input--with-icon input--with-toggle input--pin" :disabled="savingPin" />
            <button type="button" class="toggle" :aria-label="showNewPin ? 'Hide PIN' : 'Show PIN'" @click="showNewPin = !showNewPin">
              <EyeIcon v-if="!showNewPin" class="toggle-icon" />
              <EyeSlashIcon v-else class="toggle-icon" />
            </button>
          </div>
        </div>

        <div class="field">
          <label class="label" for="sv-confirmpin">Confirm new PIN</label>
          <div class="input-wrap">
            <ShieldCheckIcon class="input-icon" aria-hidden="true" />
            <input id="sv-confirmpin" v-model="confirmPin" :type="showConfirmPin ? 'text' : 'password'"
                   inputmode="numeric" maxlength="4" autocomplete="off" placeholder="••••"
                   class="input input--with-icon input--with-toggle input--pin" :disabled="savingPin" />
            <button type="button" class="toggle" :aria-label="showConfirmPin ? 'Hide PIN' : 'Show PIN'" @click="showConfirmPin = !showConfirmPin">
              <EyeIcon v-if="!showConfirmPin" class="toggle-icon" />
              <EyeSlashIcon v-else class="toggle-icon" />
            </button>
          </div>
        </div>

        <p class="note">
          Avoid obvious patterns like <strong>1234</strong>, <strong>0000</strong>, or repeats of the same digit.
        </p>

        <button type="button" class="submit" :disabled="savingPin" @click="savePin">
          {{ savingPin ? 'Updating…' : 'Update PIN' }}
        </button>
      </section>
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

.card { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-5) var(--space-4); box-shadow: var(--shadow-card); display: flex; flex-direction: column; gap: var(--space-3); }
.card-title { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.card-sub { font-size: 0.8rem; color: var(--text-secondary); margin: -0.2rem 0 var(--space-2); }

.field { display: flex; flex-direction: column; gap: 0.4rem; }
.label { font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }

.input-wrap { position: relative; display: flex; align-items: center; }
.input { width: 100%; padding: 0.85rem 1rem; border-radius: var(--radius-md); border: 1px solid transparent; background: var(--surface-muted); color: var(--text-primary); font-size: 0.95rem; font-family: inherit; outline: none; transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease; }
.input:focus { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent); }
.input:disabled { opacity: 0.6; cursor: not-allowed; }
.input-icon { position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%); width: 1.25rem; height: 1.25rem; color: var(--text-primary); pointer-events: none; }
.input--with-icon { padding-left: 2.75rem; }
.input--with-toggle { padding-right: 2.8rem; }
.input--pin { letter-spacing: 0.5em; font-weight: 700; font-size: 1.15rem; text-align: center; padding-left: 2.75rem; padding-right: 2.75rem; }
.toggle { position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); background: transparent; border: none; color: var(--text-secondary); cursor: pointer; padding: 0.4rem; border-radius: var(--radius-sm); display: inline-flex; align-items: center; justify-content: center; }
.toggle:hover { color: var(--text-primary); background: color-mix(in srgb, var(--text-primary) 5%, transparent); }
.toggle-icon { width: 1.15rem; height: 1.15rem; }

.strength { margin-top: -0.4rem; }
.strength-bars { display: flex; gap: 0.25rem; margin-bottom: 0.3rem; }
.strength-bar { height: 0.35rem; flex: 1; border-radius: var(--radius-pill); background: var(--divider); transition: background-color 0.2s ease; }
.strength-bar--weak { background: var(--danger-fg); }
.strength-bar--fair { background: var(--warning-fg); }
.strength-bar--good, .strength-bar--strong { background: var(--success-fg); }
.strength-label { font-size: 0.75rem; margin: 0; }
.strength-label--weak { color: var(--danger-fg); }
.strength-label--fair { color: var(--warning-fg); }
.strength-label--good, .strength-label--strong { color: var(--success-fg); }

.note { font-size: 0.8rem; color: var(--warning-fg); background: color-mix(in srgb, var(--warning-fg) 10%, transparent); padding: 0.65rem 0.85rem; border-radius: var(--radius-md); margin: 0; line-height: 1.45; }
.note strong { font-weight: 700; }

.submit { width: 100%; height: 3rem; padding: 0 var(--space-4); border-radius: var(--radius-pill); border: 1px solid transparent; background: var(--btn-primary-bg); color: var(--btn-primary-fg); font-weight: 600; font-size: 0.9rem; font-family: inherit; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; transition: transform 0.08s ease, opacity 0.15s ease; margin-top: var(--space-2); }
.submit:hover:not(:disabled) { opacity: 0.92; }
.submit:active:not(:disabled) { transform: scale(0.98); }
.submit:disabled { opacity: 0.5; cursor: not-allowed; }
</style>
