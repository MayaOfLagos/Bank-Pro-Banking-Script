<script setup>
/*
 * Stands in for the `.balance-card` that heads the domestic-transfer,
 * wire-transfer and withdrawal forms.
 *
 * Metrics: surface card, radius-lg, padding var(--space-4), row with
 * space-between; label 0.75rem with a 0.25rem bottom margin, amount 1.6rem,
 * optional limit line 0.75rem with a 0.35rem top margin; 2.75rem circular icon.
 */
import SkeletonText from './SkeletonText.vue'

defineProps({
  // The remaining-limit line is conditional on the account having a limit, so
  // views that never show it should turn it off or the card is a line too tall.
  limit: { type: Boolean, default: true },
})
</script>

<template>
  <section class="sk-balance-card" aria-hidden="true">
    <div class="copy">
      <SkeletonText class="label" size="0.75rem" width="8.5rem" />
      <SkeletonText size="1.6rem" width="7rem" />
      <SkeletonText v-if="limit" class="limit" size="0.75rem" width="13rem" />
    </div>
    <div class="skeleton icon" />
  </section>
</template>

<style scoped>
.sk-balance-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-4);
  background: var(--surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
}
.copy {
  min-width: 0;
}
.label {
  margin-bottom: 0.25rem;
}
.limit {
  margin-top: 0.35rem;
}
.icon {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-pill);
  flex-shrink: 0;
}
</style>
