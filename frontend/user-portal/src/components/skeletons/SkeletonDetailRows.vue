<script setup>
/*
 * Stands in for the label/value `.detail-card` used by the transaction, loan,
 * transfer-success and profile screens.
 *
 * Metrics from TransactionDetailView.vue: card padding var(--space-2)
 * var(--space-4), each row padding var(--space-3) 0 with a 1px divider between,
 * label 0.85rem on the left, value 0.9rem right-aligned. Set :card="false" when
 * the view supplies its own card shell (different padding, extra heading, …).
 */
import SkeletonText from './SkeletonText.vue'

defineProps({
  rows: { type: Number, default: 5 },
  card: { type: Boolean, default: true },
})

const LABEL_WIDTHS = ['3.5rem', '5rem', '4.2rem', '6rem', '3.2rem', '4.6rem', '5.4rem']
const VALUE_WIDTHS = ['30%', '48%', '38%', '55%', '42%', '33%', '50%']
</script>

<template>
  <div :class="['sk-detail-rows', { 'sk-detail-rows--card': card }]" aria-hidden="true">
    <div v-for="i in rows" :key="i" class="row">
      <SkeletonText
        class="row-label"
        size="0.85rem"
        :width="LABEL_WIDTHS[(i - 1) % LABEL_WIDTHS.length]"
      />
      <SkeletonText
        class="row-value"
        size="0.9rem"
        :width="VALUE_WIDTHS[(i - 1) % VALUE_WIDTHS.length]"
      />
    </div>
  </div>
</template>

<style scoped>
.sk-detail-rows--card {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-2) var(--space-4);
  box-shadow: var(--shadow-card);
}
.row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: var(--space-4);
  padding: var(--space-3) 0;
}
.row + .row {
  border-top: 1px solid var(--divider);
}
.row-label {
  flex-shrink: 0;
}
/* Values are right-aligned in the real card, so the bar has to hug the right
   edge or the ragged edge lands on the wrong side. It also has to take the
   remaining track, otherwise its percentage width resolves against itself. */
.row-value {
  flex: 1 1 auto;
  min-width: 0;
}
.row-value :deep(.sk-text__line) {
  justify-content: flex-end;
}
</style>
