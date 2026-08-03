<script setup>
defineProps({
  message: { type: String, required: true },
  icon: { type: [Object, Function], default: null },
})
</script>

<template>
  <div class="empty">
    <component v-if="icon" :is="icon" class="icon" aria-hidden="true" />
    <p class="message">{{ message }}</p>
    <div class="cta">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.empty {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-8) var(--space-4);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  box-shadow: var(--shadow-card);
}
.icon {
  width: 2.5rem;
  height: 2.5rem;
  color: var(--text-muted);
}
.message {
  color: var(--text-secondary);
  font-size: 0.9rem;
  margin: 0;
  text-align: center;
}
.cta {
  margin-top: var(--space-2);
}
.cta:empty {
  display: none;
}
</style>
