<script setup>
defineProps({
  message: { type: String, required: true },
  icon: { type: [Object, Function], default: null },
})
</script>

<template>
  <div class="bg-[#111827] rounded-2xl px-4 py-16 flex flex-col items-center gap-3">
    <component v-if="icon" :is="icon" class="h-10 w-10 text-slate-600" aria-hidden="true" />
    <p class="text-slate-500 text-sm">{{ message }}</p>
    <div class="mt-2">
      <slot />
    </div>
  </div>
</template>
