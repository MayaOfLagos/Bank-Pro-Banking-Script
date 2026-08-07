<script setup>
import { computed, onMounted, ref } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  ChevronLeftIcon, ChevronRightIcon,
  HomeIcon, ArrowDownTrayIcon, PaperAirplaneIcon, GlobeAltIcon,
  CreditCardIcon, WalletIcon, QueueListIcon, BanknotesIcon,
  UserGroupIcon, PencilSquareIcon, ShieldCheckIcon, LifebuoyIcon,
  IdentificationIcon,
  ArrowRightOnRectangleIcon, MoonIcon, SunIcon,
} from '@heroicons/vue/24/solid'
import { useProfileStore } from '../stores/profile'
import { useTheme } from '../composables/useTheme'
import { clearSession } from '../composables/useSession'
import { authApi } from '../api/auth'
import SkeletonText from '../components/skeletons/SkeletonText.vue'

const router = useRouter()
const profileStore = useProfileStore()
const { isDark, toggleTheme } = useTheme()

const loading = ref(true)
const loggingOut = ref(false)

const displayName = computed(() => profileStore.fullName || 'Your account')
const accountType = computed(() => profileStore.profile?.acct_type || 'Personal account')
const avatar = computed(() => profileStore.profile?.image || '')
const initials = computed(() => profileStore.initials)

/**
 * This page is the destination for the bottom bar's "More" tab, so it
 * carries every entry point that bar has no room for.
 */
const sections = [
  {
    label: 'Banking',
    items: [
      { label: 'Dashboard', to: '/dashboard', icon: HomeIcon },
      { label: 'Online Deposit', to: '/deposits', icon: ArrowDownTrayIcon },
      { label: 'Transaction Logs', to: '/transactions', icon: QueueListIcon },
      { label: 'Virtual Card', to: '/cards', icon: CreditCardIcon },
      { label: 'Loans & Mortgages', to: '/loans', icon: WalletIcon },
    ],
  },
  {
    label: 'Move money',
    items: [
      { label: 'Domestic Transfer', to: '/domestic-transfer', icon: PaperAirplaneIcon },
      { label: 'Wire Transfer', to: '/wire-transfer', icon: GlobeAltIcon },
      { label: 'Withdrawal', to: '/withdrawals', icon: BanknotesIcon },
    ],
  },
  {
    label: 'Account',
    items: [
      { label: 'Account details', to: '/profile/account', icon: IdentificationIcon },
      { label: 'Personal details', to: '/profile/edit', icon: PencilSquareIcon },
      { label: 'Security', to: '/profile/security', icon: ShieldCheckIcon },
      { label: 'Account Manager', to: '/profile/manager', icon: UserGroupIcon },
      { label: 'Support', to: '/tickets', icon: LifebuoyIcon },
    ],
  },
]

async function handleLogout() {
  loggingOut.value = true
  try {
    await authApi.logout()
  } finally {
    // Cleared even if the request failed: the customer asked to leave, so the
    // local copy of their account must go regardless of what the server said.
    clearSession()
    loggingOut.value = false
    router.push('/login')
  }
}

onMounted(async () => {
  try {
    await profileStore.loadProfile()
  } catch {
    // The header falls back to placeholder text; the nav below still works
    // without a profile, so a failed fetch shouldn't block the page.
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="page">
    <div class="content">
      <header class="header">
        <button type="button" class="back" aria-label="Go back" @click="router.back()">
          <ChevronLeftIcon class="back-icon" aria-hidden="true" />
        </button>
        <div class="titles">
          <h1 class="title">More</h1>
          <p class="subtitle">Everything else about your account</p>
        </div>
      </header>

      <section class="who">
        <div class="who-avatar">
          <img v-if="avatar" :src="avatar" :alt="`${displayName} avatar`" />
          <span v-else-if="loading" class="skeleton who-avatar-sk" />
          <span v-else>{{ initials }}</span>
        </div>
        <div class="who-copy">
          <template v-if="loading">
            <SkeletonText size="1rem" :line-height="1.2" width="9rem" />
            <SkeletonText size="0.75rem" width="6rem" />
          </template>
          <template v-else>
            <p class="who-name">{{ displayName }}</p>
            <p class="who-type">{{ accountType }}</p>
          </template>
        </div>
      </section>

      <nav
        v-for="section in sections"
        :key="section.label"
        class="section"
        :aria-label="section.label"
      >
        <p class="section-label">{{ section.label }}</p>
        <div class="card manage-card">
          <RouterLink
            v-for="item in section.items"
            :key="item.to"
            :to="item.to"
            class="manage-row"
          >
            <span class="manage-icon">
              <component :is="item.icon" aria-hidden="true" />
            </span>
            <span class="manage-body">
              <span class="manage-title">{{ item.label }}</span>
            </span>
            <ChevronRightIcon class="manage-chev" aria-hidden="true" />
          </RouterLink>
        </div>
      </nav>

      <div class="card manage-card">
        <button type="button" class="manage-row manage-row--button" @click="toggleTheme">
          <span class="manage-icon">
            <MoonIcon v-if="!isDark" aria-hidden="true" />
            <SunIcon v-else aria-hidden="true" />
          </span>
          <span class="manage-body">
            <span class="manage-title">{{ isDark ? 'Light mode' : 'Dark mode' }}</span>
          </span>
        </button>
      </div>

      <button
        type="button"
        class="signout"
        :disabled="loggingOut"
        @click="handleLogout"
      >
        <ArrowRightOnRectangleIcon class="signout-icon" aria-hidden="true" />
        {{ loggingOut ? 'Signing out…' : 'Sign out' }}
      </button>
    </div>
  </div>
</template>

<style scoped>
.page { min-height: 100vh; background: var(--bg-gradient); padding: var(--space-5) var(--space-4) 7rem; }
.content { max-width: 30rem; margin: 0 auto; display: flex; flex-direction: column; gap: var(--space-4); }

.header { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) 0 var(--space-1); }
.back { width: 2.5rem; height: 2.5rem; border-radius: 1.7rem; border: 1px solid var(--border); background: transparent; color: var(--text-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.back:hover { background: color-mix(in srgb, var(--text-primary) 6%, transparent); }
.back:active { transform: scale(0.95); }
.back-icon { width: 1.15rem; height: 1.15rem; }
.title { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; line-height: 1.1; }
.subtitle { font-size: 0.8rem; color: var(--text-secondary); margin: 0.15rem 0 0; }

.who {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-4);
  background: var(--surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
}
.who-avatar {
  width: 3.25rem;
  height: 3.25rem;
  flex-shrink: 0;
  border-radius: var(--radius-pill);
  background: var(--card-gradient-debit);
  color: var(--text-on-accent);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 1rem;
  overflow: hidden;
}
.who-avatar img { width: 100%; height: 100%; object-fit: cover; }
.who-avatar-sk { width: 100%; height: 100%; border-radius: var(--radius-pill); }
.who-copy { min-width: 0; display: flex; flex-direction: column; gap: 0.15rem; }
.who-name {
  margin: 0;
  font-size: 1rem;
  font-weight: 800;
  line-height: 1.2;
  color: var(--text-primary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.who-type {
  margin: 0;
  font-size: 0.8rem;
  color: var(--text-secondary);
  text-transform: capitalize;
}

.section { display: flex; flex-direction: column; gap: var(--space-2); }
.section-label {
  margin: 0;
  padding-inline: var(--space-2);
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.card { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-card); }
.manage-card { padding: var(--space-2) var(--space-4); display: flex; flex-direction: column; }
.manage-row {
  display: flex; align-items: center; gap: var(--space-3);
  padding: var(--space-3) 0;
  text-decoration: none; color: inherit;
  transition: background-color 0.15s ease;
  border-radius: var(--radius-md);
  margin: 0 calc(var(--space-2) * -1); padding-inline: var(--space-2);
  border: 0; background: transparent; font: inherit; text-align: left; cursor: pointer;
}
.manage-row + .manage-row { border-top: 1px solid var(--divider); }
.manage-row:hover { background: color-mix(in srgb, var(--text-primary) 4%, transparent); }
.manage-row:active { transform: scale(0.995); }
.manage-icon {
  width: 2.5rem; height: 2.5rem; border-radius: var(--radius-md);
  background: var(--surface-muted); color: var(--text-primary);
  display: inline-flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.manage-icon > svg { width: 1.25rem; height: 1.25rem; }
.manage-body { display: flex; flex-direction: column; flex: 1; min-width: 0; }
.manage-title { font-size: 0.95rem; font-weight: 600; color: var(--text-primary); }
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
</style>
