<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center gap-3">
      <div v-for="item in series" :key="item.name" class="flex items-center gap-2 text-xs font-medium text-slate-500 dark:text-slate-400">
        <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: item.color }"></span>
        <span>{{ item.name }}</span>
      </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-slate-200/70 bg-white/70 p-3 dark:border-slate-800 dark:bg-slate-900/60">
      <svg viewBox="0 0 100 44" class="h-52 w-full">
        <g v-for="line in 5" :key="line">
          <line
            x1="0"
            :y1="line * 8"
            x2="100"
            :y2="line * 8"
            class="stroke-slate-200/80 dark:stroke-slate-800"
            stroke-width="0.4"
            stroke-dasharray="1.5 2"
          />
        </g>

        <template v-for="item in normalizedSeries" :key="item.name">
          <path :d="item.areaPath" :fill="item.fill" fill-opacity="0.18" />
          <path :d="item.linePath" fill="none" :stroke="item.color" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
          <circle
            v-for="point in item.points"
            :key="`${item.name}-${point.x}`"
            :cx="point.x"
            :cy="point.y"
            :fill="item.color"
            r="1.2"
          />
        </template>
      </svg>

      <div class="mt-1 grid" :style="{ gridTemplateColumns: `repeat(${Math.max(labels.length, 1)}, minmax(0, 1fr))` }">
        <span v-for="label in labels" :key="label" class="truncate text-center text-[11px] font-medium text-slate-400 dark:text-slate-500">
          {{ label }}
        </span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  labels: {
    type: Array,
    default: () => []
  },
  series: {
    type: Array,
    default: () => []
  }
})

const normalizedSeries = computed(() => {
  const values = props.series.flatMap((item) => item.data || [])
  const maxValue = Math.max(...values, 0)
  const safeMax = maxValue > 0 ? maxValue : 1
  const count = Math.max(props.labels.length - 1, 1)

  return props.series.map((item) => {
    const points = (item.data || []).map((value, index) => {
      const x = (index / count) * 100
      const normalized = Number(value || 0) / safeMax
      const y = 36 - normalized * 28
      return {
        x: Number(x.toFixed(3)),
        y: Number(y.toFixed(3))
      }
    })

    const linePath = points.length
      ? points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`).join(' ')
      : ''

    const areaPath = points.length
      ? `${linePath} L ${points[points.length - 1].x} 40 L ${points[0].x} 40 Z`
      : ''

    return {
      ...item,
      points,
      linePath,
      areaPath,
      fill: item.color
    }
  })
})
</script>
