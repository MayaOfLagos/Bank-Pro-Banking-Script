<script setup>
import { RouterLink } from 'vue-router'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'

/**
 * List of tappable rows. Each option:
 *   { label, icon, to?, onClick?, ariaLabel? }
 */
defineProps({
  options: { type: Array, required: true },
})

const emit = defineEmits(['select'])

function handleClick(option, event) {
  if (typeof option.onClick === 'function') {
    event.preventDefault()
    option.onClick(event)
  }
  emit('select', option)
}
</script>

<template>
  <div class="list">
    <template v-for="(option, i) in options" :key="option.label + i">
      <component
        :is="option.to ? RouterLink : 'button'"
        :to="option.to"
        :type="option.to ? undefined : 'button'"
        class="row"
        :aria-label="option.ariaLabel || option.label"
        @click="handleClick(option, $event)"
      >
        <div class="icon-wrap">
          <component :is="option.icon" v-if="option.icon" class="icon" aria-hidden="true" />
        </div>
        <span class="label">{{ option.label }}</span>
        <ChevronRightIcon class="chev" aria-hidden="true" />
      </component>
    </template>
  </div>
</template>

<style scoped>
.list {
  background: var(--surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
  overflow: hidden;
}
.row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  width: 100%;
  padding: var(--space-4);
  background: transparent;
  border: none;
  color: var(--text-primary);
  text-decoration: none;
  cursor: pointer;
  text-align: left;
  font: inherit;
}
.row + .row {
  border-top: 1px solid var(--divider);
}
.icon-wrap {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-md);
  background: var(--surface-muted);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.icon {
  width: 1.1rem;
  height: 1.1rem;
  color: var(--text-primary);
}
.label {
  flex: 1;
  font-size: 0.95rem;
  font-weight: 500;
}
.chev {
  width: 1.1rem;
  height: 1.1rem;
  color: var(--text-secondary);
  flex-shrink: 0;
}
</style>
