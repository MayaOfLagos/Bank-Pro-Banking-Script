<script setup>
import { computed, useId } from 'vue'

const props = defineProps({
  label: { type: String, default: '' },
  error: { type: String, default: '' },
  hint: { type: String, default: '' },
  required: { type: Boolean, default: false },
})

const fieldId = useId()
const errorId = computed(() => `${fieldId}-error`)
const hintId = computed(() => `${fieldId}-hint`)
const describedBy = computed(() => {
  const parts = []
  if (props.hint) parts.push(hintId.value)
  if (props.error) parts.push(errorId.value)
  return parts.join(' ') || undefined
})
</script>

<template>
  <div>
    <label v-if="label" :for="fieldId" class="block text-xs text-slate-400 mb-1.5">
      {{ label }}<span v-if="required" class="text-red-400 ml-0.5">*</span>
    </label>
    <slot :id="fieldId" :error-id="errorId" :hint-id="hintId" :described-by="describedBy" />
    <p v-if="hint && !error" :id="hintId" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
    <p v-if="error" :id="errorId" class="mt-1 text-xs text-red-400" role="alert">{{ error }}</p>
  </div>
</template>
