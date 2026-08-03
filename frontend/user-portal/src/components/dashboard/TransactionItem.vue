<script setup>
import { computed } from 'vue'
import { ArrowDownIcon } from '@heroicons/vue/24/solid'
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
  return `${sign}${props.currency}${formatMoneyInline(amountValue.value, { trimTrailingZero: true })}`
})

const merchant = computed(() => tx.value.description || tx.value.sender_name || 'Account transaction')

const timeLabel = computed(() => {
  const raw = tx.value.created_at || tx.value.time_created || tx.value.date
  if (!raw) return ''
  const d = new Date(String(raw).replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return String(raw)
  return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
})

/**
 * Merchant category → avatar style. Matches the reference's per-merchant
 * treatment (Uber = black text, Amazon = orange "a", Cashback = purple
 * with arrow, FlowerShop = light purple w/ flower). Unknown merchants
 * fall back to purple-tinted initials.
 */
const category = computed(() => {
  const name = merchant.value.toLowerCase()
  if (name.includes('uber')) return 'uber'
  if (name.includes('amazon')) return 'amazon'
  if (name.includes('cashback') || name.includes('refund') || name.includes('reward')) return 'cashback'
  if (name.includes('flower')) return 'flower'
  if (isCredit.value) return 'cashback'
  return 'default'
})

const initials = computed(() => merchantInitials(merchant.value))
</script>

<template>
  <div class="row">
    <div class="avatar" :class="`avatar--${category}`" aria-hidden="true">
      <template v-if="category === 'uber'">
        <span class="uber-mark">Uber</span>
      </template>
      <template v-else-if="category === 'amazon'">
        <span class="amazon-mark">a</span>
      </template>
      <template v-else-if="category === 'cashback'">
        <ArrowDownIcon class="cashback-icon" />
      </template>
      <template v-else-if="category === 'flower'">
        <span class="flower-mark">✿</span>
      </template>
      <template v-else>
        {{ initials }}
      </template>
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
  display: inline-flex;
  align-items: center;
  justify-content: center;
  font-size: 0.75rem;
  font-weight: 700;
  flex-shrink: 0;
  overflow: hidden;
}
.avatar--default,
.avatar--flower {
  background: var(--accent-tint);
  color: var(--accent-strong);
}
.avatar--cashback {
  background: var(--accent);
  color: var(--text-on-accent);
}
.avatar--uber {
  background: var(--text-primary);
  color: var(--surface);
}
.avatar--amazon {
  background: var(--text-primary);
  color: #ff9900;
}
.uber-mark {
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: -0.01em;
}
.amazon-mark {
  font-family: "Comic Sans MS", "Verdana", sans-serif;
  font-size: 1.15rem;
  font-weight: 700;
  line-height: 1;
}
.cashback-icon {
  width: 1rem;
  height: 1rem;
}
.flower-mark {
  color: #d946ef;
  font-size: 1.1rem;
  line-height: 1;
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
