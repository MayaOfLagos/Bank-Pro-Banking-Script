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
    return { classes: 'bg-emerald-500/20 text-emerald-400', icon: CheckCircleIcon }
  }
  if (k.includes('hold') || k === '2') {
    return { classes: 'bg-amber-500/20 text-amber-400', icon: PauseCircleIcon }
  }
  if (k.includes('reject') || k.includes('fail') || k.includes('cancel') || k.includes('declin') || k === '3') {
    return { classes: 'bg-red-500/20 text-red-400', icon: XCircleIcon }
  }
  if (k.includes('pending') || k.includes('process') || k === '0') {
    return { classes: 'bg-slate-500/20 text-slate-400', icon: ClockIcon }
  }
  return { classes: 'bg-slate-500/20 text-slate-400', icon: QuestionMarkCircleIcon }
})

const displayLabel = computed(() => props.label || props.status || 'Unknown')
</script>

<template>
  <span
    class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
    :class="variant.classes"
  >
    <component :is="variant.icon" class="h-3 w-3" aria-hidden="true" />
    <span>{{ displayLabel }}</span>
  </span>
</template>
