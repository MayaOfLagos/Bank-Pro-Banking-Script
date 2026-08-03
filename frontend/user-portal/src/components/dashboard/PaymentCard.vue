<script setup>
import { computed } from 'vue'
import { EllipsisHorizontalIcon } from '@heroicons/vue/24/outline'
import { formatMoneyInline, last4 } from '../../utils/format'

/**
 * A single card in the horizontal carousel. Presentation-only — the parent
 * decides which cards to render. Type drives the gradient token used.
 * The wavy floral texture is an inline SVG so it scales with the card
 * and stays crisp on retina.
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
const balanceString = computed(() => `${props.currency}${formatMoneyInline(props.card?.balance ?? 0, { trimTrailingZero: true })}`)
const cardLast4 = computed(() => last4(props.card?.card_number ?? props.card?.masked_number ?? props.card?.last4))
const network = computed(() => (props.card?.network || '').toUpperCase())
const isVisa = computed(() => network.value === 'VISA')
</script>

<template>
  <article class="card" :class="gradientClass">
    <!-- Wavy floral texture. Two overlapping petal shapes create the
         organic gradient depth the reference shows. -->
    <svg class="texture" viewBox="0 0 320 200" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
      <defs>
        <radialGradient id="petal-a" cx="70%" cy="45%" r="65%">
          <stop offset="0%" stop-color="#ffffff" stop-opacity="0.25" />
          <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
        </radialGradient>
        <radialGradient id="petal-b" cx="90%" cy="80%" r="70%">
          <stop offset="0%" stop-color="#ffffff" stop-opacity="0.18" />
          <stop offset="100%" stop-color="#ffffff" stop-opacity="0" />
        </radialGradient>
      </defs>
      <path
        fill="url(#petal-a)"
        d="M330 -20 C 260 40, 240 110, 300 190 C 220 170, 170 130, 190 60 C 210 20, 270 -10, 330 -20 Z"
      />
      <path
        fill="url(#petal-b)"
        d="M340 40 C 280 80, 250 150, 310 220 C 230 210, 170 180, 180 110 C 210 60, 280 30, 340 40 Z"
      />
      <path
        fill="#ffffff"
        fill-opacity="0.08"
        d="M360 100 C 300 130, 260 190, 320 260 C 240 250, 180 210, 190 140 C 220 90, 300 80, 360 100 Z"
      />
    </svg>

    <div class="content">
      <div class="top">
        <span v-if="isVisa" class="visa-mark">VISA</span>
        <span v-else-if="network" class="network">{{ network }}</span>
        <span v-else></span>
        <button type="button" class="menu" aria-label="Card options">
          <EllipsisHorizontalIcon class="menu-icon" aria-hidden="true" />
        </button>
      </div>

      <p class="balance">{{ balanceString }}</p>

      <div class="bottom">
        <span class="type">{{ label }}</span>
        <span class="last4">{{ cardLast4 || '••••' }}</span>
      </div>
    </div>
  </article>
</template>

<style scoped>
.card {
  position: relative;
  min-height: 10.5rem;
  border-radius: var(--radius-lg);
  color: var(--text-on-dark);
  overflow: hidden;
  isolation: isolate;
}
.grad-debit {
  background: var(--card-gradient-debit);
}
.grad-credit {
  background: var(--card-gradient-credit);
}
.texture {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  z-index: 0;
  pointer-events: none;
}
.content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 100%;
  padding: var(--space-4);
}
.top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  min-height: 1.5rem;
}
.visa-mark {
  font-family: "Times New Roman", Georgia, serif;
  font-style: italic;
  font-weight: 800;
  letter-spacing: 0.05em;
  font-size: 1rem;
  color: rgba(255, 255, 255, 0.98);
  text-shadow: 0 1px 0 rgba(0, 0, 0, 0.1);
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
  display: inline-flex;
  align-items: center;
  justify-content: center;
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
  align-items: flex-end;
  font-size: 0.8rem;
  opacity: 0.9;
}
.type {
  text-transform: capitalize;
}
.last4 {
  font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
  letter-spacing: 0.05em;
}
</style>
