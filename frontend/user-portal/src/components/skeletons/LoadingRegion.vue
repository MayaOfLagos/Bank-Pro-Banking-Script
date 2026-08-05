<script setup>
/*
 * The single accessible announcement for a loading screen.
 *
 * Everything inside is aria-hidden (the Skeleton* components enforce that
 * themselves), so assistive tech hears one polite "Loading transactions…"
 * instead of walking dozens of empty boxes.
 *
 * It is a flex column with the same default gap as the `.content` wrapper every
 * page uses, so dropping it in place of a page's real children reproduces the
 * same vertical rhythm. Override with --sk-region-gap where a page differs.
 */
defineProps({
  // What is loading, e.g. "transactions". Kept short — it is read aloud.
  label: { type: String, default: 'content' },
})
</script>

<template>
  <div class="sk-region" role="status" aria-busy="true" aria-live="polite">
    <span class="sr-only">Loading {{ label }}…</span>
    <slot />
  </div>
</template>

<style scoped>
.sk-region {
  display: flex;
  flex-direction: column;
  gap: var(--sk-region-gap, var(--space-4));
  min-width: 0;
}
</style>
