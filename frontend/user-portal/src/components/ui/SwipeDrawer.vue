<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'

/**
 * Drag-to-dismiss sheet. `side` picks both the edge it slides from and the
 * gesture that closes it: 'bottom' closes on a downward swipe, 'top' on an
 * upward one, 'right' on a rightward one. Written by hand because the project
 * ships no drawer dependency and cPanel has no build step to add one lightly.
 */
const props = defineProps({
  open: { type: Boolean, default: false },
  side: {
    type: String,
    default: 'bottom',
    validator: (v) => v === 'bottom' || v === 'top' || v === 'right',
  },
  title: { type: String, default: '' },
  // Fraction of the sheet's own size the user must drag past to dismiss.
  closeThreshold: { type: Number, default: 0.4 },
})

const emit = defineEmits(['close'])

const panel = ref(null)
const titleId = `drawer-title-${Math.random().toString(36).slice(2, 9)}`

// Live drag offset in px along the dismiss axis. Always >= 0: negative
// values mean the user is pulling the sheet further open, which we damp
// rather than allow, so the sheet never detaches from its edge.
const dragOffset = ref(0)
const dragging = ref(false)

let pointerId = null
let startX = 0
let startY = 0
let startTarget = null
let axisLocked = null   // 'dismiss' | 'scroll' — set on first decisive move
let startedAt = 0
let scrollLockY = 0
// A drag that ends outside the panel makes the browser dispatch the click to
// their common ancestor, the scrim — so releasing a snapped-back sheet would
// dismiss it anyway. Most visible on a top sheet, whose non-dismissing
// direction points off the panel and onto the scrim.
let suppressScrimClick = false

const isVertical = computed(() => props.side !== 'right')

// Which way along the axis counts as "toward dismissal". A top sheet is the
// only side that dismisses on a negative delta, so the raw gesture is
// normalised through this and every threshold below stays sign-agnostic.
const dismissSign = computed(() => (props.side === 'top' ? -1 : 1))

const transitionName = computed(
  () => ({ bottom: 'drawer-up', top: 'drawer-down', right: 'drawer-in' })[props.side],
)

const panelStyle = computed(() => {
  if (!dragOffset.value) return {}
  const axis = isVertical.value ? 'Y' : 'X'
  return {
    transform: `translate${axis}(${dragOffset.value * dismissSign.value}px)`,
    // Following the finger must not be animated or it lags behind it.
    transition: 'none',
  }
})

// Scrim fades out as the sheet is pulled away, which is the main cue that
// the gesture is going to dismiss rather than snap back.
const scrimStyle = computed(() => {
  if (!dragging.value || !dragOffset.value) return {}
  const extent = panelExtent()
  if (!extent) return {}
  const progress = Math.min(1, dragOffset.value / extent)
  return { opacity: String(1 - progress * 0.85) }
})

function panelExtent() {
  const el = panel.value
  if (!el) return 0
  return isVertical.value ? el.offsetHeight : el.offsetWidth
}

/**
 * A drag that starts inside a scrollable region should scroll it, not drag
 * the sheet — unless that region is already at the edge the drag pulls
 * away from. Mirrors how native sheets hand off between the two.
 *
 * Answered from where the gesture *started*, not where the pointer is now,
 * which by lock time may already have left the panel.
 *
 * Takes the raw (un-normalised) delta: the handoff depends on which way the
 * content can still travel, not on which way dismisses this particular side.
 */
function scrollableBlocksDrag(delta) {
  let node = startTarget
  while (node && node !== panel.value) {
    if (node.nodeType === 1) {
      const style = window.getComputedStyle(node)
      const scrolls = /(auto|scroll|overlay)/.test(
        isVertical.value ? style.overflowY : style.overflowX,
      )
      if (scrolls) {
        const pos = isVertical.value ? node.scrollTop : node.scrollLeft
        const size = isVertical.value ? node.clientHeight : node.clientWidth
        const total = isVertical.value ? node.scrollHeight : node.scrollWidth
        if (total > size + 1) {
          // Dragging toward dismissal only takes over once the content is
          // pinned at its start edge.
          if (delta > 0 && pos > 0) return true
          if (delta < 0 && pos < total - size - 1) return true
        }
      }
    }
    node = node.parentNode
  }
  return false
}

// Only a press that lands on the panel starts a gesture, but the rest of it is
// tracked on the scrim: a top sheet leaves the panel on its very first
// non-dismissing move, and panel-bound listeners would simply stop firing.
function onPointerDown(event) {
  if (pointerId !== null) return
  if (event.pointerType === 'mouse' && event.button !== 0) return
  suppressScrimClick = false
  pointerId = event.pointerId
  startX = event.clientX
  startY = event.clientY
  startTarget = event.target
  startedAt = event.timeStamp
  axisLocked = null
  dragOffset.value = 0
}

function onPointerMove(event) {
  if (event.pointerId !== pointerId) return

  const dx = event.clientX - startX
  const dy = event.clientY - startY
  const rawAlong = isVertical.value ? dy : dx
  const along = rawAlong * dismissSign.value
  const across = isVertical.value ? dx : dy

  if (axisLocked === null) {
    // Wait for a decisive movement before committing, so a tap or a small
    // jitter never nudges the sheet.
    if (Math.abs(along) < 6 && Math.abs(across) < 6) return
    if (Math.abs(across) > Math.abs(along)) {
      axisLocked = 'scroll'
      return
    }
    if (scrollableBlocksDrag(rawAlong)) {
      axisLocked = 'scroll'
      return
    }
    axisLocked = 'dismiss'
    dragging.value = true
    panel.value?.setPointerCapture?.(pointerId)
  }

  if (axisLocked !== 'dismiss') return

  // Pulling past the open position is rubber-banded rather than tracked 1:1.
  dragOffset.value = along >= 0 ? along : along / 4
  event.preventDefault()
}

function onPointerUp(event) {
  if (event.pointerId !== pointerId) return
  const wasDragging = axisLocked === 'dismiss'
  const offset = dragOffset.value
  const elapsed = Math.max(1, event.timeStamp - startedAt)

  releasePointer()

  if (!wasDragging) return
  suppressScrimClick = true

  const extent = panelExtent()
  const velocity = offset / elapsed          // px per ms
  // A short fast flick dismisses even when it never crossed the distance
  // threshold — matching the feel of native sheets.
  const flicked = velocity > 0.5 && offset > 24
  const dragged = extent > 0 && offset > extent * props.closeThreshold

  if (flicked || dragged) {
    emit('close')
    // Leave the offset in place so the closing transition continues from
    // where the finger let go instead of snapping back first.
    return
  }
  dragOffset.value = 0
}

function onPointerCancel(event) {
  if (event.pointerId !== pointerId) return
  releasePointer()
  dragOffset.value = 0
}

function releasePointer() {
  if (pointerId !== null) {
    panel.value?.releasePointerCapture?.(pointerId)
  }
  pointerId = null
  axisLocked = null
  startTarget = null
  dragging.value = false
}

function onScrimClick() {
  if (suppressScrimClick) {
    suppressScrimClick = false
    return
  }
  emit('close')
}

function onKeydown(event) {
  if (event.key === 'Escape') {
    event.stopPropagation()
    emit('close')
  }
}

// iOS ignores overflow:hidden on <body> for touch scrolling, so the page is
// pinned by position:fixed and restored to the same offset on close.
function lockScroll() {
  scrollLockY = window.scrollY
  const body = document.body
  body.style.position = 'fixed'
  body.style.top = `-${scrollLockY}px`
  body.style.left = '0'
  body.style.right = '0'
  body.style.width = '100%'
}

function unlockScroll() {
  const body = document.body
  body.style.position = ''
  body.style.top = ''
  body.style.left = ''
  body.style.right = ''
  body.style.width = ''
  window.scrollTo(0, scrollLockY)
}

watch(
  () => props.open,
  async (open) => {
    if (open) {
      dragOffset.value = 0
      suppressScrimClick = false
      lockScroll()
      await nextTick()
      panel.value?.focus?.()
    } else {
      releasePointer()
      unlockScroll()
      dragOffset.value = 0
    }
  },
)

onUnmounted(() => {
  if (props.open) unlockScroll()
})
</script>

<template>
  <Teleport to="body">
    <transition :name="transitionName">
      <div
        v-if="open"
        class="scrim"
        :class="`scrim--${side}`"
        @click.self="onScrimClick"
        @keydown="onKeydown"
        @pointermove="onPointerMove"
        @pointerup="onPointerUp"
        @pointercancel="onPointerCancel"
      >
        <div
          ref="panel"
          class="panel"
          :class="[`panel--${side}`, { 'panel--dragging': dragging }]"
          :style="panelStyle"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="title ? titleId : undefined"
          tabindex="-1"
          @pointerdown="onPointerDown"
        >
          <div class="handle-zone" aria-hidden="true">
            <span class="handle" />
          </div>

          <h2 v-if="title" :id="titleId" class="panel-title">{{ title }}</h2>

          <div class="panel-body">
            <slot :close="() => emit('close')" />
          </div>
        </div>

        <div class="scrim-tint" :style="scrimStyle" />
      </div>
    </transition>
  </Teleport>
</template>

<style scoped>
.scrim {
  position: fixed;
  inset: 0;
  z-index: 60;
  display: flex;
  overscroll-behavior: contain;
}
.scrim--bottom { align-items: flex-end; justify-content: center; }
.scrim--top { align-items: flex-start; justify-content: center; }
.scrim--right { align-items: stretch; justify-content: flex-end; }

/* Painted behind the panel so dragging can fade it independently, which a
   background on .scrim itself could not do without fading the panel too. */
.scrim-tint {
  position: absolute;
  inset: 0;
  z-index: -1;
  background: color-mix(in srgb, #000 55%, transparent);
  pointer-events: none;
}

.panel {
  position: relative;
  background: var(--surface);
  color: var(--text-primary);
  display: flex;
  flex-direction: column;
  outline: none;
  touch-action: none;
  transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}
.panel--dragging { transition: none; }

.panel--bottom {
  width: 100%;
  max-width: 30rem;
  max-height: min(85vh, 40rem);
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  padding-bottom: calc(var(--space-5) + env(safe-area-inset-bottom));
  box-shadow: 0 -20px 60px -20px rgba(0, 0, 0, 0.45);
}
.panel--top {
  width: 100%;
  max-width: 30rem;
  max-height: min(85vh, 40rem);
  border-radius: 0 0 var(--radius-lg) var(--radius-lg);
  padding-top: calc(var(--space-2) + env(safe-area-inset-top));
  box-shadow: 0 20px 60px -20px rgba(0, 0, 0, 0.45);
}
.panel--right {
  width: min(20rem, 86vw);
  height: 100%;
  border-radius: var(--radius-lg) 0 0 var(--radius-lg);
  padding-bottom: env(safe-area-inset-bottom);
  box-shadow: -20px 0 60px -20px rgba(0, 0, 0, 0.45);
}

/* Generous hit area around the small visual handle — the pill itself is
   well under the 44px minimum touch target. */
.handle-zone {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}
.panel--bottom .handle-zone { padding: 0.75rem 0 0.35rem; }
/* The grab edge of a top sheet is its bottom rim, so the handle is reordered
   below the content rather than moved with absolute positioning, which would
   overlap the last row of the body. */
.panel--top .handle-zone {
  order: 3;
  padding: 0.35rem 0 0.75rem;
}
.panel--right .handle-zone {
  position: absolute;
  left: 0;
  top: 0;
  bottom: 0;
  width: 1.75rem;
}

.handle {
  display: block;
  background: var(--text-muted);
  border-radius: var(--radius-pill);
  opacity: 0.4;
}
.panel--bottom .handle,
.panel--top .handle { width: 2.5rem; height: 0.3rem; }
.panel--right .handle { width: 0.3rem; height: 2.5rem; }

.panel-title {
  flex-shrink: 0;
  margin: 0;
  font-size: 1.05rem;
  font-weight: 800;
  line-height: 1.2;
}
.panel--bottom .panel-title { padding: 0 var(--space-4) var(--space-1); }
.panel--top .panel-title { padding: var(--space-3) var(--space-4) var(--space-1); }
.panel--right .panel-title { padding: var(--space-4) var(--space-4) var(--space-1) 2rem; }

.panel-body {
  flex: 1 1 auto;
  min-height: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  /* Children scroll and receive taps normally; only the panel shell owns
     the dismiss gesture. */
  touch-action: pan-y;
}
.panel--bottom .panel-body { padding: var(--space-3) var(--space-4) 0; }
.panel--top .panel-body { padding: var(--space-3) var(--space-4) 0; }
.panel--right .panel-body { padding: var(--space-2) var(--space-4) var(--space-4) 2rem; }

.drawer-up-enter-active,
.drawer-up-leave-active,
.drawer-down-enter-active,
.drawer-down-leave-active,
.drawer-in-enter-active,
.drawer-in-leave-active {
  transition: opacity 0.24s ease;
}
.drawer-up-enter-active .panel,
.drawer-up-leave-active .panel,
.drawer-down-enter-active .panel,
.drawer-down-leave-active .panel,
.drawer-in-enter-active .panel,
.drawer-in-leave-active .panel {
  transition: transform 0.28s cubic-bezier(0.22, 1, 0.36, 1);
}
.drawer-up-enter-from,
.drawer-up-leave-to,
.drawer-down-enter-from,
.drawer-down-leave-to,
.drawer-in-enter-from,
.drawer-in-leave-to {
  opacity: 0;
}
.drawer-up-enter-from .panel,
.drawer-up-leave-to .panel {
  transform: translateY(100%);
}
.drawer-down-enter-from .panel,
.drawer-down-leave-to .panel {
  transform: translateY(-100%);
}
.drawer-in-enter-from .panel,
.drawer-in-leave-to .panel {
  transform: translateX(100%);
}

@media (prefers-reduced-motion: reduce) {
  .panel,
  .drawer-up-enter-active .panel,
  .drawer-up-leave-active .panel,
  .drawer-down-enter-active .panel,
  .drawer-down-leave-active .panel,
  .drawer-in-enter-active .panel,
  .drawer-in-leave-active .panel {
    transition: none;
  }
  .drawer-up-enter-from .panel,
  .drawer-up-leave-to .panel,
  .drawer-down-enter-from .panel,
  .drawer-down-leave-to .panel,
  .drawer-in-enter-from .panel,
  .drawer-in-leave-to .panel {
    transform: none;
  }
}
</style>
