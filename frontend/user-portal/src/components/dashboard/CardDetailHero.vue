<script setup>
import { computed } from 'vue'
import { SparklesIcon, WifiIcon } from '@heroicons/vue/24/outline'
import { formatCardNumber, maskCardNumber } from '../../utils/format'

const props = defineProps({
  card: { type: Object, required: true },
  masked: { type: Boolean, default: false },
})

const cardNumber = computed(() => {
  if (props.masked) return maskCardNumber(props.card?.card_number)
  return formatCardNumber(props.card?.card_number)
})
const holder = computed(() => props.card?.card_name || props.card?.holder || 'Card holder')
const expiry = computed(() => props.card?.card_expiration || props.card?.expiry || '--/--')
</script>

<template>
  <article class="hero">
    <div class="top">
      <SparklesIcon class="sparkle" aria-hidden="true" />
      <div class="badges">
        <WifiIcon class="wifi" aria-hidden="true" />
        <span class="network-dots" aria-hidden="true">
          <span></span><span></span>
        </span>
      </div>
    </div>

    <p class="number">{{ cardNumber }}</p>

    <div class="bottom">
      <div>
        <p class="label">Card holder</p>
        <p class="value">{{ holder }}</p>
      </div>
      <div>
        <p class="label">Expiry date</p>
        <p class="value">{{ expiry }}</p>
      </div>
    </div>
  </article>
</template>

<style scoped>
.hero {
  position: relative;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  min-height: 12.5rem;
  padding: var(--space-5);
  border-radius: var(--radius-lg);
  background: var(--card-gradient-credit);
  color: var(--text-on-dark);
  overflow: hidden;
  isolation: isolate;
}
.hero::after {
  content: '';
  position: absolute;
  inset: 0;
  z-index: -1;
  background: var(--card-gradient-credit-overlay);
}
.top {
  display: flex;
  align-items: center;
  justify-content: space-between;
}
.sparkle {
  width: 1.6rem;
  height: 1.6rem;
}
.badges {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}
.wifi {
  width: 1.25rem;
  height: 1.25rem;
  transform: rotate(90deg);
  opacity: 0.9;
}
.network-dots {
  display: inline-flex;
  gap: -0.25rem;
}
.network-dots span {
  width: 1.35rem;
  height: 1.35rem;
  border-radius: var(--radius-pill);
  background: rgba(255, 255, 255, 0.85);
  display: inline-block;
}
.network-dots span + span {
  margin-left: -0.55rem;
  background: rgba(255, 200, 0, 0.85);
  mix-blend-mode: screen;
}
.number {
  font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
  font-size: 1.35rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  margin: var(--space-4) 0;
}
.bottom {
  display: flex;
  justify-content: space-between;
  gap: var(--space-4);
}
.label {
  font-size: 0.7rem;
  opacity: 0.8;
  margin: 0;
  text-transform: none;
}
.value {
  font-size: 0.95rem;
  font-weight: 600;
  margin: 0.15rem 0 0;
}
</style>
