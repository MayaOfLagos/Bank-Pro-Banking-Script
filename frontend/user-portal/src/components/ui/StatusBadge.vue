<script setup>
import { computed } from 'vue'
import {
  CheckCircleIcon,
  ClockIcon,
  XCircleIcon,
  PauseCircleIcon,
  QuestionMarkCircleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  status: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
})

const key = computed(() => String(props.status ?? '').toLowerCase())

const variant = computed(() => {
  const k = key.value
  if (k.includes('complet') || k.includes('approv') || k.includes('success') || k === '1') {
    return { tone: 'success', icon: CheckCircleIcon }
  }
  if (k.includes('hold') || k === '2') {
    return { tone: 'warning', icon: PauseCircleIcon }
  }
  if (k.includes('reject') || k.includes('fail') || k.includes('cancel') || k.includes('declin') || k === '3') {
    return { tone: 'danger', icon: XCircleIcon }
  }
  if (k.includes('pending') || k.includes('process') || k === '0') {
    return { tone: 'neutral', icon: ClockIcon }
  }
  return { tone: 'neutral', icon: QuestionMarkCircleIcon }
})

const displayLabel = computed(() => props.label || props.status || 'Unknown')
</script>

<template>
  <span class="badge" :class="`badge--${variant.tone}`">
    <component :is="variant.icon" class="badge-icon" aria-hidden="true" />
    <span>{{ displayLabel }}</span>
  </span>
</template>

<style scoped>
/* Uses the shared status tokens (--success-fg / --warning-fg / --danger-fg)
   rather than fixed emerald/amber/red so badges track the active theme. */
.badge {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  border-radius: var(--radius-pill);
  padding: 0.15rem 0.5rem;
  font-size: 0.75rem;
  font-weight: 500;
}
.badge-icon {
  width: 0.75rem;
  height: 0.75rem;
}
.badge--success {
  background: color-mix(in srgb, var(--success-fg) 18%, transparent);
  color: var(--success-fg);
}
.badge--warning {
  background: color-mix(in srgb, var(--warning-fg) 18%, transparent);
  color: var(--warning-fg);
}
.badge--danger {
  background: var(--danger-bg);
  color: var(--danger-fg);
}
.badge--neutral {
  background: color-mix(in srgb, var(--text-muted) 18%, transparent);
  color: var(--text-secondary);
}
</style>
