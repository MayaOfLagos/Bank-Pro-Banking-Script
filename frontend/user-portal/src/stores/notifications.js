import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import { useDocumentVisibility, useIntervalFn } from '@vueuse/core'
import client from '../api/client'
import { useAuthStore } from './auth'

const ENDPOINT = '/api/user/notifications.php'

// The drawer is a peek at the newest activity, not a second history page.
export const DRAWER_LIMIT = 10
export const PAGE_SIZE = 20

// Poll slowly: an unread badge is not worth a request per few seconds from
// every open tab, and the drawer refetches on open anyway.
const POLL_INTERVAL_MS = 60_000
// After the tab has been hidden the cached count is assumed stale, so
// returning to it refetches instead of waiting out the rest of the interval.
const STALE_AFTER_MS = 30_000

const SEVERITIES = new Set(['info', 'success', 'warning', 'danger'])

/**
 * `link` is admin-authored and is fed to router.push(), so it is constrained
 * to a root-relative in-app path. A protocol-relative "//evil.example" or a
 * "javascript:" string would otherwise become a navigation primitive.
 *
 * The second character is checked against slash *and* backslash: a browser
 * parsing a special-scheme URL treats the two as equivalent, so "/\evil.test"
 * is the same authority-relative reference as "//evil.test" wearing a costume
 * that a naive startsWith('//') check waves through.
 */
export function safeInternalPath(raw) {
  const value = typeof raw === 'string' ? raw.trim() : ''
  if (!value) return ''
  if (value[0] !== '/') return ''
  if (value[1] === '/' || value[1] === '\\') return ''
  return value
}

function normalize(raw) {
  const severity = String(raw?.severity ?? 'info')
  return {
    id: Number(raw?.id ?? 0),
    event: String(raw?.event ?? ''),
    title: String(raw?.title ?? ''),
    body: String(raw?.body ?? ''),
    severity: SEVERITIES.has(severity) ? severity : 'info',
    link: safeInternalPath(raw?.link),
    read: Boolean(raw?.read),
    createdAt: String(raw?.created_at ?? ''),
    createdTs: Number(raw?.created_ts) > 0 ? Number(raw.created_ts) : 0,
  }
}

function readError(err, fallback) {
  return err?.response?.data?.message || err?.message || fallback
}

/**
 * "3m ago" / "Yesterday" / "3 Aug".
 *
 * Prefers `created_ts`, the epoch the API derives with UNIX_TIMESTAMP(). The
 * `created_at` string is a naive MySQL-local datetime, so parsing it here
 * silently reinterprets it in the *browser's* timezone — an offset that made
 * fresh alerts read as hours old. It stays only as a fallback for a row that
 * predates the epoch field.
 */
export function formatNotificationTime(raw, epochSeconds = 0) {
  const ts = Number(epochSeconds)
  const parsed = ts > 0 ? new Date(ts * 1000) : new Date(String(raw ?? '').replace(' ', 'T'))
  if (!raw && !(ts > 0)) return ''
  if (Number.isNaN(parsed.getTime())) return String(raw ?? '')

  const seconds = Math.round((Date.now() - parsed.getTime()) / 1000)
  if (seconds < 0) return 'Just now'
  if (seconds < 60) return 'Just now'
  if (seconds < 3600) return `${Math.floor(seconds / 60)}m ago`
  if (seconds < 86_400) return `${Math.floor(seconds / 3600)}h ago`
  if (seconds < 172_800) return 'Yesterday'
  if (seconds < 604_800) return `${Math.floor(seconds / 86_400)}d ago`

  return parsed.toLocaleDateString(undefined, {
    day: 'numeric',
    month: 'short',
    ...(parsed.getFullYear() === new Date().getFullYear() ? {} : { year: 'numeric' }),
  })
}

export const useNotificationsStore = defineStore('notifications', () => {
  const auth = useAuthStore()

  // Drawer feed.
  const items = ref([])
  const unreadCount = ref(0)
  const loading = ref(false)
  const error = ref('')
  const loadedOnce = ref(false)
  const lastFetchedAt = ref(0)
  const drawerOpen = ref(false)

  // Full-history page. Kept separate from the drawer feed so paging back
  // through history never rewrites what the bell is showing.
  const pageItems = ref([])
  const page = ref(1)
  const perPage = ref(PAGE_SIZE)
  const total = ref(0)
  const hasMore = ref(false)
  const pageLoading = ref(false)
  const pageError = ref('')

  const hasUnread = computed(() => unreadCount.value > 0)
  const totalPages = computed(() => Math.max(1, Math.ceil(total.value / Math.max(1, perPage.value))))
  const badgeLabel = computed(() => (unreadCount.value > 99 ? '99+' : String(unreadCount.value)))

  function applyEnvelope(payload) {
    unreadCount.value = Math.max(0, Number(payload?.unread_count ?? 0))
  }

  /**
   * Newest slice for the drawer plus the authoritative unread count.
   * `silent` is the polling path: it must not flip the list into a skeleton
   * or replace a visible list with an error banner.
   */
  async function loadFeed({ silent = false } = {}) {
    if (!silent) {
      loading.value = true
      error.value = ''
    }
    try {
      const { data } = await client.get(ENDPOINT, { params: { limit: DRAWER_LIMIT } })
      if (data?.ok === false) throw new Error(data?.message || 'Failed to load notifications')
      const payload = data?.data || {}
      items.value = Array.isArray(payload.notifications) ? payload.notifications.map(normalize) : []
      applyEnvelope(payload)
      loadedOnce.value = true
      lastFetchedAt.value = Date.now()
      if (silent) error.value = ''
    } catch (err) {
      if (!silent) error.value = readError(err, 'Unable to load notifications')
    } finally {
      if (!silent) loading.value = false
    }
  }

  async function loadPage(nextPage = 1) {
    const target = Math.max(1, Number(nextPage) || 1)
    pageLoading.value = true
    pageError.value = ''
    try {
      const { data } = await client.get(ENDPOINT, {
        params: { view: 'all', page: target, per_page: PAGE_SIZE },
      })
      if (data?.ok === false) throw new Error(data?.message || 'Failed to load notifications')
      const payload = data?.data || {}
      pageItems.value = Array.isArray(payload.notifications) ? payload.notifications.map(normalize) : []
      page.value = Math.max(1, Number(payload.page ?? target))
      perPage.value = Math.max(1, Number(payload.per_page ?? PAGE_SIZE))
      total.value = Math.max(0, Number(payload.total ?? pageItems.value.length))
      hasMore.value = Boolean(payload.has_more)
      applyEnvelope(payload)
      lastFetchedAt.value = Date.now()
    } catch (err) {
      pageError.value = readError(err, 'Unable to load notifications')
    } finally {
      pageLoading.value = false
    }
  }

  function markLocally(id, read) {
    for (const list of [items.value, pageItems.value]) {
      for (const item of list) {
        if (item.id === id) item.read = read
      }
    }
  }

  /**
   * Optimistic: the row must stop looking unread the instant it is tapped,
   * because the tap usually navigates away before the response lands. A
   * failure rolls the row back rather than leaving a lie on screen.
   */
  async function markRead(id) {
    const target = Number(id)
    if (!target) return
    const wasUnread = [...items.value, ...pageItems.value].some((n) => n.id === target && !n.read)
    if (!wasUnread) return

    markLocally(target, true)
    unreadCount.value = Math.max(0, unreadCount.value - 1)

    try {
      const { data } = await client.post(ENDPOINT, { action: 'read', id: target })
      if (data?.ok === false) throw new Error(data?.message || 'Failed')
      applyEnvelope(data?.data || {})
    } catch {
      markLocally(target, false)
      unreadCount.value += 1
    }
  }

  async function markAllRead() {
    const snapshot = [...items.value, ...pageItems.value]
      .filter((n) => !n.read)
      .map((n) => n.id)
    if (!snapshot.length && unreadCount.value === 0) return

    const previousCount = unreadCount.value
    for (const id of snapshot) markLocally(id, true)
    unreadCount.value = 0

    try {
      const { data } = await client.post(ENDPOINT, { action: 'read_all' })
      if (data?.ok === false) throw new Error(data?.message || 'Failed')
      applyEnvelope(data?.data || {})
    } catch {
      for (const id of snapshot) markLocally(id, false)
      unreadCount.value = previousCount
    }
  }

  function openDrawer() {
    drawerOpen.value = true
    loadFeed()
  }

  function closeDrawer() {
    drawerOpen.value = false
  }

  function reset() {
    items.value = []
    pageItems.value = []
    unreadCount.value = 0
    loading.value = false
    error.value = ''
    pageError.value = ''
    loadedOnce.value = false
    lastFetchedAt.value = 0
    drawerOpen.value = false
    page.value = 1
    total.value = 0
    hasMore.value = false
  }

  // --- Polling -------------------------------------------------------------
  // Reference-counted so the two screens that can mount the bell at once
  // (and the notifications page) share one timer instead of stacking them.
  const subscribers = ref(0)
  const visibility = useDocumentVisibility()

  const shouldPoll = computed(
    () => subscribers.value > 0 && visibility.value !== 'hidden' && auth.isAuthenticated,
  )

  const { pause, resume } = useIntervalFn(
    () => { loadFeed({ silent: true }) },
    POLL_INTERVAL_MS,
    { immediate: false, immediateCallback: false },
  )

  watch(
    shouldPoll,
    (active) => {
      if (!active) {
        pause()
        return
      }
      if (Date.now() - lastFetchedAt.value > STALE_AFTER_MS) loadFeed({ silent: true })
      resume()
    },
    { immediate: true },
  )

  function subscribe() {
    subscribers.value += 1
  }

  function unsubscribe() {
    subscribers.value = Math.max(0, subscribers.value - 1)
  }

  return {
    items,
    unreadCount,
    loading,
    error,
    loadedOnce,
    drawerOpen,
    pageItems,
    page,
    perPage,
    total,
    hasMore,
    pageLoading,
    pageError,
    hasUnread,
    totalPages,
    badgeLabel,
    loadFeed,
    loadPage,
    markRead,
    markAllRead,
    openDrawer,
    closeDrawer,
    reset,
    subscribe,
    unsubscribe,
  }
})
