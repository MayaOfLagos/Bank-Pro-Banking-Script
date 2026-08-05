<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import TransactionAvatar from '../ui/TransactionAvatar.vue'
import { formatMoneyInline, coerceNumber } from '../../utils/format'

const props = defineProps({
  transaction: { type: Object, required: true },
  currency: { type: String, default: '$' },
  to: { type: [String, Object], default: null },
})

const tx = computed(() => props.transaction || {})

// Direction: 1 = credit / positive, else debit / negative
const isCredit = computed(() => Number(tx.value.trans_type) === 1)
const amountValue = computed(() => coerceNumber(tx.value.amount))
const amountDisplay = computed(() => {
  const sign = isCredit.value ? '+' : '-'
  return `${sign}${props.currency}${formatMoneyInline(amountValue.value, { trimTrailingZero: true })}`
})

const merchant = computed(() => tx.value.description || tx.value.sender_name || 'Account transaction')
const statusLabel = computed(() => tx.value.status_label || '')
const isPending = computed(() => {
  const s = Number(tx.value.trans_status)
  // 0 = Processing, 2 = Hold — both worth surfacing next to the merchant name.
  return s === 0 || s === 2
})

const timeLabel = computed(() => {
  const raw = tx.value.created_at || tx.value.time_created || tx.value.date
  if (!raw) return ''
  const d = new Date(String(raw).replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return String(raw)
  return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
})
</script>

<template>
  <component :is="to ? RouterLink : 'div'" :to="to" class="row" :class="{ 'row--link': !!to }">
    <TransactionAvatar :transaction="tx" />

    <div class="meta">
      <div class="merchant-line">
        <p class="merchant">{{ merchant }}</p>
        <span v-if="isPending && statusLabel" class="status-pill">{{ statusLabel }}</span>
      </div>
      <p v-if="timeLabel" class="time">{{ timeLabel }}</p>
    </div>

    <p class="amount" :class="{ 'amount--credit': isCredit }">{{ amountDisplay }}</p>
  </component>
</template>

<style scoped>
.row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) 0;
  color: inherit;
  text-decoration: none;
}
.row--link {
  cursor: pointer;
  transition: transform 0.08s ease, background-color 0.15s ease;
  border-radius: var(--radius-md);
  margin: 0 calc(var(--space-2) * -1);
  padding-inline: var(--space-2);
}
.row--link:hover {
  background: color-mix(in srgb, var(--text-primary) 4%, transparent);
}
.row--link:active {
  transform: scale(0.995);
}
.meta {
  flex: 1;
  min-width: 0;
}
.merchant-line {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  min-width: 0;
}
.merchant {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  flex: 1 1 auto;
  min-width: 0;
}
.status-pill {
  font-size: 0.65rem;
  font-weight: 700;
  padding: 0.1rem 0.4rem;
  border-radius: var(--radius-pill);
  background: rgba(180, 83, 9, 0.14);
  color: var(--warning-fg);
  text-transform: uppercase;
  letter-spacing: 0.04em;
  flex-shrink: 0;
}
.time {
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin: 0.15rem 0 0;
}
.amount {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--money-negative);
  margin: 0;
  flex-shrink: 0;
}
.amount--credit {
  color: var(--money-positive);
}
</style>
