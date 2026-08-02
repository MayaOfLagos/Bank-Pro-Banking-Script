<template>
  <div class="grid gap-6 lg:grid-cols-[220px_minmax(0,1fr)] lg:items-center">
    <div class="relative mx-auto flex h-52 w-52 items-center justify-center">
      <svg viewBox="0 0 42 42" class="h-full w-full -rotate-90">
        <circle cx="21" cy="21" r="15.915" fill="none" class="stroke-slate-200 dark:stroke-slate-800" stroke-width="4"></circle>
        <circle
          v-for="segment in segmentsWithStroke"
          :key="segment.label"
          cx="21"
          cy="21"
          r="15.915"
          fill="none"
          :stroke="segment.color"
          stroke-width="4"
          stroke-linecap="round"
          :stroke-dasharray="segment.dasharray"
          :stroke-dashoffset="segment.offset"
        ></circle>
      </svg>
      <div class="absolute text-center">
        <p class="text-xs font-medium uppercase tracking-[0.22em] text-slate-400">Portfolio</p>
        <p class="mt-2 text-3xl font-semibold text-slate-900 dark:text-white">{{ totalLabel }}</p>
      </div>
    </div>

    <div class="space-y-3">
      <div
        v-for="segment in segmentsWithStroke"
        :key="segment.label"
        class="rounded-2xl border border-slate-200/80 bg-white/80 p-3 dark:border-slate-800 dark:bg-slate-900/70"
      >
        <div class="flex items-center justify-between gap-4">
          <div class="flex items-center gap-3">
            <span class="h-3 w-3 rounded-full" :style="{ backgroundColor: segment.color }"></span>
            <div>
              <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ segment.label }}</p>
              <p class="text-xs text-slate-500 dark:text-slate-400">{{ segment.percent.toFixed(1) }}% allocation</p>
            </div>
          </div>
          <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ segment.formatted }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  segments: {
    type: Array,
    default: () => []
  },
  totalLabel: {
    type: String,
    default: '$0.00'
  }
})

const circumference = 100

const segmentsWithStroke = computed(() => {
  const total = props.segments.reduce((sum, segment) => sum + Number(segment.value || 0), 0)
  const safeTotal = total > 0 ? total : 1
  let cumulative = 0

  return props.segments.map((segment) => {
    const value = Number(segment.value || 0)
    const portion = (value / safeTotal) * circumference
    const offset = circumference - cumulative
    cumulative += portion

    return {
      ...segment,
      percent: (value / safeTotal) * 100,
      dasharray: `${portion} ${circumference - portion}`,
      offset
    }
  })
})
</script>
