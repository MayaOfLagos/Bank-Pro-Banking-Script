<script setup>
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import {
  HomeIcon,
  ArrowsRightLeftIcon,
  ArrowDownTrayIcon,
  CreditCardIcon,
  Bars3Icon,
} from '@heroicons/vue/24/solid'

/**
 * Bottom floating pill nav. Five destinations by default:
 *   home (Dashboard)
 *   transfer (/wire-transfer)
 *   deposit (/transactions — funding history hub until a dedicated deposit flow lands)
 *   card (/cards)
 *   more (/profile — hub for profile, support, loans, logout)
 * Kept dark in both themes per spec.
 */

const props = defineProps({
  items: {
    type: Array,
    default: () => [
      { key: 'home', label: 'Home', to: '/dashboard', icon: HomeIcon },
      { key: 'transfer', label: 'Transfer', to: '/wire-transfer', icon: ArrowsRightLeftIcon },
      { key: 'deposit', label: 'Deposit', to: '/deposits', icon: ArrowDownTrayIcon },
      { key: 'card', label: 'Card', to: '/cards', icon: CreditCardIcon },
      { key: 'more', label: 'More', to: '/profile', icon: Bars3Icon },
    ],
  },
})

const route = useRoute()

const activeKey = computed(() => {
  // Longest-match wins so /wire-transfer routes highlight "transfer"
  // rather than accidentally matching "/". Explicit-order tie-break falls
  // back to first item.
  let best = null
  let bestLen = -1
  for (const item of props.items) {
    if (route.path === item.to || route.path.startsWith(`${item.to}/`)) {
      if (item.to.length > bestLen) {
        best = item
        bestLen = item.to.length
      }
    }
  }
  return best?.key ?? props.items[0]?.key
})
</script>

<template>
  <nav class="bar" aria-label="Primary">
    <RouterLink
      v-for="item in items"
      :key="item.key"
      :to="item.to"
      class="tab"
      :class="{ 'tab--active': item.key === activeKey }"
      :aria-current="item.key === activeKey ? 'page' : undefined"
      :aria-label="item.label"
    >
      <component :is="item.icon" class="icon" aria-hidden="true" />
      <span class="sr-only">{{ item.label }}</span>
    </RouterLink>
  </nav>
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
  border-radius: var(--radius-pill);
  color: var(--floating-bar-idle-fg);
  text-decoration: none;
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
