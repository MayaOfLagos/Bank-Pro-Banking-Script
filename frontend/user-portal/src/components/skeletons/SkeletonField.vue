<script setup>
/*
 * Stands in for a <FormField> plus its control.
 *
 * FormField's label is 0.75rem/600 uppercase with a 0.5rem bottom margin; the
 * portal `.input` treatment is padding 0.85rem 1rem at 0.95rem which, with the
 * 1.5 line-height inputs inherit from Tailwind's preflight plus a 1px border,
 * measures 3.25rem. Pass :height for taller controls (textarea, amount field)
 * or a wider :label for long captions.
 *
 * Views that hand-roll their own label instead of using FormField (Withdrawals)
 * can retune the caption with :label-size and :gap.
 */
import SkeletonText from './SkeletonText.vue'

defineProps({
  label: { type: String, default: '6rem' },
  labelSize: { type: String, default: '0.75rem' },
  gap: { type: String, default: '0.5rem' },
  height: { type: String, default: '3.25rem' },
  radius: { type: String, default: 'var(--radius-md)' },
  // FormField's optional hint sits below the control at 0.72rem with a
  // 0.35rem top margin; omitting it here would leave the form short.
  hint: { type: String, default: '' },
  hintGap: { type: String, default: '0.35rem' },
})
</script>

<template>
  <div class="sk-field" aria-hidden="true">
    <SkeletonText
      class="label"
      :size="labelSize"
      :line-height="1.2"
      :width="label"
      :style="{ marginBottom: gap }"
    />
    <div class="skeleton control" :style="{ height, borderRadius: radius }" />
    <SkeletonText v-if="hint" size="0.72rem" :width="hint" :style="{ marginTop: hintGap }" />
  </div>
</template>

<style scoped>
.sk-field {
  min-width: 0;
}
.control {
  width: 100%;
}
</style>
