<script setup>
import { computed } from 'vue'
import { formatMoneyInline, coerceNumber } from '../../utils/format'

const props = defineProps({
  spent: { type: [Number, String], default: 0 },
  limit: { type: [Number, String], default: 0 },
  weekStart: { type: String, default: '' },
  weekEnd: { type: String, default: '' },
  currency: { type: String, default: '$' },
  label: { type: String, default: 'Week budget' },
})

const spentValue = computed(() => coerceNumber(props.spent))
const limitValue = computed(() => coerceNumber(props.limit))

const ratio = computed(() => {
  if (!limitValue.value) return 0
  return Math.min(1, Math.max(0, spentValue.value / limitValue.value))
})

// SVG donut math: r = 15, circumference ≈ 94.2. dash = ratio * circumference
const circumference = 2 * Math.PI * 15
const dashOffset = computed(() => circumference * (1 - ratio.value))

const rangeLabel = computed(() => {
  if (!props.weekStart && !props.weekEnd) return ''
  return [props.weekStart, props.weekEnd].filter(Boolean).join('–')
})

const spentDisplay = computed(() => `${props.currency}${formatMoneyInline(spentValue.value)}`)
const limitDisplay = computed(() => `${props.currency}${formatMoneyInline(limitValue.value)}`)
</script>

<template>
  <section class="widget" :aria-label="`${label}: ${spentDisplay} of ${limitDisplay}`">
    <div class="donut">
      <svg viewBox="0 0 36 36" aria-hidden="true">
        <circle
          class="track"
          cx="18"
          cy="18"
          r="15"
          fill="none"
          stroke-width="4"
        />
        <circle
          class="progress"
          cx="18"
          cy="18"
          r="15"
          fill="none"
          stroke-width="4"
          :stroke-dasharray="circumference"
          :stroke-dashoffset="dashOffset"
          transform="rotate(-90 18 18)"
        />
      </svg>
    </div>
    <div class="meta">
      <p class="label">{{ label }}</p>
      <p v-if="rangeLabel" class="range">{{ rangeLabel }}</p>
    </div>
    <div class="amounts">
      <p class="spent">{{ spentDisplay }}</p>
      <p class="limit">{{ limitDisplay }}</p>
    </div>
  </section>
</template>

<style scoped>
.widget {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-card);
}
.donut {
  width: 2.75rem;
  height: 2.75rem;
  flex-shrink: 0;
}
.donut svg {
  width: 100%;
  height: 100%;
}
.donut .track {
  stroke: var(--divider);
}
.donut .progress {
  stroke: var(--accent);
  stroke-linecap: round;
  transition: stroke-dashoffset 0.4s ease;
}
.meta {
  flex: 1;
  min-width: 0;
}
.label {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0;
}
.range {
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin: 0.15rem 0 0;
}
.amounts {
  text-align: right;
  flex-shrink: 0;
}
.spent {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}
.limit {
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin: 0.15rem 0 0;
}
</style>
