<script setup>
import { computed, nextTick, onBeforeUnmount, ref, shallowRef, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { BellSlashIcon, ChevronRightIcon } from '@heroicons/vue/24/solid'
import SwipeDrawer from '../ui/SwipeDrawer.vue'
import EmptyState from '../ui/EmptyState.vue'
import ErrorState from '../ui/ErrorState.vue'
import { useNotificationsStore, formatNotificationTime } from '../../stores/notifications'

/**
 * Top sheet hung off the header bell. It shows only the newest slice — the
 * full history lives at /notifications — so this stays a glanceable peek
 * rather than a second list view with its own paging.
 */
const store = useNotificationsStore()
const router = useRouter()
const route = useRoute()

const contentRef = ref(null)
// Restored on close so a keyboard user is put back on the bell rather than
// at the top of the document.
let previouslyFocused = null

const notifications = computed(() => store.items.slice(0, 10))
const showEmpty = computed(
  () => !store.loading && !store.error && store.loadedOnce && notifications.value.length === 0,
)

/**
 * marked + DOMPurify are ~27 kB gzipped and nothing on the dashboard needs
 * them until a notification body is actually on screen, so the module is
 * fetched on first open instead of riding along in the dashboard chunk.
 *
 * Until it lands the template falls back to plain interpolation — the drawer
 * fires its feed request at the same moment, so the round trip almost always
 * settles last and the fallback is never seen. It renders the markdown source
 * as text, which is unstyled but never a second HTML sink.
 */
const markdown = shallowRef(null)

function loadMarkdown() {
  if (markdown.value) return
  import('../../utils/markdown')
    .then((mod) => { markdown.value = mod })
    .catch(() => {})
}

function bodyHtml(item) {
  return markdown.value ? markdown.value.renderMarkdown(item.body) : ''
}

function timeLabel(item) {
  return formatNotificationTime(item.createdAt, item.createdTs)
}

async function onSelect(item) {
  if (!item.read) store.markRead(item.id)
  if (item.link) {
    store.closeDrawer()
    router.push(item.link).catch(() => {})
  }
}

function seeAll() {
  store.closeDrawer()
  router.push('/notifications').catch(() => {})
}

// --- Focus management ------------------------------------------------------
// SwipeDrawer owns Escape, the scrim click and the initial panel focus, but
// it does not trap Tab. Trapping here keeps that shared component untouched
// while still meeting the dialog contract for this drawer.
const FOCUSABLE =
  'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]),' +
  ' textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'

function panelEl() {
  return contentRef.value?.closest('[role="dialog"]') || null
}

function focusables(panel) {
  return Array.from(panel.querySelectorAll(FOCUSABLE)).filter(
    (el) => el.offsetParent !== null || el === document.activeElement,
  )
}

function onKeydown(event) {
  // SwipeDrawer listens for Escape on the scrim, which only fires while focus
  // is inside it. Disabling the focused control (which "Mark all read" does to
  // itself) drops focus to <body>, and from there the key never reaches the
  // scrim — so Escape is handled here too rather than becoming intermittent.
  if (event.key === 'Escape') {
    event.stopPropagation()
    store.closeDrawer()
    return
  }
  if (event.key !== 'Tab') return
  const panel = panelEl()
  if (!panel) return

  const list = focusables(panel)
  if (!list.length) {
    event.preventDefault()
    panel.focus()
    return
  }

  const first = list[0]
  const last = list[list.length - 1]
  const active = document.activeElement

  if (!panel.contains(active)) {
    event.preventDefault()
    ;(event.shiftKey ? last : first).focus()
    return
  }
  if (event.shiftKey && active === first) {
    event.preventDefault()
    last.focus()
  } else if (!event.shiftKey && active === last) {
    event.preventDefault()
    first.focus()
  }
}

// Covers the paths Tab handling cannot: a click on the page behind, or a
// programmatic focus from another component.
function onFocusIn(event) {
  const panel = panelEl()
  if (!panel || panel.contains(event.target)) return
  const list = focusables(panel)
  ;(list[0] || panel).focus()
}

// Disabling the control that currently has focus ("Mark all read" does this
// to itself) drops focus onto <body> without firing focusin, which would
// silently leak the user out of the dialog. Re-anchor on the next frame.
function onFocusOut() {
  if (!store.drawerOpen) return
  requestAnimationFrame(() => {
    if (!store.drawerOpen) return
    const panel = panelEl()
    if (!panel) return
    const active = document.activeElement
    if (active && active !== document.body && panel.contains(active)) return
    const list = focusables(panel)
    ;(list[0] || panel).focus()
  })
}

function bindGuards() {
  document.addEventListener('keydown', onKeydown, true)
  document.addEventListener('focusin', onFocusIn, true)
  document.addEventListener('focusout', onFocusOut, true)
}

function releaseGuards() {
  document.removeEventListener('keydown', onKeydown, true)
  document.removeEventListener('focusin', onFocusIn, true)
  document.removeEventListener('focusout', onFocusOut, true)
}

watch(
  () => store.drawerOpen,
  async (open) => {
    if (open) {
      loadMarkdown()
      previouslyFocused = document.activeElement
      await nextTick()
      bindGuards()
      return
    }
    releaseGuards()
    // Wait a tick so the leave transition has removed the panel before the
    // bell takes focus back; otherwise the focus guard fights the restore.
    await nextTick()
    if (previouslyFocused?.isConnected) previouslyFocused.focus()
    previouslyFocused = null
  },
)

// A drawer left open would hang over whatever route comes next — including
// the /notifications page it links to.
watch(() => route.fullPath, () => {
  if (store.drawerOpen) store.closeDrawer()
})

onBeforeUnmount(releaseGuards)
</script>

<template>
  <SwipeDrawer :open="store.drawerOpen" side="top" title="Notifications" @close="store.closeDrawer()">
    <div ref="contentRef" class="feed">
      <div class="toolbar">
        <p class="count" aria-live="polite">
          {{ store.unreadCount ? `${store.badgeLabel} unread` : 'All caught up' }}
        </p>
        <button
          type="button"
          class="link-btn"
          :disabled="!store.unreadCount"
          @click="store.markAllRead()"
        >
          Mark all read
        </button>
      </div>

      <div v-if="store.loading && !notifications.length" class="skeletons" aria-hidden="true">
        <div v-for="i in 4" :key="i" class="skeleton-row" />
      </div>

      <ErrorState v-else-if="store.error" :message="store.error" compact />

      <EmptyState
        v-else-if="showEmpty"
        :icon="BellSlashIcon"
        message="No notifications yet. Account activity will show up here."
      />

      <ul v-else class="rows">
        <li v-for="item in notifications" :key="item.id">
          <button
            type="button"
            class="row"
            :class="[`row--${item.severity}`, { 'row--unread': !item.read }]"
            @click="onSelect(item)"
          >
            <span class="pip" :class="`pip--${item.severity}`" aria-hidden="true"></span>
            <span class="row-main">
              <span class="row-head">
                <span class="row-title">{{ item.title }}</span>
                <span class="row-time">{{ timeLabel(item) }}</span>
              </span>
              <!-- eslint-disable-next-line vue/no-v-html -->
              <span v-if="markdown" class="row-body markdown" v-html="bodyHtml(item)"></span>
              <span v-else class="row-body">{{ item.body }}</span>
              <span v-if="!item.read" class="sr-only">Unread</span>
            </span>
            <ChevronRightIcon v-if="item.link" class="row-chevron" aria-hidden="true" />
          </button>
        </li>
      </ul>

      <button type="button" class="see-all" @click="seeAll">See all notifications</button>
    </div>
  </SwipeDrawer>
</template>

<style scoped>
.feed {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  padding-bottom: var(--space-2);
}

.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}
.count {
  margin: 0;
  font-size: 0.75rem;
  font-weight: 600;
  color: var(--text-secondary);
}
.link-btn {
  border: 0;
  background: transparent;
  padding: 0.25rem 0.1rem;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--accent-strong);
  cursor: pointer;
}
.link-btn:disabled {
  color: var(--text-muted);
  cursor: default;
}
.link-btn:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
  border-radius: var(--radius-sm);
}

.rows {
  list-style: none;
  margin: 0;
  padding: 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}

.row {
  width: 100%;
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  padding: var(--space-3);
  border: 1px solid var(--divider);
  border-radius: var(--radius-md);
  background: var(--surface-muted);
  color: var(--text-primary);
  text-align: left;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}
.row:hover {
  background: color-mix(in srgb, var(--text-primary) 5%, var(--surface-muted));
}
.row:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
/* Unread carries three cues, not just colour: a tinted surface, a heavier
   title and the leading pip — colour alone would not survive the
   high-contrast palette. */
.row--unread {
  background: var(--accent-soft);
  border-color: color-mix(in srgb, var(--accent) 35%, transparent);
}
.row--unread .row-title {
  font-weight: 800;
}

.pip {
  width: 0.5rem;
  height: 0.5rem;
  flex-shrink: 0;
  margin-top: 0.4rem;
  border-radius: var(--radius-pill);
  background: var(--text-muted);
}
.row--unread .pip--info { background: var(--accent); }
.row--unread .pip--success { background: var(--success-fg); }
.row--unread .pip--warning { background: var(--warning-fg); }
.row--unread .pip--danger { background: var(--danger-fg); }
.row:not(.row--unread) .pip { opacity: 0.35; }

.row-main {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.15rem;
}
.row-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}
.row-title {
  font-size: 0.85rem;
  font-weight: 600;
  line-height: 1.25;
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.row-time {
  flex-shrink: 0;
  font-size: 0.7rem;
  color: var(--text-muted);
}
.row-body {
  font-size: 0.78rem;
  line-height: 1.45;
  color: var(--text-secondary);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
.row-chevron {
  width: 0.9rem;
  height: 0.9rem;
  flex-shrink: 0;
  margin-top: 0.3rem;
  color: var(--text-muted);
}

.see-all {
  width: 100%;
  padding: 0.6rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--btn-outline-border);
  background: var(--btn-outline-bg);
  color: var(--btn-outline-fg);
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
}
.see-all:active { transform: scale(0.98); }
.see-all:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}

.skeletons {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.skeleton-row {
  height: 3.5rem;
  border-radius: var(--radius-md);
  background: var(--surface-muted);
  animation: notif-pulse 1.6s ease-in-out infinite;
}
@keyframes notif-pulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 0.85; }
}
/* style.css guards the shared .skeleton sweep but not this local keyframe, so
   the placeholder would keep pulsing for someone who asked for less motion. */
@media (prefers-reduced-motion: reduce) {
  .skeleton-row { animation: none; }
}


/* Markdown bodies are sanitised in utils/markdown.js; these rules only make
   the surviving tags sit correctly inside a compact row. */
.markdown :deep(p) { margin: 0; }
.markdown :deep(p + p) { margin-top: 0.3rem; }
.markdown :deep(a) { color: var(--accent-strong); text-decoration: underline; }
/* An anchor whose href the sanitiser rejected must not keep looking
   clickable — a blocked javascript: link should read as plain text. */
.markdown :deep(a:not([href])) {
  color: inherit;
  text-decoration: none;
  cursor: default;
}
.markdown :deep(code) {
  font-size: 0.9em;
  padding: 0 0.2em;
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--text-primary) 8%, transparent);
}
.markdown :deep(ul),
.markdown :deep(ol) { margin: 0.2rem 0 0; padding-left: 1.1rem; }
.markdown :deep(h1),
.markdown :deep(h2),
.markdown :deep(h3),
.markdown :deep(h4),
.markdown :deep(h5),
.markdown :deep(h6) {
  margin: 0;
  font-size: 0.82rem;
  font-weight: 700;
  color: var(--text-primary);
}
</style>
