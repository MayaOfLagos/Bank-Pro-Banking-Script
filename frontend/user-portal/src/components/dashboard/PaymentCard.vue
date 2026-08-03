<script setup>
import { computed } from 'vue'
import { EllipsisHorizontalIcon } from '@heroicons/vue/24/solid'
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
const isVisa = computed(() => network.value === 'VISA')
const isMaster = computed(() => network.value === 'MASTERCARD' || network.value === 'MASTER')
const holderName = computed(() => String(props.card?.card_name || props.card?.holder || '').toUpperCase())
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
        <span v-if="isVisa" class="visa-mark">VISA</span>
        <span v-else-if="isMaster" class="master-mark" aria-label="Mastercard">
          <span class="master-dot master-dot--red"></span>
          <span class="master-dot master-dot--yellow"></span>
        </span>
        <span v-else-if="network" class="network">{{ network }}</span>
        <span v-else></span>
        <button type="button" class="menu" aria-label="Card options">
          <EllipsisHorizontalIcon class="menu-icon" aria-hidden="true" />
        </button>
      </div>

      <div class="chip-row">
        <svg class="chip" viewBox="0 0 40 30" aria-hidden="true">
          <rect x="1" y="1" width="38" height="28" rx="5" fill="#dcb046" stroke="rgba(80,55,15,0.4)" stroke-width="0.6"/>
          <rect x="14" y="9" width="12" height="12" rx="1.5" fill="none" stroke="rgba(80,55,15,0.55)" stroke-width="0.8"/>
          <path stroke="rgba(80,55,15,0.55)" stroke-width="0.8" fill="none"
                d="M1 9 h13 M1 21 h13 M26 9 h13 M26 21 h13 M20 1 v8 M20 21 v8" />
        </svg>
        <svg class="contactless" viewBox="0 0 24 24" aria-hidden="true">
          <path d="M8 5 a10 10 0 0 1 0 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M12 8 a6 6 0 0 1 0 8" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M16 11 a2.5 2.5 0 0 1 0 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>
      </div>

      <p class="balance">{{ balanceString }}</p>

      <div class="bottom">
        <span class="holder">{{ holderName || label }}</span>
        <span class="last4">•• {{ cardLast4 || '••••' }}</span>
      </div>
    </div>
  </article>
</template>

<style scoped>
.card {
  position: relative;
  min-height: 11rem;
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
  font-size: 1.1rem;
  color: #ffffff;
  text-shadow: 0 1px 0 rgba(0, 0, 0, 0.15);
}
.master-mark {
  display: inline-flex;
  align-items: center;
  height: 1.4rem;
}
.master-dot {
  width: 1.1rem;
  height: 1.1rem;
  border-radius: 50%;
  display: inline-block;
}
.master-dot--red { background: #eb001b; }
.master-dot--yellow {
  background: #f79e1b;
  margin-left: -0.55rem;
  mix-blend-mode: multiply;
}
.network {
  font-weight: 800;
  letter-spacing: 0.05em;
  font-size: 0.9rem;
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
  width: 1.35rem;
  height: 1.35rem;
}
.chip-row {
  display: flex;
  align-items: center;
  gap: 0.65rem;
  margin: var(--space-2) 0 var(--space-1);
}
.chip {
  width: 2.4rem;
  height: 1.8rem;
  flex-shrink: 0;
  filter: drop-shadow(0 1px 1px rgba(0, 0, 0, 0.25));
}
.contactless {
  width: 1.15rem;
  height: 1.15rem;
  color: rgba(255, 255, 255, 0.9);
  flex-shrink: 0;
}
.balance {
  font-size: 1.75rem;
  font-weight: 700;
  margin: 0;
  line-height: 1.05;
  letter-spacing: -0.01em;
}
.bottom {
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  gap: var(--space-3);
  font-size: 0.75rem;
}
.holder {
  text-transform: uppercase;
  letter-spacing: 0.06em;
  font-weight: 600;
  opacity: 0.9;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 60%;
}
.last4 {
  font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
  letter-spacing: 0.1em;
  opacity: 0.9;
}
</style>
