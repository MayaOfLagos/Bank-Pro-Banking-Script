<script setup>
import { computed, ref, watch } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import {
  HomeIcon,
  ArrowsRightLeftIcon,
  ArrowDownTrayIcon,
  QueueListIcon,
  CreditCardIcon,
  Bars3Icon,
} from '@heroicons/vue/24/solid'
import TransferDrawer from '../layout/TransferDrawer.vue'
import MoreDrawer from '../layout/MoreDrawer.vue'

/**
 * Bottom floating pill nav. Four destinations navigate directly; Transfer
 * and More open drawers instead, because each fans out to more screens
 * than the bar has room for.
 *
 * Kept dark in both themes per spec.
 */

const route = useRoute()

// 'transfer' | 'more' | null. A single slot enforces one sheet at a time
// rather than letting two dialogs stack on the same scrim layer.
const openSheet = ref(null)

// Routes each drawer trigger stands in for. Used only to light up the tab
// so the bar still reflects where the user is after the drawer closes.
const TRANSFER_ROUTES = ['/wire-transfer', '/domestic-transfer', '/withdrawals']
const MORE_ROUTES = ['/profile', '/loans', '/tickets']

const items = [
  { key: 'home', label: 'Home', to: '/dashboard', icon: HomeIcon },
  { key: 'transfer', label: 'Transfer', icon: ArrowsRightLeftIcon },
  { key: 'deposit', label: 'Deposit', to: '/deposits', icon: ArrowDownTrayIcon },
  { key: 'transaction', label: 'Transaction', to: '/transactions', icon: QueueListIcon },
  { key: 'card', label: 'Card', to: '/cards', icon: CreditCardIcon },
  { key: 'more', label: 'More', icon: Bars3Icon },
]

const links = items.filter((item) => item.to)

function matches(path, base) {
  return path === base || path.startsWith(`${base}/`)
}

// Longest match wins so /transactions/12 highlights Transaction rather than
// falling through to the first item.
const activeKey = computed(() => {
  const path = route.path
  let best = null
  let bestLen = -1

  for (const item of links) {
    if (matches(path, item.to) && item.to.length > bestLen) {
      best = item.key
      bestLen = item.to.length
    }
  }
  for (const base of TRANSFER_ROUTES) {
    if (matches(path, base) && base.length > bestLen) {
      best = 'transfer'
      bestLen = base.length
    }
  }
  for (const base of MORE_ROUTES) {
    if (matches(path, base) && base.length > bestLen) {
      best = 'more'
      bestLen = base.length
    }
  }
  return best ?? 'home'
})

// A back-button navigation does not unmount this bar, so a drawer left open
// would hang over the new route.
watch(() => route.fullPath, () => {
  openSheet.value = null
})
</script>

<template>
  <nav class="bar no-print" aria-label="Primary">
    <component
      :is="item.to ? RouterLink : 'button'"
      v-for="item in items"
      :key="item.key"
      :to="item.to"
      :type="item.to ? undefined : 'button'"
      class="tab"
      :class="{ 'tab--active': item.key === activeKey }"
      :aria-current="item.to && item.key === activeKey ? 'page' : undefined"
      :aria-label="item.label"
      :aria-haspopup="item.to ? undefined : 'dialog'"
      :aria-expanded="item.to ? undefined : openSheet === item.key"
      @click="item.to ? undefined : (openSheet = item.key)"
    >
      <component :is="item.icon" class="icon" aria-hidden="true" />
      <span class="sr-only">{{ item.label }}</span>
    </component>
  </nav>

  <TransferDrawer :open="openSheet === 'transfer'" @close="openSheet = null" />
  <MoreDrawer :open="openSheet === 'more'" @close="openSheet = null" />
</template>

<style scoped>
.bar {
  position: fixed;
  left: 50%;
  bottom: calc(var(--space-4) + env(safe-area-inset-bottom));
  transform: translateX(-50%);
  display: inline-flex;
  gap: var(--space-2);
  padding: 0.4rem;
  border-radius: var(--radius-pill);
  background: var(--floating-bar-bg);
  backdrop-filter: blur(14px);
  -webkit-backdrop-filter: blur(14px);
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
  z-index: 40;
}
.tab {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.75rem;
  height: 2.75rem;
  flex-shrink: 0;
  border: 0;
  padding: 0;
  background: transparent;
  border-radius: var(--radius-pill);
  color: var(--floating-bar-idle-fg);
  text-decoration: none;
  cursor: pointer;
  transition: transform 0.1s ease, background-color 0.2s ease;
}
.tab:active {
  transform: scale(0.96);
}
.tab--active {
  background: var(--floating-bar-active-bg);
  color: var(--floating-bar-active-fg);
}
.icon {
  width: 1.25rem;
  height: 1.25rem;
}
/* Six tabs at full size overflow a 360px viewport once the pill's own
   padding is counted, so the tabs tighten rather than the bar clipping. */
@media (max-width: 22.5rem) {
  .bar { gap: 0.3rem; padding: 0.3rem; }
  .tab { width: 2.5rem; height: 2.5rem; }
}
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  border: 0;
}
</style>
