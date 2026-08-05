<script setup>
import { computed } from 'vue'
import { EllipsisHorizontalIcon } from '@heroicons/vue/24/solid'
import CardNetworkMark from './CardNetworkMark.vue'
import { formatMoneyInline, last4 } from '../../utils/format'

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
const network = computed(() => String(props.card?.network || '').toUpperCase())
</script>

<template>
  <article class="card" :class="gradientClass">
    <svg class="texture" viewBox="0 0 320 200" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
      <path
        class="petal petal-a"
        d="M330 -20 C 260 40, 240 110, 300 190 C 220 170, 170 130, 190 60 C 210 20, 270 -10, 330 -20 Z"
      />
      <path
        class="petal petal-b"
        d="M340 40 C 280 80, 250 150, 310 220 C 230 210, 170 180, 180 110 C 210 60, 280 30, 340 40 Z"
      />
      <path
        class="petal petal-c"
        d="M360 100 C 300 130, 260 190, 320 260 C 240 250, 180 210, 190 140 C 220 90, 300 80, 360 100 Z"
      />
      <path
        class="petal petal-d"
        d="M-10 40 C 40 60, 70 110, 30 180 C 90 150, 130 100, 100 40 C 70 10, 20 20, -10 40 Z"
      />
    </svg>

    <div class="content">
      <div class="top">
        <CardNetworkMark class="brand" :network="network" />
        <button type="button" class="menu" aria-label="Card options">
          <EllipsisHorizontalIcon class="menu-icon" aria-hidden="true" />
        </button>
      </div>

      <div class="foot">
        <p class="balance">{{ balanceString }}</p>
        <div class="bottom">
          <span class="kind">{{ label }}</span>
          <span class="last4">{{ cardLast4 || '••••' }}</span>
        </div>
      </div>
    </div>
  </article>
</template>

<style scoped>
.card {
  position: relative;
  /* Landscape like a real card, so height follows whatever width the
     carousel gives it instead of being pinned independently. */
  aspect-ratio: 1.6;
  border-radius: var(--radius-md);
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
.petal {
  mix-blend-mode: soft-light;
  fill: #ffffff;
}
.petal-a { fill-opacity: 0.45; }
.petal-b { fill-opacity: 0.32; }
.petal-c { fill-opacity: 0.22; }
.petal-d { fill-opacity: 0.28; }
.grad-credit .petal {
  mix-blend-mode: screen;
}
.grad-credit .petal-a { fill-opacity: 0.3; }
.grad-credit .petal-b { fill-opacity: 0.22; }
.grad-credit .petal-c { fill-opacity: 0.18; }
.grad-credit .petal-d { fill-opacity: 0.22; }
.content {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: 100%;
  padding: var(--space-3);
}
.top {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.brand {
  /* Drives the mark's height; the two networks stay in proportion. */
  font-size: 1.1rem;
  filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.2));
}
.menu {
  background: transparent;
  border: none;
  cursor: pointer;
  color: rgba(255, 255, 255, 0.9);
  padding: 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.menu-icon {
  width: 1.1rem;
  height: 1.1rem;
}
.foot {
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
  min-width: 0;
}
.balance {
  font-size: 1.15rem;
  font-weight: 700;
  margin: 0;
  line-height: 1.1;
  letter-spacing: -0.01em;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.bottom {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: var(--space-2);
  font-size: 0.7rem;
  opacity: 0.9;
}
.kind {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.last4 {
  font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
  letter-spacing: 0.06em;
  flex-shrink: 0;
}
</style>
