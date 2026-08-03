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

/**
 * Pie: base circle is the light "remaining" color, wedge on top is the
 * dark "spent" color sweeping clockwise from 12 o'clock by (ratio * 2π).
 * Matches the reference — as the user spends more, the dark wedge grows.
 * Center (18,18), radius 15.
 */
const spentWedge = computed(() => {
  const r = ratio.value
  if (r <= 0) return ''
  if (r >= 1) {
    // Full circle via two half-arcs so no degenerate start=end point.
    return 'M 3 18 A 15 15 0 0 1 33 18 A 15 15 0 0 1 3 18 Z'
  }
  const angle = r * 2 * Math.PI
  // Sweep clockwise from 12 o'clock (18, 3) by `angle` radians.
  const endX = 18 + 15 * Math.sin(angle)
  const endY = 18 - 15 * Math.cos(angle)
  const largeArc = angle > Math.PI ? 1 : 0
  return `M 18 18 L 18 3 A 15 15 0 ${largeArc} 1 ${endX.toFixed(3)} ${endY.toFixed(3)} Z`
})

const rangeLabel = computed(() => {
  if (!props.weekStart && !props.weekEnd) return ''
  return [props.weekStart, props.weekEnd].filter(Boolean).join('–')
})

const spentDisplay = computed(() => `${props.currency}${formatMoneyInline(spentValue.value, { trimTrailingZero: true })}`)
const limitDisplay = computed(() => `${props.currency}${formatMoneyInline(limitValue.value, { trimTrailingZero: true })}`)
</script>

<template>
  <section class="widget" :aria-label="`${label}: ${spentDisplay} of ${limitDisplay}`">
    <div class="pie">
      <svg viewBox="0 0 36 36" aria-hidden="true">
        <!-- Base pie in tint; wedge overlay for spent portion. A darker
             stroke around the whole pie plus a hairline divider from the
             center to 12 o'clock guarantee the icon reads as a chart even
             at 0% spent. -->
        <circle cx="18" cy="18" r="15" class="pie-base" />
        <path v-if="spentWedge" :d="spentWedge" class="pie-spent" />
        <line x1="18" y1="18" x2="18" y2="3" class="pie-tick" />
        <circle cx="18" cy="18" r="15" class="pie-ring" />
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
.pie {
  width: 2.75rem;
  height: 2.75rem;
  flex-shrink: 0;
}
.pie svg {
  width: 100%;
  height: 100%;
}
.pie-base {
  fill: var(--accent-tint);
}
.pie-spent {
  fill: var(--accent);
}
.pie-tick {
  stroke: var(--accent-strong);
  stroke-width: 0.6;
  opacity: 0.6;
}
.pie-ring {
  fill: none;
  stroke: var(--accent-strong);
  stroke-width: 0.6;
  opacity: 0.5;
}
.meta {
  flex: 1;
  min-width: 0;
}
.label {
  font-size: 0.95rem;
  font-weight: 700;
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
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}
.limit {
  font-size: 0.8rem;
  color: var(--text-secondary);
  margin: 0.15rem 0 0;
}
</style>
