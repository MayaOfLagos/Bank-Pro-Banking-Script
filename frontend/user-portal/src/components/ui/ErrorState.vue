<script setup>
defineProps({
  message: { type: String, required: true },
  compact: { type: Boolean, default: false },
})
</script>

<template>
  <div class="error" :class="{ 'error--compact': compact }" role="alert">
    {{ message }}
  </div>
</template>

<style scoped>
/* Token-driven so the same alert reads correctly in both themes and when
   printed — the previous fixed red-400/red-500 tints washed out on the light
   surface this component is rendered on in ~20 views. */
.error {
  background: var(--danger-bg);
  border: 1px solid var(--danger-border);
  color: var(--danger-fg);
  border-radius: var(--radius-lg);
  padding: var(--space-4) var(--space-5);
  font-size: 0.875rem;
  line-height: 1.45;
}
.error--compact {
  border-radius: var(--radius-md);
  padding: var(--space-3) var(--space-4);
}
</style>
