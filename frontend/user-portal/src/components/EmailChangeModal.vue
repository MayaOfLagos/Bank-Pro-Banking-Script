<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import {
  XMarkIcon, EnvelopeIcon, ShieldCheckIcon, ArrowPathIcon,
} from '@heroicons/vue/24/solid'
import client from '../api/client'

const props = defineProps({
  open: { type: Boolean, default: false },
  currentEmail: { type: String, default: '' },
  // If the server already has a pending change, jump straight to the
  // OTP step so the user isn't forced to re-enter the address.
  pending: { type: Object, default: null },
})

const emit = defineEmits(['close', 'updated'])

const toast = useToast()

const step = ref('email')            // 'email' | 'otp'
const newEmail = ref('')
const otp = ref('')
const requesting = ref(false)
const verifying = ref(false)
const cancelling = ref(false)

// Countdown timer state — recomputed off wall-clock so a paused tab
// (background throttling) still shows the correct remaining time.
const expiresAt = ref(0)             // unix seconds
const now = ref(Date.now())
let clockTimer = null

const resendCooldown = ref(0)        // seconds remaining before Resend is enabled
let cooldownTimer = null

const remainingLabel = computed(() => {
  if (!expiresAt.value) return ''
  const secs = Math.max(0, Math.floor((expiresAt.value * 1000 - now.value) / 1000))
  const m = Math.floor(secs / 60)
  const s = secs % 60
  return `${m}:${String(s).padStart(2, '0')}`
})

const otpExpired = computed(() =>
  expiresAt.value > 0 && now.value >= expiresAt.value * 1000,
)

function startClock() {
  stopClock()
  clockTimer = window.setInterval(() => { now.value = Date.now() }, 1000)
}
function stopClock() {
  if (clockTimer) window.clearInterval(clockTimer)
  clockTimer = null
}
function startCooldown(seconds) {
  resendCooldown.value = seconds
  if (cooldownTimer) window.clearInterval(cooldownTimer)
  cooldownTimer = window.setInterval(() => {
    resendCooldown.value -= 1
    if (resendCooldown.value <= 0) {
      window.clearInterval(cooldownTimer)
      cooldownTimer = null
      resendCooldown.value = 0
    }
  }, 1000)
}

function resetLocalState() {
  step.value = 'email'
  newEmail.value = ''
  otp.value = ''
  expiresAt.value = 0
  resendCooldown.value = 0
  if (cooldownTimer) { window.clearInterval(cooldownTimer); cooldownTimer = null }
}

// If a pending state is passed in when the modal opens, prefill it and
// jump to OTP — most likely the user closed and reopened mid-flow.
watch(() => props.open, (isOpen) => {
  if (isOpen) {
    if (props.pending?.new_email) {
      newEmail.value = props.pending.new_email
      const exp = props.pending.expires_at ? new Date(props.pending.expires_at).getTime() / 1000 : 0
      if (exp > Date.now() / 1000) {
        expiresAt.value = exp
        step.value = 'otp'
      }
    }
    startClock()
  } else {
    stopClock()
    resetLocalState()
  }
})

onMounted(() => { if (props.open) startClock() })
onUnmounted(() => {
  stopClock()
  if (cooldownTimer) window.clearInterval(cooldownTimer)
})

const canRequest = computed(() =>
  /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(newEmail.value.trim()) &&
  newEmail.value.trim().toLowerCase() !== props.currentEmail.trim().toLowerCase() &&
  !requesting.value,
)

async function requestCode() {
  if (!canRequest.value) {
    toast.error('Enter a valid new email address.')
    return
  }
  requesting.value = true
  try {
    const { data } = await client.post('/api/user/profile-email-request.php', {
      new_email: newEmail.value.trim(),
    })
    if (!data?.ok) throw new Error(data?.message || 'Could not send verification code')
    step.value = 'otp'
    otp.value = ''
    const exp = data?.data?.expires_at
    expiresAt.value = exp ? new Date(exp).getTime() / 1000 : Math.floor(Date.now() / 1000) + 15 * 60
    startCooldown(60)
    toast.success('Verification code sent. Check your new inbox.')
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Could not send code.')
  } finally {
    requesting.value = false
  }
}

async function verifyCode() {
  const trimmed = otp.value.trim()
  if (!/^\d{6}$/.test(trimmed)) {
    toast.error('Enter the 6-digit code.')
    return
  }
  verifying.value = true
  try {
    const { data } = await client.post('/api/user/profile-email-verify.php', {
      otp: trimmed,
    })
    if (!data?.ok) throw new Error(data?.message || 'Verification failed')
    toast.success('Email updated.')
    emit('updated', data?.data?.email || newEmail.value.trim())
    resetLocalState()
    emit('close')
  } catch (err) {
    const msg = err?.response?.data?.message || err.message || 'Could not verify code.'
    toast.error(msg)
    otp.value = ''
  } finally {
    verifying.value = false
  }
}

async function cancelPending() {
  cancelling.value = true
  try {
    await client.post('/api/user/profile-email-cancel.php', {})
  } catch {
    // Non-blocking — the modal closes regardless.
  } finally {
    cancelling.value = false
    resetLocalState()
    emit('close')
    emit('updated')  // Trigger a reload so the parent drops the pending badge.
  }
}

function backToEmail() {
  step.value = 'email'
  otp.value = ''
  expiresAt.value = 0
}
</script>

<template>
  <Teleport to="body">
    <transition name="ec-fade">
      <div v-if="open" class="scrim" @click.self="emit('close')">
        <div class="sheet" role="dialog" aria-modal="true" aria-labelledby="ec-title">
          <header class="head">
            <div class="head-icon"><EnvelopeIcon /></div>
            <div class="head-copy">
              <h2 id="ec-title" class="head-title">
                {{ step === 'email' ? 'Change email address' : 'Confirm your new email' }}
              </h2>
              <p class="head-sub">
                <template v-if="step === 'email'">
                  We'll send a 6-digit code to the new address.
                </template>
                <template v-else>
                  Code sent to <strong>{{ newEmail }}</strong>. Enter it below.
                </template>
              </p>
            </div>
            <button type="button" class="close" aria-label="Close" @click="emit('close')">
              <XMarkIcon />
            </button>
          </header>

          <!-- Step 1: enter new email -->
          <section v-if="step === 'email'" class="body">
            <div class="field">
              <label class="label" for="ec-current">Current email</label>
              <input id="ec-current" :value="currentEmail" type="email" readonly
                     class="input input--readonly" />
            </div>

            <div class="field">
              <label class="label" for="ec-new">New email</label>
              <input id="ec-new" v-model="newEmail" type="email" autocomplete="email"
                     placeholder="you@example.com"
                     class="input" :disabled="requesting"
                     @keydown.enter.prevent="requestCode" />
              <p class="hint">Only the address you can receive mail at right now — otherwise you'll be locked out on next sign-in.</p>
            </div>

            <button type="button" class="primary" :disabled="!canRequest" @click="requestCode">
              {{ requesting ? 'Sending code…' : 'Send verification code' }}
            </button>
          </section>

          <!-- Step 2: enter OTP -->
          <section v-else class="body">
            <div class="field">
              <label class="label" for="ec-otp">Verification code</label>
              <div class="input-wrap">
                <ShieldCheckIcon class="input-icon" aria-hidden="true" />
                <input id="ec-otp" v-model="otp" type="text" inputmode="numeric" maxlength="6"
                       autocomplete="one-time-code" placeholder="000000"
                       class="input input--with-icon input--otp" :disabled="verifying || otpExpired"
                       @keydown.enter.prevent="verifyCode" />
              </div>
              <p v-if="!otpExpired" class="hint">
                Expires in <strong>{{ remainingLabel }}</strong>. Check your spam folder if it doesn't arrive.
              </p>
              <p v-else class="hint hint--danger">The code has expired. Send a new one.</p>
            </div>

            <button type="button" class="primary" :disabled="verifying || otpExpired" @click="verifyCode">
              {{ verifying ? 'Verifying…' : 'Confirm change' }}
            </button>

            <div class="foot-actions">
              <button type="button" class="ghost" @click="backToEmail" :disabled="verifying">
                Change email
              </button>
              <button type="button" class="ghost ghost--with-icon"
                      :disabled="requesting || resendCooldown > 0"
                      @click="requestCode">
                <ArrowPathIcon class="ghost-icon" aria-hidden="true" />
                {{ resendCooldown > 0 ? `Resend in ${resendCooldown}s` : (requesting ? 'Resending…' : 'Resend code') }}
              </button>
            </div>

            <button type="button" class="cancel-pending" :disabled="cancelling" @click="cancelPending">
              {{ cancelling ? 'Cancelling…' : 'Cancel email change' }}
            </button>
          </section>
        </div>
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.scrim {
  position: fixed; inset: 0; z-index: 60;
  background: color-mix(in srgb, #000 55%, transparent);
  display: flex; align-items: flex-end; justify-content: center;
  padding: var(--space-3);
}
@media (min-width: 640px) { .scrim { align-items: center; } }

.sheet {
  width: 100%; max-width: 26rem;
  background: var(--surface); color: var(--text-primary);
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  padding: var(--space-5) var(--space-4) var(--space-5);
  box-shadow: 0 -20px 60px -20px rgba(0,0,0,0.35);
  display: flex; flex-direction: column; gap: var(--space-4);
  max-height: 90vh; overflow-y: auto;
}
@media (min-width: 640px) { .sheet { border-radius: var(--radius-lg); } }

.head { display: grid; grid-template-columns: 2.5rem 1fr 2rem; gap: var(--space-3); align-items: start; }
.head-icon {
  width: 2.5rem; height: 2.5rem; border-radius: var(--radius-md);
  background: var(--accent-tint); color: var(--accent-strong);
  display: inline-flex; align-items: center; justify-content: center;
}
.head-icon > svg { width: 1.35rem; height: 1.35rem; }
.head-copy { display: flex; flex-direction: column; gap: 0.15rem; min-width: 0; }
.head-title { font-size: 1.1rem; font-weight: 800; margin: 0; line-height: 1.2; }
.head-sub { font-size: 0.8rem; color: var(--text-secondary); margin: 0; line-height: 1.4; word-break: break-word; }
.close {
  width: 2rem; height: 2rem; border: none; background: transparent; color: var(--text-secondary);
  border-radius: var(--radius-sm); cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center;
}
.close:hover { background: color-mix(in srgb, var(--text-primary) 6%, transparent); color: var(--text-primary); }
.close > svg { width: 1.15rem; height: 1.15rem; }

.body { display: flex; flex-direction: column; gap: var(--space-3); }
.field { display: flex; flex-direction: column; gap: 0.4rem; }
.label { font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }
.hint { font-size: 0.75rem; color: var(--text-muted); margin: 0; line-height: 1.45; }
.hint--danger { color: var(--danger-fg); }

.input-wrap { position: relative; display: flex; align-items: center; }
.input {
  width: 100%; padding: 0.85rem 1rem; border-radius: var(--radius-md);
  border: 1px solid transparent; background: var(--surface-muted);
  color: var(--text-primary); font-size: 0.95rem; font-family: inherit; outline: none;
  transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
}
.input:focus { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent); }
.input:disabled { opacity: 0.6; cursor: not-allowed; }
.input--readonly { background: var(--surface-muted); color: var(--text-secondary); cursor: default; }
.input-icon { position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%); width: 1.25rem; height: 1.25rem; color: var(--text-primary); pointer-events: none; }
.input--with-icon { padding-left: 2.75rem; }
.input--otp { letter-spacing: 0.5em; font-weight: 700; font-size: 1.15rem; text-align: center; padding-right: 1rem; }

.primary {
  width: 100%; height: 3rem; padding: 0 var(--space-4);
  border-radius: var(--radius-pill); border: 1px solid transparent;
  background: var(--btn-primary-bg); color: var(--btn-primary-fg);
  font-weight: 600; font-size: 0.9rem; font-family: inherit; cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center;
  transition: transform 0.08s ease, opacity 0.15s ease;
  margin-top: var(--space-1);
}
.primary:hover:not(:disabled) { opacity: 0.92; }
.primary:active:not(:disabled) { transform: scale(0.98); }
.primary:disabled { opacity: 0.5; cursor: not-allowed; }

.foot-actions { display: flex; gap: var(--space-2); justify-content: space-between; align-items: center; }
.ghost {
  background: transparent; border: none; color: var(--accent-strong);
  font-weight: 600; font-size: 0.8rem; padding: 0.4rem 0.6rem; cursor: pointer;
  border-radius: var(--radius-sm);
}
.ghost:hover:not(:disabled) { background: color-mix(in srgb, var(--accent) 8%, transparent); }
.ghost:disabled { opacity: 0.5; cursor: not-allowed; }
.ghost--with-icon { display: inline-flex; align-items: center; gap: 0.3rem; }
.ghost-icon { width: 0.95rem; height: 0.95rem; }

.cancel-pending {
  background: transparent; border: none;
  color: var(--danger-fg); font-size: 0.75rem; font-weight: 600;
  cursor: pointer; padding: 0.5rem 0; margin-top: 0.2rem;
  text-decoration: underline; text-underline-offset: 3px;
}
.cancel-pending:hover:not(:disabled) { opacity: 0.85; }
.cancel-pending:disabled { opacity: 0.5; cursor: not-allowed; }

.ec-fade-enter-active, .ec-fade-leave-active { transition: opacity 0.15s ease; }
.ec-fade-enter-from, .ec-fade-leave-to { opacity: 0; }
</style>
