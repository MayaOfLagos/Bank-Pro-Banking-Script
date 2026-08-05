<script setup>
import { computed } from 'vue'

/**
 * Real Visa / Mastercard marks. The Visa wordmark's outline comes from the
 * Font Awesome Free brands font already vendored at front/fonts/fa-brands-400.svg
 * (CC BY 4.0); the Mastercard symbol is two discs and their overlap, drawn from
 * geometry. The networks' trademarks remain theirs — this only identifies the
 * card, which is the one thing the platform issues.
 *
 * Both marks share one 277-unit-tall viewBox, so `height` alone keeps them at
 * the relative sizes they have on a physical card. Callers set `font-size`.
 */
const props = defineProps({
  network: { type: String, default: '' },
})

const kind = computed(() => {
  const name = String(props.network || '').toUpperCase().replace(/[^A-Z]/g, '')
  if (name === 'VISA') return 'visa'
  if (name === 'MASTERCARD' || name === 'MASTER' || name === 'MC') return 'mastercard'
  return ''
})

const label = computed(() => (kind.value === 'visa' ? 'Visa' : 'Mastercard'))

// Legacy rows can name a network the platform never issued. Falling back to
// the bare text keeps the card header the same shape instead of collapsing it.
const fallback = computed(() => String(props.network || '').toUpperCase())
</script>

<template>
  <svg
    v-if="kind === 'visa'"
    class="mark mark--visa"
    viewBox="32 114.85 496 277"
    role="img"
    :aria-label="label"
  >
    <g transform="translate(0,448) scale(1,-1)">
      <path fill="currentColor" fill-rule="evenodd" d="M152.5 116.8l63.2002 155.2h-42.5l-39.2998 -106l-4.30078 21.5l-14 71.4004c-2.2998 9.89941 -9.39941 12.6992 -18.1992 13.0996h-64.7002l-0.700195 -3.09961c15.7998 -4 29.9004 -9.80078 42.2002 -17.1006l35.7998 -135h42.5z M246.9 116.6
l25.1992 155.4h-40.1992l-25.1006 -155.4h40.1006z M386.8 167.4c0.200195 17.6992 -10.5996 31.1992 -33.7002 42.2998c-14.0996 7.09961 -22.6992 11.8994 -22.6992 19.2002c0.199219 6.59961 7.2998 13.3994 23.0996 13.3994
c13.0996 0.299805 22.7002 -2.7998 29.9004 -5.89941l3.59961 -1.7002l5.5 33.5996c-7.90039 3.10059 -20.5 6.60059 -36 6.60059c-39.7002 0 -67.5996 -21.2002 -67.7998 -51.4004c-0.299805 -22.2998 20 -34.7002 35.2002 -42.2002
c15.5 -7.59961 20.7998 -12.5996 20.7998 -19.2998c-0.200195 -10.4004 -12.6006 -15.2002 -24.1006 -15.2002c-16 0 -24.5996 2.5 -37.6992 8.2998l-5.30078 2.5l-5.59961 -34.8994c9.40039 -4.2998 26.7998 -8.10059 44.7998 -8.2998
c42.2002 -0.100586 69.7002 20.7998 70 53z M528 116.6l-32.4004 155.4h-31.0996c-9.59961 0 -16.9004 -2.7998 -21 -12.9004l-59.7002 -142.5h42.2002s6.90039 19.2002 8.40039 23.3008h51.5996c1.2002 -5.5 4.7998 -23.3008 4.7998 -23.3008h37.2002z M470.1 216.7c0 0 7.60059 -37.2002 9.30078 -45h-33.4004c3.2998 8.89941 16 43.5 16 43.5c-0.200195 -0.299805 3.2998 9.09961 5.2998 14.8994z" />
    </g>
  </svg>

  <svg
    v-else-if="kind === 'mastercard'"
    class="mark mark--mastercard"
    viewBox="0 0 448 277"
    role="img"
    :aria-label="label"
  >
    <circle cx="138.5" cy="138.5" r="138.5" fill="#eb001b" />
    <circle cx="309.5" cy="138.5" r="138.5" fill="#f79e1b" />
    <path d="M224 29.54A138.5 138.5 0 0 1 224 247.46A138.5 138.5 0 0 1 224 29.54Z" fill="#ff5f00" />
  </svg>

  <span v-else class="mark-fallback">{{ fallback }}</span>
</template>

<style scoped>
.mark {
  display: block;
  height: 1em;
  width: auto;
  flex-shrink: 0;
}
.mark-fallback {
  font-size: 0.75em;
  font-weight: 800;
  letter-spacing: 0.05em;
}
</style>
