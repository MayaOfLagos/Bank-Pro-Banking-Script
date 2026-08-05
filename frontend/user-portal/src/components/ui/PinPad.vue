<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue'
import { BackspaceIcon } from '@heroicons/vue/24/outline'

/**
 * Shuffled PIN pad.
 *
 * Modelled on the legacy .legacy-reference/pin.php pad, which re-ran its
 * jQuery `.shuffle()` on every keypress. The digit keys are therefore
 * re-ordered on mount, after each accepted digit, and whenever the value is
 * cleared — so the physical position of a digit is never stable, defeating
 * shoulder-surfers memorising a finger pattern and positional keyloggers that
 * only capture tap coordinates.
 *
 * Two deliberate departures from the legacy pad:
 *  - it replaced the DOM nodes, so the keys visibly jumped; here the 3x4 grid
 *    is fixed and only the labels swap, which is the same security property
 *    without the layout thrash;
 *  - it blocked the keyboard outright (`keypress` -> preventDefault), locking
 *    out keyboard-only users. Physical digit keys work here instead.
 *
 * The shuffle is a presentation-layer concern only: the value emitted through
 * `v-model` is the plain PIN string, so callers keep whatever request contract
 * they already had.
 *
 * Accessibility is deliberately *not* traded away for the shuffle:
 *  - every key is a real <button> in a labelled role="group", so tab order,
 *    Enter/Space activation and screen-reader semantics come for free;
 *  - each key carries an explicit aria-label naming its digit;
 *  - physical number keys / Backspace / Escape work as an escape hatch for
 *    keyboard-only and switch-device users;
 *  - the masked display is a polite live region that announces *how many*
 *    digits are entered, never the digits themselves.
 *
 * The grid is a fixed 3x4 so re-shuffling swaps labels without ever moving,
 * resizing or reflowing a key.
 */

const props = defineProps({
  modelValue: { type: String, default: '' },
  length: { type: Number, default: 4 },
  disabled: { type: Boolean, default: false },
  label: { type: String, default: 'PIN entry keypad' },
})

const emit = defineEmits(['update:modelValue', 'complete'])

const DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']

const order = ref([...DIGITS])

/** Fisher-Yates, seeded from crypto when available so the order is not guessable. */
function shuffled(source) {
  const items = [...source]
  const random = randomInts(items.length)
  for (let i = items.length - 1; i > 0; i -= 1) {
    const j = random[i] % (i + 1)
    ;[items[i], items[j]] = [items[j], items[i]]
  }
  return items
}

function randomInts(count) {
  const crypto = typeof window !== 'undefined' ? window.crypto : undefined
  if (crypto?.getRandomValues) {
    return crypto.getRandomValues(new Uint32Array(count))
  }
  return Array.from({ length: count }, () => Math.floor(Math.random() * 0xffffffff))
}

function reshuffle() {
  order.value = shuffled(DIGITS)
}

// Rows 1-3 hold nine digits; the tenth sits in the bottom-centre cell between
// the clear and backspace keys. Always 12 cells, so nothing shifts on reshuffle.
const gridDigits = computed(() => order.value.slice(0, 9))
const lastDigit = computed(() => order.value[9])

const value = computed(() => props.modelValue ?? '')
const filled = computed(() => Math.min(value.value.length, props.length))
const isFull = computed(() => value.value.length >= props.length)
const cells = computed(() => Array.from({ length: props.length }, (_, i) => i < filled.value))

const statusText = computed(() =>
  filled.value === 0
    ? `No digits entered. ${props.length} required.`
    : `${filled.value} of ${props.length} digits entered.`
)

function append(digit) {
  if (props.disabled || isFull.value) return
  const next = `${value.value}${digit}`
  emit('update:modelValue', next)
  // Re-shuffle after every accepted digit, matching the legacy pad. This is the
  // property that makes the pad worth having: an observer who watches the whole
  // entry sees four taps whose positions imply nothing about the digits.
  reshuffle()
  if (next.length >= props.length) emit('complete', next)
}

function backspace() {
  if (props.disabled || !value.value.length) return
  emit('update:modelValue', value.value.slice(0, -1))
}

function clear() {
  if (props.disabled || !value.value.length) return
  emit('update:modelValue', '')
}

function onKeydown(event) {
  if (props.disabled) return
  // Never swallow keys meant for a real text field elsewhere on the page.
  const target = event.target
  const tag = target?.tagName
  if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || target?.isContentEditable) return
  if (event.metaKey || event.ctrlKey || event.altKey) return
  if (event.key >= '0' && event.key <= '9') {
    event.preventDefault()
    append(event.key)
  } else if (event.key === 'Backspace') {
    event.preventDefault()
    backspace()
  } else if (event.key === 'Escape' || event.key === 'Delete') {
    event.preventDefault()
    clear()
  }
}

// Re-shuffle whenever the field is emptied (e.g. after a rejected attempt) so a
// second guess never reuses the first layout.
watch(
  () => props.modelValue,
  (next, previous) => {
    if (!next && previous) reshuffle()
  }
)

onMounted(() => {
  reshuffle()
  window.addEventListener('keydown', onKeydown)
})

onBeforeUnmount(() => {
  window.removeEventListener('keydown', onKeydown)
})

defineExpose({ reshuffle, clear })
</script>

<template>
  <div class="pinpad">
    <!-- Masked value -->
    <!-- Purely decorative: the live region below is the single source of
         truth for assistive tech, so the dots must not double-announce. -->
    <div class="dots" aria-hidden="true">
      <span
        v-for="(isFilledCell, index) in cells"
        :key="index"
        class="dot"
        :class="{ 'dot--filled': isFilledCell }"
      />
    </div>
    <p class="sr-only" role="status" aria-live="polite">{{ statusText }}</p>

    <!-- Keypad -->
    <div class="grid" role="group" :aria-label="label">
      <button
        v-for="digit in gridDigits"
        :key="digit"
        type="button"
        class="key"
        :disabled="disabled || isFull"
        :aria-label="`Enter digit ${digit}`"
        @click="append(digit)"
      >
        <span aria-hidden="true">{{ digit }}</span>
      </button>

      <button
        type="button"
        class="key key--action"
        :disabled="disabled || !value.length"
        aria-label="Clear all entered digits"
        @click="clear"
      >
        <span aria-hidden="true">Clear</span>
      </button>

      <button
        type="button"
        class="key"
        :disabled="disabled || isFull"
        :aria-label="`Enter digit ${lastDigit}`"
        @click="append(lastDigit)"
      >
        <span aria-hidden="true">{{ lastDigit }}</span>
      </button>

      <button
        type="button"
        class="key key--action"
        :disabled="disabled || !value.length"
        aria-label="Delete last digit"
        @click="backspace"
      >
        <BackspaceIcon class="key-icon" aria-hidden="true" />
      </button>
    </div>

    <p class="hint">Key positions change every time for your security.</p>
  </div>
</template>

<style scoped>
.pinpad {
  display: flex;
  flex-direction: column;
  gap: var(--space-5);
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border: 0;
}

/* Masked display */
.dots {
  display: flex;
  justify-content: center;
  align-items: center;
  gap: var(--space-3);
  min-height: 2.75rem;
}
.dot {
  width: 0.95rem;
  height: 0.95rem;
  border-radius: var(--radius-pill);
  background: transparent;
  border: 2px solid var(--border);
  transition: background-color 0.15s ease, border-color 0.15s ease, transform 0.15s ease;
}
.dot--filled {
  background: var(--accent);
  border-color: var(--accent);
  transform: scale(1.12);
}

/* Fixed 3x4 grid — reshuffling only swaps labels, never geometry. */
.grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-3);
}
.key {
  min-height: 3.5rem; /* comfortable touch target */
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
  background: var(--surface-muted);
  color: var(--text-primary);
  font-family: inherit;
  font-size: 1.4rem;
  font-weight: 700;
  letter-spacing: -0.01em;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  user-select: none;
  -webkit-tap-highlight-color: transparent;
  transition: background-color 0.12s ease, transform 0.08s ease, border-color 0.12s ease;
}
.key:hover:not(:disabled) {
  background: color-mix(in srgb, var(--accent) 10%, var(--surface-muted));
  border-color: color-mix(in srgb, var(--accent) 35%, var(--border));
}
.key:active:not(:disabled) {
  transform: scale(0.96);
  background: var(--accent-soft);
}
.key:focus-visible {
  outline: none;
  border-color: var(--accent);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 25%, transparent);
}
.key:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.key--action {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--text-secondary);
  background: transparent;
  border-color: transparent;
}
.key--action:hover:not(:disabled) {
  background: color-mix(in srgb, var(--text-primary) 6%, transparent);
  border-color: var(--border);
}
.key-icon {
  width: 1.35rem;
  height: 1.35rem;
}

.hint {
  margin: 0;
  text-align: center;
  font-size: 0.72rem;
  color: var(--text-muted);
}

@media (prefers-reduced-motion: reduce) {
  .dot,
  .key {
    transition: none;
  }
  .dot--filled {
    transform: none;
  }
}
</style>
