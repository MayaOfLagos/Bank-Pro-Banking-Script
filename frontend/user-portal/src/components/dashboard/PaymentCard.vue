<script setup>
import { computed } from 'vue'
import { EllipsisHorizontalIcon } from '@heroicons/vue/24/outline'
import { formatMoneyInline, last4 } from '../../utils/format'

/**
 * A single card in the horizontal carousel. Presentation-only — the parent
 * decides which cards to render. Type drives the gradient token used.
 */
const props = defineProps({
  card: {
    type: Object,
    required: true,
  },
  currency: { type: String, default: '$' },
})

const gradientClass = computed(() => (String(props.card?.type || 'debit').toLowerCase() === 'credit' ? 'grad-credit' : 'grad-debit'))
const label = computed(() => (String(props.card?.type || 'Debit')).replace(/^./, (c) => c.toUpperCase()))
const balanceString = computed(() => `${props.currency}${formatMoneyInline(props.card?.balance ?? 0)}`)
const cardLast4 = computed(() => last4(props.card?.card_number ?? props.card?.masked_number ?? props.card?.last4))
const network = computed(() => (props.card?.network || '').toUpperCase())
</script>

<template>
  <article class="card" :class="gradientClass">
    <div class="top">
      <span v-if="network" class="network">{{ network }}</span>
      <button v-else type="button" class="menu" aria-label="Card options">
        <EllipsisHorizontalIcon class="menu-icon" aria-hidden="true" />
      </button>
    </div>

    <p class="balance">{{ balanceString }}</p>

    <div class="bottom">
      <span class="type">{{ label }}</span>
      <span class="last4">{{ cardLast4 || '••••' }}</span>
    </div>
  </article>
</template>

<style scoped>
.card {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 10.5rem;
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  color: var(--text-on-dark);
  overflow: hidden;
  isolation: isolate;
}
.card::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: -1;
  background-repeat: no-repeat;
  background-position: center;
  background-size: cover;
  opacity: 0.9;
}
.grad-debit {
  background: var(--card-gradient-debit);
}
.grad-debit::after {
  background-image: var(--card-gradient-debit-overlay);
}
.grad-credit {
  background: var(--card-gradient-credit);
}
.grad-credit::after {
  background-image: var(--card-gradient-credit-overlay);
}
.top {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  min-height: 1.5rem;
}
.network {
  font-weight: 800;
  letter-spacing: 0.05em;
  font-size: 0.85rem;
  color: rgba(255, 255, 255, 0.95);
}
.menu {
  background: transparent;
  border: none;
  cursor: pointer;
  color: rgba(255, 255, 255, 0.9);
  padding: 0.25rem;
}
.menu-icon {
  width: 1.25rem;
  height: 1.25rem;
}
.balance {
  font-size: 1.65rem;
  font-weight: 700;
  margin: var(--space-3) 0;
  line-height: 1.1;
}
.bottom {
  display: flex;
  justify-content: space-between;
  font-size: 0.8rem;
  opacity: 0.9;
}
.type {
  text-transform: capitalize;
}
</style>
