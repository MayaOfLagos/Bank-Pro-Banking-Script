<script setup>
/**
 * Two-column tile picker shared by the Transfer and Deposit drawers. Each
 * option is { key, label, hint, icon, tone: 'accent' | 'muted' }.
 */
defineProps({
  options: { type: Array, required: true },
})

const emit = defineEmits(['select'])
</script>

<template>
  <div class="grid">
    <button
      v-for="option in options"
      :key="option.key"
      type="button"
      class="tile"
      @click="emit('select', option)"
    >
      <span class="tile-icon" :class="`tile-icon--${option.tone || 'accent'}`">
        <component :is="option.icon" aria-hidden="true" />
      </span>
      <span class="tile-label">{{ option.label }}</span>
      <span class="tile-hint">{{ option.hint }}</span>
    </button>
  </div>
</template>

<style scoped>
.grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--space-3);
  padding-bottom: var(--space-2);
}
.tile {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.3rem;
  padding: var(--space-4) var(--space-3);
  border-radius: var(--radius-lg);
  border: 1px solid var(--border);
  background: var(--surface-muted);
  color: var(--text-primary);
  text-align: left;
  cursor: pointer;
  transition: transform 0.08s ease, border-color 0.15s ease;
}
.tile:hover { border-color: var(--accent); }
.tile:active { transform: scale(0.98); }
.tile:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
.tile-icon {
  width: 2.5rem;
  height: 2.5rem;
  margin-bottom: 0.2rem;
  border-radius: var(--radius-md);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.tile-icon > svg { width: 1.3rem; height: 1.3rem; }
.tile-icon--accent { background: var(--accent-tint); color: var(--accent-strong); }
.tile-icon--muted { background: var(--surface); color: var(--text-secondary); }
.tile-label {
  font-size: 0.88rem;
  font-weight: 700;
  line-height: 1.2;
}
.tile-hint {
  font-size: 0.72rem;
  color: var(--text-secondary);
  line-height: 1.3;
}
</style>
