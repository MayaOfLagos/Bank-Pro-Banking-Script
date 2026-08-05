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
    <label v-if="label" :for="fieldId" class="label">
      {{ label }}<span v-if="required" class="required" aria-hidden="true">*</span>
    </label>
    <slot :id="fieldId" :error-id="errorId" :hint-id="hintId" :described-by="describedBy" />
    <p v-if="hint && !error" :id="hintId" class="hint">{{ hint }}</p>
    <p v-if="error" :id="errorId" class="error" role="alert">{{ error }}</p>
  </div>
</template>

<style scoped>
/* Matches the .auth-field-label treatment in style.css so labels are identical
   whether the field is rendered inside the auth card or a portal form card.
   Previously used fixed slate-400/red-400, which under-contrasted on the light
   --surface these forms actually sit on. */
.label {
  display: block;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin-bottom: 0.5rem;
}
.required {
  color: var(--danger-fg);
  margin-left: 0.15rem;
}
.hint {
  margin: 0.35rem 0 0;
  font-size: 0.72rem;
  color: var(--text-muted);
}
.error {
  margin: 0.35rem 0 0;
  font-size: 0.72rem;
  color: var(--danger-fg);
}
</style>
