<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { BellSlashIcon, ChevronLeftIcon, ChevronRightIcon } from '@heroicons/vue/24/solid'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import { useNotificationsStore, formatNotificationTime } from '../stores/notifications'
import { renderMarkdown } from '../utils/markdown'

const router = useRouter()
const store = useNotificationsStore()

const TABS = ['All', 'Unread', 'Read']
const activeTab = ref('All')

// The frozen endpoint contract has no server-side read filter, so filtering
// is applied to the page that is loaded rather than to the whole history.
// The count line below says so explicitly instead of implying otherwise.
const rows = computed(() => {
  if (activeTab.value === 'Unread') return store.pageItems.filter((n) => !n.read)
  if (activeTab.value === 'Read') return store.pageItems.filter((n) => n.read)
  return store.pageItems
})

const showEmpty = computed(() => !store.pageLoading && !store.pageError && rows.value.length === 0)
// "Nothing here yet" is only true when the history itself is empty. With a tab
// filter active over a non-empty page it read as though the account had never
// been notified at all, when the rest of the list is one tab away.
const emptyMessage = computed(() => {
  if (store.pageItems.length) {
    return `No ${activeTab.value.toLowerCase()} notifications on this page.`
  }
  return 'Nothing here yet. Alerts about your account will appear on this page.'
})
const canGoBack = computed(() => store.page > 1)
const rangeLabel = computed(() => {
  if (!store.total) return ''
  const from = (store.page - 1) * store.perPage + 1
  const to = Math.min(store.total, from + store.pageItems.length - 1)
  return `${from}–${to} of ${store.total}`
})

function bodyHtml(item) {
  return renderMarkdown(item.body)
}

function timeLabel(item) {
  return formatNotificationTime(item.createdAt, item.createdTs)
}

function onSelect(item) {
  if (!item.read) store.markRead(item.id)
  if (item.link) router.push(item.link).catch(() => {})
}

function goToPage(next) {
  const target = Math.max(1, next)
  if (target === store.page) return
  store.loadPage(target)
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

onMounted(() => {
  store.subscribe()
  store.loadPage(1)
})
onBeforeUnmount(() => store.unsubscribe())
</script>

<template>
  <div class="page">
    <div class="content">
      <header class="header">
        <button type="button" class="back" aria-label="Go back" @click="router.back()">
          <ChevronLeftIcon class="back-icon" aria-hidden="true" />
        </button>
        <div class="titles">
          <h1 class="title">Notifications</h1>
          <p class="subtitle">Alerts and account activity</p>
        </div>
        <button
          type="button"
          class="mark-all"
          :disabled="!store.unreadCount"
          @click="store.markAllRead()"
        >
          Mark all read
        </button>
      </header>

      <div class="tabs" role="tablist" aria-label="Filter notifications">
        <button
          v-for="tab in TABS"
          :key="tab"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab"
          class="tab"
          :class="{ 'tab--active': activeTab === tab }"
          @click="activeTab = tab"
        >
          {{ tab }}
        </button>
      </div>

      <div v-if="store.pageLoading" class="skeletons" aria-hidden="true">
        <div v-for="i in 5" :key="i" class="skeleton-row" />
      </div>

      <ErrorState v-else-if="store.pageError" :message="store.pageError" />

      <template v-else>
        <p v-if="rangeLabel" class="count">
          Showing {{ rangeLabel }}<span v-if="activeTab !== 'All'"> · {{ activeTab.toLowerCase() }} on this page</span>
        </p>

        <EmptyState
          v-if="showEmpty"
          :icon="BellSlashIcon"
          :message="emptyMessage"
        />

        <ul v-else class="rows">
          <li v-for="item in rows" :key="item.id">
            <button
              type="button"
              class="row"
              :class="{ 'row--unread': !item.read }"
              @click="onSelect(item)"
            >
              <span class="pip" :class="`pip--${item.severity}`" aria-hidden="true"></span>
              <span class="row-main">
                <span class="row-head">
                  <span class="row-title">{{ item.title }}</span>
                  <span class="row-time">{{ timeLabel(item) }}</span>
                </span>
                <!-- Sanitised in utils/markdown.js. eslint-disable-next-line vue/no-v-html -->
                <span class="row-body markdown" v-html="bodyHtml(item)"></span>
                <span v-if="!item.read" class="sr-only">Unread</span>
              </span>
              <ChevronRightIcon v-if="item.link" class="row-chevron" aria-hidden="true" />
            </button>
          </li>
        </ul>

        <nav v-if="store.totalPages > 1" class="pager" aria-label="Notification pages">
          <button
            type="button"
            class="pager-btn"
            :disabled="!canGoBack"
            @click="goToPage(store.page - 1)"
          >
            Previous
          </button>
          <span class="pager-status" aria-live="polite">
            Page {{ store.page }} of {{ store.totalPages }}
          </span>
          <button
            type="button"
            class="pager-btn"
            :disabled="!store.hasMore"
            @click="goToPage(store.page + 1)"
          >
            Next
          </button>
        </nav>
      </template>
    </div>
  </div>
</template>

<style scoped>
.page {
  min-height: 100vh;
  background: var(--bg-gradient);
  padding: var(--space-5) var(--space-4) 7rem;
}
.content {
  max-width: 30rem;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}
.header {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) 0 var(--space-1);
}
.back {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.7rem;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--text-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
  transition: background-color 0.15s ease, transform 0.1s ease;
}
.back:hover { background: color-mix(in srgb, var(--text-primary) 6%, transparent); }
.back:active { transform: scale(0.95); }
.back-icon { width: 1.15rem; height: 1.15rem; }
.titles { min-width: 0; flex: 1 1 auto; }
.title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  line-height: 1.1;
}
.subtitle {
  font-size: 0.8rem;
  color: var(--text-secondary);
  margin: 0.15rem 0 0;
}
.mark-all {
  flex-shrink: 0;
  border: 0;
  background: transparent;
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--accent-strong);
  cursor: pointer;
  padding: 0.35rem 0.1rem;
}
.mark-all:disabled { color: var(--text-muted); cursor: default; }

.tabs {
  display: flex;
  gap: var(--space-2);
  overflow-x: auto;
  scrollbar-width: none;
  padding-bottom: 0.25rem;
}
.tabs::-webkit-scrollbar { display: none; }
.tab {
  padding: 0.55rem 1.1rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-secondary);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.tab:hover { color: var(--text-primary); }
.tab--active {
  background: var(--btn-primary-bg);
  color: var(--btn-primary-fg);
  border-color: var(--btn-primary-bg);
}

.count {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin: 0;
  padding: 0 var(--space-1);
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
  padding: var(--space-4);
  border: 1px solid transparent;
  border-radius: var(--radius-lg);
  background: var(--surface);
  box-shadow: var(--shadow-card);
  color: var(--text-primary);
  text-align: left;
  cursor: pointer;
  transition: background-color 0.15s ease, border-color 0.15s ease;
}
.row:focus-visible {
  outline: 2px solid var(--accent);
  outline-offset: 2px;
}
/* Unread is signalled by tint, border, weight and the pip together — never by
   colour alone, which the forced high-contrast palette flattens. */
.row--unread {
  background: var(--accent-soft);
  border-color: color-mix(in srgb, var(--accent) 35%, transparent);
}
.row--unread .row-title { font-weight: 800; }

.pip {
  width: 0.55rem;
  height: 0.55rem;
  flex-shrink: 0;
  margin-top: 0.4rem;
  border-radius: var(--radius-pill);
  background: var(--text-muted);
}
.pip--info { background: var(--accent); }
.pip--success { background: var(--success-fg); }
.pip--warning { background: var(--warning-fg); }
.pip--danger { background: var(--danger-fg); }
.row:not(.row--unread) .pip { opacity: 0.35; }

.row-main {
  flex: 1 1 auto;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;
}
.row-head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-2);
}
.row-title {
  font-size: 0.9rem;
  font-weight: 600;
  line-height: 1.25;
  min-width: 0;
}
.row-time {
  flex-shrink: 0;
  font-size: 0.7rem;
  color: var(--text-muted);
}
.row-body {
  font-size: 0.82rem;
  line-height: 1.5;
  color: var(--text-secondary);
}
.row-chevron {
  width: 1rem;
  height: 1rem;
  flex-shrink: 0;
  margin-top: 0.3rem;
  color: var(--text-muted);
}

.pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}
.pager-btn {
  padding: 0.55rem 1.1rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--btn-outline-border);
  background: var(--btn-outline-bg);
  color: var(--btn-outline-fg);
  font-size: 0.8rem;
  font-weight: 700;
  cursor: pointer;
}
.pager-btn:disabled { opacity: 0.45; cursor: not-allowed; }
.pager-status {
  font-size: 0.75rem;
  color: var(--text-secondary);
}

.skeletons {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.skeleton-row {
  height: 5rem;
  border-radius: var(--radius-lg);
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


/* Sanitised markdown; these rules only place the surviving tags. */
.markdown :deep(p) { margin: 0; }
.markdown :deep(p + p) { margin-top: 0.45rem; }
.markdown :deep(a) { color: var(--accent-strong); text-decoration: underline; }
.markdown :deep(a:focus-visible) { outline: 2px solid var(--accent); outline-offset: 2px; }
/* An anchor whose href the sanitiser rejected must not keep looking clickable
   — otherwise a blocked javascript: link still reads as a live control. */
.markdown :deep(a:not([href])) {
  color: inherit;
  text-decoration: none;
  cursor: default;
}
.markdown :deep(strong) { color: var(--text-primary); }
.markdown :deep(code) {
  font-size: 0.9em;
  padding: 0.05em 0.3em;
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--text-primary) 8%, transparent);
}
.markdown :deep(pre) {
  margin: 0.45rem 0 0;
  padding: var(--space-3);
  overflow-x: auto;
  border-radius: var(--radius-sm);
  background: color-mix(in srgb, var(--text-primary) 8%, transparent);
}
.markdown :deep(pre code) { background: transparent; padding: 0; }
.markdown :deep(ul),
.markdown :deep(ol) { margin: 0.35rem 0 0; padding-left: 1.2rem; }
.markdown :deep(li + li) { margin-top: 0.15rem; }
.markdown :deep(blockquote) {
  margin: 0.45rem 0 0;
  padding-left: var(--space-3);
  border-left: 2px solid var(--border);
  color: var(--text-muted);
}
.markdown :deep(hr) {
  margin: 0.6rem 0;
  border: 0;
  border-top: 1px solid var(--divider);
}
.markdown :deep(h1),
.markdown :deep(h2),
.markdown :deep(h3),
.markdown :deep(h4),
.markdown :deep(h5),
.markdown :deep(h6) {
  margin: 0.45rem 0 0.15rem;
  font-size: 0.88rem;
  font-weight: 700;
  color: var(--text-primary);
}
</style>
