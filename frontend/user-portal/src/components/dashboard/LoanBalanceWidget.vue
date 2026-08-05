<script setup>
import { computed } from 'vue'
import { WalletIcon, ChevronRightIcon } from '@heroicons/vue/24/solid'
import { formatMoneyInline, coerceNumber } from '../../utils/format'

const props = defineProps({
  balance: { type: [Number, String], default: 0 },
  activeCount: { type: Number, default: 0 },
  currency: { type: String, default: '$' },
})

const emit = defineEmits(['open'])

const balanceValue = computed(() => coerceNumber(props.balance))

const balanceDisplay = computed(
  () => `${props.currency}${formatMoneyInline(balanceValue.value, { trimTrailingZero: true })}`,
)

const countLabel = computed(() => {
  const n = props.activeCount
  if (!n) return 'No active loans'
  return n === 1 ? '1 active loan' : `${n} active loans`
})
</script>

<template>
  <button
    type="button"
    class="widget"
    :aria-label="`Loan balance ${balanceDisplay}, ${countLabel}. View loans`"
    @click="emit('open')"
  >
    <span class="icon">
      <WalletIcon aria-hidden="true" />
    </span>
    <span class="meta">
      <span class="label">Loan Balance</span>
      <span class="count">{{ countLabel }}</span>
    </span>
    <span class="amounts">
      <span class="balance">{{ balanceDisplay }}</span>
      <span class="more">
        View
        <ChevronRightIcon class="chevron" aria-hidden="true" />
      </span>
    </span>
  </button>
</template>

<style scoped>
.widget {
  width: 100%;
  display: flex;
  align-items: center;
  gap: var(--space-3);
  background: var(--surface);
  border: 0;
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-card);
  text-align: left;
  cursor: pointer;
  transition: transform 0.08s ease;
}
.widget:active { transform: scale(0.99); }
.widget:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
.icon {
  width: 2.75rem;
  height: 2.75rem;
  flex-shrink: 0;
  border-radius: var(--radius-md);
  background: var(--accent-tint);
  color: var(--accent-strong);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.icon > svg { width: 1.35rem; height: 1.35rem; }
.meta {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
}
.label {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text-primary);
}
.count {
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin-top: 0.15rem;
}
.amounts {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}
.balance {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
}
.more {
  display: inline-flex;
  align-items: center;
  gap: 0.1rem;
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin-top: 0.15rem;
}
.chevron { width: 0.85rem; height: 0.85rem; }
</style>
