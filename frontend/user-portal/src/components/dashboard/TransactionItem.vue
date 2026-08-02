<script setup>
import { computed } from 'vue'
import { formatMoneyInline, coerceNumber, merchantInitials } from '../../utils/format'

const props = defineProps({
  transaction: { type: Object, required: true },
  currency: { type: String, default: '$' },
})

const tx = computed(() => props.transaction || {})

// Direction: 1 = credit / positive, else debit / negative
const isCredit = computed(() => Number(tx.value.trans_type) === 1)
const amountValue = computed(() => coerceNumber(tx.value.amount))
const amountDisplay = computed(() => {
  const sign = isCredit.value ? '+' : '-'
  return `${sign}${props.currency}${formatMoneyInline(amountValue.value)}`
})

const merchant = computed(() => tx.value.description || tx.value.sender_name || 'Account transaction')
const initials = computed(() => merchantInitials(merchant.value))

const timeLabel = computed(() => {
  const raw = tx.value.created_at || tx.value.time_created || tx.value.date
  if (!raw) return ''
  const d = new Date(String(raw).replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return String(raw)
  return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
})
</script>

<template>
  <div class="row">
    <div class="avatar" :class="{ 'avatar--credit': isCredit }" aria-hidden="true">
      {{ initials }}
    </div>
    <div class="meta">
      <p class="merchant">{{ merchant }}</p>
      <p v-if="timeLabel" class="time">{{ timeLabel }}</p>
    </div>
    <p class="amount" :class="{ 'amount--credit': isCredit }">{{ amountDisplay }}</p>
  </div>
</template>

<style scoped>
.row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) 0;
}
.avatar {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-pill);
  background: var(--accent-soft);
  color: var(--accent-strong);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  flex-shrink: 0;
}
.avatar--credit {
  background: var(--accent-soft);
  color: var(--accent-strong);
}
.meta {
  flex: 1;
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
