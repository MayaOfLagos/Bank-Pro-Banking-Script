<script setup>
import { computed } from 'vue'

const props = defineProps({
  value: { type: [Number, String], default: 0 },
  currency: { type: String, default: '$' },
  sign: { type: String, default: '' }, // '', '+', '-'
  minimumFractionDigits: { type: Number, default: 2 },
  maximumFractionDigits: { type: Number, default: 2 },
})

const formatted = computed(() => {
  const n = Number(props.value ?? 0)
  return n.toLocaleString(undefined, {
    minimumFractionDigits: props.minimumFractionDigits,
    maximumFractionDigits: props.maximumFractionDigits,
  })
})
</script>

<template><span>{{ sign }}{{ currency }}{{ formatted }}</span></template>
