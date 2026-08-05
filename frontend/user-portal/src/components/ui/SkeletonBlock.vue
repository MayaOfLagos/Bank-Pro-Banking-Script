<script setup>
import { computed } from 'vue'

const props = defineProps({
  height: {
    type: String,
    default: 'h-10'
  }
})

const heightClass = computed(() => props.height)
</script>

<template>
  <div class="skeleton" :class="heightClass" aria-hidden="true" />
</template>

<style scoped>
/* Was `bg-slate-200/80 dark:bg-slate-700/70`, but the theme system toggles
   [data-theme] on <html> and never adds a `.dark` class, so the dark variant
   never applied. Matching the inline `.skeleton` treatment the portal views
   already use keeps every loading state identical in both themes. */
.skeleton {
  border-radius: var(--radius-md);
  background: var(--surface-muted);
  animation: skeleton-pulse 1.6s ease-in-out infinite;
}
@keyframes skeleton-pulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 0.85; }
}
@media (prefers-reduced-motion: reduce) {
  .skeleton { animation: none; }
}
</style>
