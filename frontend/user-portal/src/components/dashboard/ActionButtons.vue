<script setup>
/**
 * Pair of pill buttons. Each item in :actions can be:
 *   { label, to?, onClick?, icon?, variant: 'filled' | 'outlined', disabled? }
 * If both `to` and `onClick` are provided, click wins.
 */
import { computed } from 'vue'
import { RouterLink } from 'vue-router'

const props = defineProps({
  actions: {
    type: Array,
    required: true,
    validator: (v) => Array.isArray(v) && v.length > 0 && v.length <= 3,
  },
})

const emit = defineEmits(['action'])

const items = computed(() =>
  props.actions.map((a) => ({
    variant: 'outlined',
    ...a,
  }))
)

function handleClick(item, event) {
  if (typeof item.onClick === 'function') {
    event.preventDefault()
    item.onClick(event)
  }
  emit('action', item)
}
</script>

<template>
  <div class="row">
    <template v-for="item in items" :key="item.label">
      <component
        :is="item.to ? RouterLink : 'button'"
        :to="item.to"
        :type="item.to ? undefined : 'button'"
        class="btn"
        :class="[`btn--${item.variant}`, { 'btn--disabled': item.disabled }]"
        :disabled="item.disabled"
        :aria-label="item.ariaLabel || item.label"
        @click="handleClick(item, $event)"
      >
        <component :is="item.icon" v-if="item.icon" class="btn-icon" aria-hidden="true" />
        <span>{{ item.label }}</span>
      </component>
    </template>
  </div>
</template>

<style scoped>
.row {
  display: flex;
  gap: var(--space-3);
  width: 100%;
}
.btn {
  flex: 1 1 0;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  height: 3rem;
  padding: 0 var(--space-4);
  border-radius: var(--radius-pill);
  font-weight: 600;
  font-size: 0.9rem;
  text-decoration: none;
  border: 1px solid transparent;
  cursor: pointer;
  transition: transform 0.08s ease, background-color 0.15s ease;
}
.btn:active {
  transform: scale(0.98);
}
.btn--filled {
  background: var(--btn-primary-bg);
  color: var(--btn-primary-fg);
}
.btn--outlined {
  background: var(--btn-outline-bg);
  color: var(--btn-outline-fg);
  border-color: var(--btn-outline-border);
}
.btn--disabled {
  opacity: 0.5;
  cursor: not-allowed;
  pointer-events: none;
}
.btn-icon {
  width: 1rem;
  height: 1rem;
}
</style>
