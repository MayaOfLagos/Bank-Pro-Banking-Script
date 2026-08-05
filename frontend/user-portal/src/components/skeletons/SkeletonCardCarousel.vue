<script setup>
/*
 * Stands in for <CardCarousel>. Reused twice on the dashboard: once inside the
 * whole-page skeleton, and once on its own for the window where the balance has
 * landed but /api/user/card.php has not. Without the second use the carousel
 * flashes "No cards linked yet." at customers who do have cards.
 *
 * Metrics from CardCarousel.vue: surface card, radius-lg, padding var(--space-4);
 * head 1rem title + a 1.25rem icon in 0.35rem padding, margin-bottom var(--space-3);
 * scroller gap var(--space-3), padding-bottom var(--space-2); slots flex 1 1 0
 * carrying a PaymentCard at aspect-ratio 1.6 / radius-md.
 */
import SkeletonText from './SkeletonText.vue'

defineProps({
  // Two is the resting layout — three or more turns the row into a carousel.
  slots: { type: Number, default: 2 },
})
</script>

<template>
  <section class="sk-carousel" aria-hidden="true">
    <div class="head">
      <SkeletonText size="1rem" width="3.2rem" />
      <div class="skeleton add" />
    </div>
    <div class="scroller">
      <div v-for="i in slots" :key="i" class="slot skeleton" />
    </div>
  </section>
</template>

<style scoped>
.sk-carousel {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-card);
}
.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-3);
}
.add {
  width: 1.25rem;
  height: 1.25rem;
  margin: 0.35rem;
  border-radius: var(--radius-sm);
}
.scroller {
  display: flex;
  gap: var(--space-3);
  padding-bottom: var(--space-2);
}
.slot {
  flex: 1 1 0;
  min-width: 0;
  aspect-ratio: 1.6;
  border-radius: var(--radius-md);
}
</style>
