<template>
  <div class="animate-pulse rounded-xl bg-slate-200/80 dark:bg-slate-700/70" :class="heightClass" />
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  height: {
    type: String,
    default: 'h-10'
  }
})

const heightClass = computed(() => props.height)
</script>
