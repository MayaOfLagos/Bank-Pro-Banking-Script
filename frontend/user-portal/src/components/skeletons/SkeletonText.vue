<script setup>
import { computed } from 'vue'

/*
 * A line (or paragraph) of placeholder copy.
 *
 * The wrapper keeps the *line box* the real text would occupy (font-size x
 * line-height) while the bar inside is sized to the glyph mass, so swapping in
 * real text moves nothing. Getting this right is the whole reason the skeletons
 * don't jitter on load.
 */
const props = defineProps({
  // Font size of the text being stood in for, as a CSS length.
  size: { type: String, default: '0.9rem' },
  // 1.5 is what unstyled text in this app inherits (Tailwind preflight sets it
  // on <html>); pass the real value for anything that overrides line-height.
  lineHeight: { type: Number, default: 1.5 },
  lines: { type: Number, default: 1 },
  // Width of each bar; a single value applies to all lines, an array applies
  // per line so paragraphs get the ragged last line real copy has.
  width: { type: [String, Array], default: '100%' },
  gap: { type: String, default: '0.45rem' },
})

const toRem = (v) => {
  const n = parseFloat(v)
  return Number.isFinite(n) ? n : 1
}

const lineBox = computed(() => `${(toRem(props.size) * props.lineHeight).toFixed(3)}rem`)
// 0.68 of the font size tracks cap-height plus a little descender, which is
// roughly the ink a line of text actually lays down.
const barHeight = computed(() => `${Math.max(toRem(props.size) * 0.68, 0.45).toFixed(3)}rem`)

const widths = computed(() =>
  Array.from({ length: props.lines }, (_, i) =>
    Array.isArray(props.width) ? props.width[i] ?? props.width[props.width.length - 1] : props.width,
  ),
)
</script>

<template>
  <div class="sk-text" :style="{ gap }" aria-hidden="true">
    <div
      v-for="(w, i) in widths"
      :key="i"
      class="sk-text__line"
      :style="{ height: lineBox }"
    >
      <div class="skeleton skeleton--text" :style="{ height: barHeight, width: w }" />
    </div>
  </div>
</template>

<style scoped>
.sk-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
}
.sk-text__line {
  display: flex;
  align-items: center;
  min-width: 0;
}
</style>
