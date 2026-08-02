<script setup>
defineProps({
  message: { type: String, required: true },
  compact: { type: Boolean, default: false },
})
</script>

<template>
  <div
    :class="compact
      ? 'rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-4 py-3'
      : 'rounded-2xl bg-red-500/10 border border-red-500/30 text-red-400 text-sm px-5 py-4'"
    role="alert"
  >
    {{ message }}
  </div>
</template>
