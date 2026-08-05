<script setup>
/*
 * Stands in for the admin-authored `.legal-body` on /terms and /privacy.
 *
 * The copy is a CMS textarea, so its true length is unknowable — this draws a
 * plausible three-section article (h2 + paragraphs) at the same 0.95rem/1.55
 * metrics the real body uses.
 *
 * Three sections rather than two because a published policy is nearly always
 * long enough to hit `.legal-body`'s `max-height: 60vh` cap and scroll, so the
 * real container is usually *at* the cap. The wrapper below inherits the same
 * cap and clips, which means the placeholder can be short but never overshoot
 * — undershooting settles the card upward, overshooting would push the whole
 * auth card past the fold.
 */
import SkeletonText from './SkeletonText.vue'

// Ragged last lines so each block reads as a paragraph, not a table.
const PARAGRAPHS = [
  ['100%', '100%', '96%', '54%'],
  ['100%', '92%', '100%', '38%'],
  ['100%', '97%', '88%', '61%'],
]
const HEADINGS = ['11rem', '8.5rem', '9.75rem']
</script>

<template>
  <div class="sk-legal" aria-hidden="true">
    <template v-for="(lines, i) in PARAGRAPHS" :key="i">
      <SkeletonText class="heading" size="1.1rem" :line-height="1.4" :width="HEADINGS[i]" />
      <SkeletonText
        class="para"
        size="0.95rem"
        :line-height="1.55"
        :lines="lines.length"
        :width="lines"
        gap="0"
      />
    </template>
  </div>
</template>

<style scoped>
.sk-legal {
  /* Same cap and gutter as `.legal-body` on /terms and /privacy, so the
     placeholder can never be taller than the box it stands in for. */
  max-height: 60vh;
  overflow: hidden;
  padding-right: 0.25rem;
  color: var(--text-primary);
}
/* Mirrors `.legal-body :deep(h2)`: 1.1rem with 1.25rem/0.5rem margins. The
   first heading loses its top margin because the real body's first child
   collapses against the container. */
.heading {
  margin: 1.25rem 0 0.5rem;
}
.heading:first-child {
  margin-top: 0;
}
/* `.legal-body :deep(p)` carries a 0.75rem bottom margin and no line gap of
   its own, hence gap="0" above. */
.para {
  margin-bottom: 0.75rem;
}
</style>
