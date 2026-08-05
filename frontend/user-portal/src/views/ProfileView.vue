<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  ChevronLeftIcon, CameraIcon, ChevronRightIcon,
  ArrowRightOnRectangleIcon, PencilSquareIcon, ShieldCheckIcon,
  UserGroupIcon, WalletIcon, LifebuoyIcon,
} from '@heroicons/vue/24/solid'
import client, { UPLOAD_TIMEOUT } from '../api/client'
import { authApi } from '../api/auth'
import { merchantInitials } from '../utils/format'
import { currencySymbol } from '../utils/currency'
import { countryName } from '../utils/countries'
import ErrorState from '../components/ui/ErrorState.vue'

const router = useRouter()
const toast = useToast()

const loading = ref(true)
const error = ref('')
const profile = ref({})

const avatarFile = ref(null)
const uploadingAvatar = ref(false)
const loggingOut = ref(false)

async function loadProfile() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/api/user/profile.php')
    if (!data?.ok) throw new Error(data?.message || 'Unable to load profile')
    profile.value = data.data || {}
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'Unable to load profile'
  } finally {
    loading.value = false
  }
}

const initials = computed(() => {
  const name = `${profile.value.firstname || ''} ${profile.value.lastname || ''}`.trim()
  return name ? merchantInitials(name) : '?'
})

const statusLabel = computed(() => profile.value.acct_status || '')
const statusKind = computed(() => {
  const s = String(statusLabel.value).toLowerCase()
  if (s === 'active') return 'active'
  if (s === 'hold' || s === 'pending' || s === 'processing') return 'paused'
  if (s === 'suspended' || s === 'blocked' || s === 'inactive') return 'hold'
  return 'unknown'
})

const currencyText = computed(() => {
  const code = profile.value.acct_currency || ''
  const sym = currencySymbol(code)
  return code ? `${code} · ${sym}` : (sym || '—')
})

const countryDisplay = computed(() => {
  const raw = profile.value.country
  if (!raw) return '—'
  return countryName(raw) !== raw ? countryName(raw) : raw
})

const subtitle = computed(() => {
  const parts = [profile.value.acct_type, profile.value.acct_currency].filter(Boolean)
  return parts.length ? parts.join(' · ') : 'Banking client'
})

// Prettify server-stored keys ('prefer_not_to_say' → 'Prefer not to say').
function humanize(value) {
  if (!value) return ''
  return String(value)
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

const infoRows = computed(() => {
  const p = profile.value
  const optional = (label, value, extra = {}) => (value ? [{ label, value, ...extra }] : [])
  return [
    { label: 'Account number', value: p.acct_no || '—', mono: true },
    { label: 'Account type', value: p.acct_type || '—' },
    { label: 'Currency', value: currencyText.value },
    { label: 'Email', value: p.email || '—' },
    { label: 'Phone', value: p.phone || '—' },
    ...optional('Date of birth', p.acct_dob),
    ...optional('Gender', humanize(p.acct_gender)),
    ...optional('Marital status', humanize(p.marital_status)),
    ...optional('Occupation', p.acct_occupation),
    { label: 'Country', value: countryDisplay.value },
    ...optional('State / Region', p.state),
    ...optional('Address', p.acct_address),
  ]
})

// ─── Avatar upload (stays on this page — it's a display concern) ─────────
function onAvatarPicked(event) {
  const file = event.target?.files?.[0]
  if (!file) return
  avatarFile.value = file
  uploadAvatar()
}

async function uploadAvatar() {
  if (!avatarFile.value) return
  uploadingAvatar.value = true
  try {
    const form = new FormData()
    form.append('image', avatarFile.value)
    const { data } = await client.post('/api/user/profile-image.php', form, {
      headers: { 'Content-Type': 'multipart/form-data' },
      timeout: UPLOAD_TIMEOUT,
    })
    if (!data?.ok) throw new Error(data?.message || 'Upload failed')
    toast.success('Profile photo updated.')
    await loadProfile()
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Could not upload photo.')
  } finally {
    uploadingAvatar.value = false
    avatarFile.value = null
  }
}

async function handleLogout() {
  loggingOut.value = true
  try {
    await authApi.logout()
  } finally {
    router.push('/login')
  }
}

onMounted(loadProfile)
</script>

<template>
  <div class="page">
    <div class="content">
      <header class="header">
        <button type="button" class="back" aria-label="Go back" @click="router.back()">
          <ChevronLeftIcon class="back-icon" aria-hidden="true" />
        </button>
        <div class="titles">
          <h1 class="title">Profile</h1>
          <p class="subtitle">Your account at a glance</p>
        </div>
      </header>

      <template v-if="loading">
        <div class="skeleton skeleton-hero" />
        <div class="skeleton skeleton-card" />
        <div class="skeleton skeleton-card-sm" />
      </template>

      <ErrorState v-else-if="error" :message="error" />

      <template v-else>
        <section class="hero">
          <label class="avatar-wrap" :aria-busy="uploadingAvatar">
            <div class="avatar">
              <img v-if="profile.image" :src="profile.image" :alt="`${profile.firstname || 'You'}'s avatar`" />
              <span v-else>{{ initials }}</span>
            </div>
            <span class="avatar-upload" :class="{ 'avatar-upload--busy': uploadingAvatar }">
              <CameraIcon class="avatar-upload-icon" aria-hidden="true" />
            </span>
            <input
              type="file"
              accept="image/png,image/jpeg"
              class="avatar-input"
              :disabled="uploadingAvatar"
              @change="onAvatarPicked"
            />
          </label>
          <h2 class="name">{{ profile.full_name || 'Your account' }}</h2>
          <p class="hero-sub">{{ subtitle }}</p>
          <span v-if="statusLabel" class="status-badge" :class="`status-badge--${statusKind}`">
            <span class="status-dot"></span>{{ statusLabel }}
          </span>
        </section>

        <section class="card" aria-label="Account details">
          <div class="rows">
            <div v-for="(row, i) in infoRows" :key="row.label + i" class="row">
              <span class="row-label">{{ row.label }}</span>
              <span class="row-value" :class="{ 'row-value--mono': row.mono }">{{ row.value }}</span>
            </div>
          </div>
        </section>

        <!-- This page is the "More" destination in the bottom nav, so it
             carries the entry points that bar has no room for. Loans and
             Support are otherwise unreachable from the mobile UI. -->
        <p class="group-label">Account</p>
        <nav class="card manage-card" aria-label="Account management">
          <RouterLink to="/profile/edit" class="manage-row">
            <span class="manage-icon manage-icon--accent">
              <PencilSquareIcon aria-hidden="true" />
            </span>
            <span class="manage-body">
              <span class="manage-title">Personal details</span>
              <span class="manage-sub">Update your name and phone</span>
            </span>
            <ChevronRightIcon class="manage-chev" aria-hidden="true" />
          </RouterLink>

          <RouterLink to="/profile/security" class="manage-row">
            <span class="manage-icon manage-icon--muted">
              <ShieldCheckIcon aria-hidden="true" />
            </span>
            <span class="manage-body">
              <span class="manage-title">Security</span>
              <span class="manage-sub">Change password and transaction PIN</span>
            </span>
            <ChevronRightIcon class="manage-chev" aria-hidden="true" />
          </RouterLink>

          <RouterLink to="/profile/manager" class="manage-row">
            <span class="manage-icon manage-icon--muted">
              <UserGroupIcon aria-hidden="true" />
            </span>
            <span class="manage-body">
              <span class="manage-title">Account manager</span>
              <span class="manage-sub">Your dedicated point of contact</span>
            </span>
            <ChevronRightIcon class="manage-chev" aria-hidden="true" />
          </RouterLink>
        </nav>

        <p class="group-label">Services</p>
        <nav class="card manage-card" aria-label="Services">
          <RouterLink to="/loans" class="manage-row">
            <span class="manage-icon manage-icon--accent">
              <WalletIcon aria-hidden="true" />
            </span>
            <span class="manage-body">
              <span class="manage-title">Loans</span>
              <span class="manage-sub">Apply for and track your loans</span>
            </span>
            <ChevronRightIcon class="manage-chev" aria-hidden="true" />
          </RouterLink>

          <RouterLink to="/tickets" class="manage-row">
            <span class="manage-icon manage-icon--muted">
              <LifebuoyIcon aria-hidden="true" />
            </span>
            <span class="manage-body">
              <span class="manage-title">Support</span>
              <span class="manage-sub">Message our team about your account</span>
            </span>
            <ChevronRightIcon class="manage-chev" aria-hidden="true" />
          </RouterLink>
        </nav>

        <button
          type="button"
          class="signout"
          :disabled="loggingOut"
          @click="handleLogout"
        >
          <ArrowRightOnRectangleIcon class="signout-icon" aria-hidden="true" />
          {{ loggingOut ? 'Signing out…' : 'Sign out' }}
        </button>
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

.hero { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-6) var(--space-4); box-shadow: var(--shadow-card); display: flex; flex-direction: column; align-items: center; gap: var(--space-3); text-align: center; }
.avatar-wrap { position: relative; width: 5.5rem; height: 5.5rem; cursor: pointer; }
.avatar { width: 100%; height: 100%; border-radius: var(--radius-pill); background: var(--card-gradient-debit); color: var(--text-on-accent); display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.4rem; overflow: hidden; box-shadow: 0 10px 24px -8px color-mix(in srgb, var(--accent-strong) 60%, transparent); }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.avatar-upload { position: absolute; right: -0.2rem; bottom: -0.2rem; width: 2rem; height: 2rem; border-radius: var(--radius-pill); background: var(--surface); border: 2px solid var(--surface); color: var(--text-primary); display: inline-flex; align-items: center; justify-content: center; box-shadow: var(--shadow-card); transition: transform 0.15s ease; }
.avatar-wrap:hover .avatar-upload { transform: scale(1.05); }
.avatar-upload--busy { opacity: 0.5; }
.avatar-upload-icon { width: 1rem; height: 1rem; }
.avatar-input { position: absolute; width: 1px; height: 1px; opacity: 0; pointer-events: none; }
.name { font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0.2rem 0 0; line-height: 1.2; }
.hero-sub { color: var(--text-secondary); font-size: 0.85rem; margin: 0; }

.status-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.3rem 0.7rem; border-radius: var(--radius-pill); font-size: 0.75rem; font-weight: 600; background: var(--surface-muted); color: var(--text-primary); text-transform: capitalize; }
.status-dot { width: 0.45rem; height: 0.45rem; border-radius: 50%; background: var(--text-secondary); }
.status-badge--active { background: rgba(16, 185, 129, 0.12); color: var(--success-fg); }
.status-badge--active .status-dot { background: var(--success-fg); }
.status-badge--paused { background: rgba(180, 83, 9, 0.12); color: var(--warning-fg); }
.status-badge--paused .status-dot { background: var(--warning-fg); }
.status-badge--hold { background: var(--danger-bg); color: var(--danger-fg); }
.status-badge--hold .status-dot { background: var(--danger-fg); }

.card { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-5) var(--space-4); box-shadow: var(--shadow-card); display: flex; flex-direction: column; gap: var(--space-3); }
.rows { display: flex; flex-direction: column; }
.row { display: flex; justify-content: space-between; align-items: baseline; gap: var(--space-4); padding: var(--space-3) 0; }
.row + .row { border-top: 1px solid var(--divider); }
.row-label { color: var(--text-secondary); font-size: 0.85rem; flex-shrink: 0; }
.row-value { color: var(--text-primary); font-size: 0.9rem; font-weight: 600; text-align: right; min-width: 0; word-break: break-word; }
.row-value--mono { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; letter-spacing: 0.02em; }

/* Manage links — tap-friendly rows with icon, title+sub, and chevron. */
.group-label { font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.06em; margin: var(--space-2) 0 calc(var(--space-3) * -1); padding-inline: var(--space-2); }
.manage-card { padding: var(--space-2) var(--space-4); gap: 0; }
.manage-row {
  display: flex; align-items: center; gap: var(--space-3);
  padding: var(--space-3) 0;
  text-decoration: none; color: inherit;
  transition: background-color 0.15s ease;
  border-radius: var(--radius-md);
  margin: 0 calc(var(--space-2) * -1); padding-inline: var(--space-2);
}
.manage-row + .manage-row { border-top: 1px solid var(--divider); }
.manage-row:hover { background: color-mix(in srgb, var(--text-primary) 4%, transparent); }
.manage-row:active { transform: scale(0.995); }
.manage-icon {
  width: 2.5rem; height: 2.5rem; border-radius: var(--radius-md);
  display: inline-flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.manage-icon--accent { background: var(--accent-tint); color: var(--accent-strong); }
.manage-icon--muted { background: var(--surface-muted); color: var(--text-primary); }
.manage-icon > svg { width: 1.35rem; height: 1.35rem; }
.manage-body { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.manage-title { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); }
.manage-sub { font-size: 0.75rem; color: var(--text-secondary); }
.manage-chev { width: 1.1rem; height: 1.1rem; color: var(--text-secondary); flex-shrink: 0; }

.signout {
  width: 100%; height: 3rem; padding: 0 var(--space-4);
  border-radius: var(--radius-pill);
  border: 1px solid var(--danger-border);
  background: var(--danger-bg); color: var(--danger-fg);
  font-weight: 600; font-size: 0.9rem; font-family: inherit;
  cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem;
  transition: transform 0.08s ease, background-color 0.15s ease;
}
.signout:hover:not(:disabled) { background: color-mix(in srgb, var(--danger-fg) 18%, transparent); }
.signout:active:not(:disabled) { transform: scale(0.98); }
.signout:disabled { opacity: 0.5; cursor: not-allowed; }
.signout-icon { width: 1.15rem; height: 1.15rem; }

.skeleton { border-radius: var(--radius-lg); background: var(--surface-muted); animation: pulse 1.6s ease-in-out infinite; }
.skeleton-hero { height: 12rem; }
.skeleton-card { height: 14rem; }
.skeleton-card-sm { height: 8rem; }
@keyframes pulse { 0%, 100% { opacity: 0.5; } 50% { opacity: 0.85; } }
</style>
