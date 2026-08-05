<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  HomeIcon, ArrowDownTrayIcon, PaperAirplaneIcon, GlobeAltIcon,
  CreditCardIcon, WalletIcon, QueueListIcon, BanknotesIcon,
  UserGroupIcon, PencilSquareIcon, ShieldCheckIcon, LifebuoyIcon,
  ArrowRightOnRectangleIcon, MoonIcon, SunIcon,
} from '@heroicons/vue/24/solid'
import SwipeDrawer from '../ui/SwipeDrawer.vue'
import { useProfileStore } from '../../stores/profile'
import { useTheme } from '../../composables/useTheme'
import { authApi } from '../../api/auth'

const props = defineProps({
  open: { type: Boolean, default: false },
})

const emit = defineEmits(['close'])

const router = useRouter()
const profileStore = useProfileStore()
const { isDark, toggleTheme } = useTheme()

const loggingOut = ref(false)

const displayName = computed(() => profileStore.fullName || 'Your account')
const accountType = computed(() => profileStore.profile?.acct_type || 'Personal account')
const avatar = computed(() => profileStore.profile?.image || '')

/**
 * Ported from the legacy user/layouts/header.php sidebar. Two entries
 * changed shape rather than being dropped:
 *   - "All Transaction Logs" was a submenu of five per-source pages
 *     (credit/debit, wire, domestic, loan, withdrawal). The Vue portal
 *     merges those into one ledger, so it collapses to a single row.
 *   - "Settings" split into the distinct pages the portal actually has
 *     instead of a two-item accordion.
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
      { label: 'Personal details', to: '/profile/edit', icon: PencilSquareIcon },
      { label: 'Security', to: '/profile/security', icon: ShieldCheckIcon },
      { label: 'Account Manager', to: '/profile/manager', icon: UserGroupIcon },
      { label: 'Support', to: '/tickets', icon: LifebuoyIcon },
    ],
  },
]

// The drawer outlives the route it sits over, so it must close itself on
// navigation or it would still be open behind the next screen.
function go(to) {
  emit('close')
  router.push(to)
}

async function handleLogout() {
  loggingOut.value = true
  try {
    await authApi.logout()
  } finally {
    loggingOut.value = false
    emit('close')
    router.push('/login')
  }
}

watch(
  () => props.open,
  (open) => {
    if (open) profileStore.loadProfile().catch(() => {})
  },
)
</script>

<template>
  <SwipeDrawer :open="open" side="right" @close="emit('close')">
    <div class="menu">
    <header class="who">
      <div class="avatar">
        <img v-if="avatar" :src="avatar" :alt="`${displayName} avatar`" />
        <span v-else>{{ profileStore.initials }}</span>
      </div>
      <div class="who-copy">
        <p class="who-name">{{ displayName }}</p>
        <p class="who-type">{{ accountType }}</p>
      </div>
    </header>

    <button type="button" class="profile-link" @click="go('/profile')">
      View full profile
    </button>

    <nav v-for="section in sections" :key="section.label" class="section" :aria-label="section.label">
      <p class="section-label">{{ section.label }}</p>
      <button
        v-for="item in section.items"
        :key="item.to"
        type="button"
        class="row"
        @click="go(item.to)"
      >
        <span class="row-icon"><component :is="item.icon" aria-hidden="true" /></span>
        <span class="row-label">{{ item.label }}</span>
      </button>
    </nav>

    <div class="footer">
      <button type="button" class="row row--plain" @click="toggleTheme">
        <span class="row-icon">
          <MoonIcon v-if="!isDark" aria-hidden="true" />
          <SunIcon v-else aria-hidden="true" />
        </span>
        <span class="row-label">{{ isDark ? 'Light mode' : 'Dark mode' }}</span>
      </button>

      <button type="button" class="signout" :disabled="loggingOut" @click="handleLogout">
        <ArrowRightOnRectangleIcon class="signout-icon" aria-hidden="true" />
        {{ loggingOut ? 'Signing out…' : 'Log Out' }}
      </button>
    </div>
    </div>
  </SwipeDrawer>
</template>

<style scoped>
/* Full-height column so the sign-out block can be pinned to the bottom
   even when the menu is shorter than the panel. */
.menu {
  display: flex;
  flex-direction: column;
  min-height: 100%;
}

.who {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) 0 var(--space-3);
}
.avatar {
  width: 3rem;
  height: 3rem;
  flex-shrink: 0;
  border-radius: var(--radius-pill);
  background: var(--card-gradient-debit);
  color: var(--text-on-accent);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-weight: 800;
  font-size: 0.95rem;
  overflow: hidden;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.who-copy { min-width: 0; }
.who-name {
  margin: 0;
  font-size: 0.95rem;
  font-weight: 800;
  line-height: 1.2;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.who-type {
  margin: 0.1rem 0 0;
  font-size: 0.75rem;
  color: var(--text-secondary);
  text-transform: capitalize;
}

.profile-link {
  width: 100%;
  padding: 0.6rem;
  margin-bottom: var(--space-4);
  border-radius: var(--radius-pill);
  border: 1px solid var(--btn-outline-border);
  background: var(--btn-outline-bg);
  color: var(--btn-outline-fg);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
}
.profile-link:active { transform: scale(0.98); }

.section { margin-bottom: var(--space-4); }
.section-label {
  margin: 0 0 var(--space-2);
  font-size: 0.68rem;
  font-weight: 700;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--text-muted);
}

.row {
  width: 100%;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: 0.6rem 0.5rem;
  border: 0;
  border-radius: var(--radius-md);
  background: transparent;
  color: var(--text-primary);
  text-align: left;
  cursor: pointer;
  transition: background-color 0.15s ease;
}
.row:hover { background: var(--surface-muted); }
.row:active { transform: scale(0.99); }
.row:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: -2px;
}
.row-icon {
  width: 1.9rem;
  height: 1.9rem;
  flex-shrink: 0;
  border-radius: var(--radius-sm);
  background: var(--surface-muted);
  color: var(--text-secondary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.row:hover .row-icon { background: var(--accent-tint); color: var(--accent-strong); }
.row-icon > svg { width: 1.05rem; height: 1.05rem; }
.row-label { font-size: 0.85rem; font-weight: 600; }

.footer {
  margin-top: auto;
  padding-top: var(--space-3);
  border-top: 1px solid var(--divider);
}
.row--plain:hover .row-icon { background: var(--surface-muted); color: var(--text-secondary); }

.signout {
  width: 100%;
  margin-top: var(--space-2);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.45rem;
  height: 2.75rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--danger-border);
  background: var(--danger-bg);
  color: var(--danger-fg);
  font-size: 0.85rem;
  font-weight: 700;
  cursor: pointer;
}
.signout:disabled { opacity: 0.6; cursor: not-allowed; }
.signout:active:not(:disabled) { transform: scale(0.98); }
.signout-icon { width: 1.05rem; height: 1.05rem; }
</style>
