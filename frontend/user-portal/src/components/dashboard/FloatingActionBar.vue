<script setup>
import { computed } from 'vue'
import { useRoute, RouterLink } from 'vue-router'
import { BoltIcon, CreditCardIcon, ChatBubbleOvalLeftIcon } from '@heroicons/vue/24/solid'

/**
 * Bottom floating pill nav. Three destinations by default:
 *   home (Dashboard, lightning icon)
 *   cards (/cards, credit-card icon)
 *   support (/tickets, chat bubble)
 * Kept dark in both themes per spec.
 */

const props = defineProps({
  items: {
    type: Array,
    default: () => [
      { key: 'home', label: 'Home', to: '/dashboard', icon: BoltIcon },
      { key: 'cards', label: 'Cards', to: '/cards', icon: CreditCardIcon },
      { key: 'support', label: 'Support', to: '/tickets', icon: ChatBubbleOvalLeftIcon },
    ],
  },
})

const route = useRoute()

const activeKey = computed(() => {
  const match = props.items.find((item) =>
    route.path === item.to || route.path.startsWith(`${item.to}/`)
  )
  return match?.key ?? props.items[0]?.key
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
  border-radius: var(--radius-md);
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
