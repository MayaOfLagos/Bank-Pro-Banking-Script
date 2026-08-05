<script setup>
/*
 * Stands in for a run of <TransactionItem>s.
 *
 * Row metrics copied from TransactionItem.vue: padding var(--space-3) 0,
 * gap var(--space-3), 2.5rem circular avatar, merchant 0.9rem, time 0.75rem
 * with a 0.15rem top margin, amount 0.95rem — and the 1px divider that the
 * parent lists draw between rows.
 */
import SkeletonText from './SkeletonText.vue'

defineProps({
  rows: { type: Number, default: 4 },
  // Row widths cycle so the list looks like data rather than a barcode.
  divided: { type: Boolean, default: true },
})

const MERCHANT_WIDTHS = ['62%', '45%', '72%', '54%', '38%']
const AMOUNT_WIDTHS = ['3.6rem', '4.4rem', '3rem', '4rem', '3.4rem']
</script>

<template>
  <!-- The root class is sk- prefixed because Vue stamps the *parent's* scope id
       onto a child component's root element: a bare `.rows` here would pick up
       the host view's card styling and paint a second card inside the first. -->
  <div class="sk-tx-rows" :class="{ 'sk-tx-rows--divided': divided }" aria-hidden="true">
    <div v-for="i in rows" :key="i" class="sk-tx-row">
      <div class="skeleton avatar" />
      <div class="meta">
        <SkeletonText size="0.9rem" :width="MERCHANT_WIDTHS[(i - 1) % MERCHANT_WIDTHS.length]" />
        <SkeletonText class="time" size="0.75rem" width="3.2rem" />
      </div>
      <SkeletonText
        class="amount"
        size="0.95rem"
        :width="AMOUNT_WIDTHS[(i - 1) % AMOUNT_WIDTHS.length]"
      />
    </div>
  </div>
</template>

<style scoped>
.sk-tx-rows--divided > * + * {
  border-top: 1px solid var(--divider);
}
.sk-tx-row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) 0;
}
.avatar {
  width: var(--tx-avatar-size, 2.5rem);
  height: var(--tx-avatar-size, 2.5rem);
  border-radius: var(--radius-pill);
  flex-shrink: 0;
}
.meta {
  flex: 1;
  min-width: 0;
}
.time {
  margin-top: 0.15rem;
}
.amount {
  flex-shrink: 0;
}
</style>
