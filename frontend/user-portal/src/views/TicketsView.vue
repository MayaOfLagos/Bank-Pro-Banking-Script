<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  ChevronLeftIcon,
  ChatBubbleLeftRightIcon,
  PaperAirplaneIcon,
  PlusIcon,
  TagIcon,
  CheckCircleIcon,
  ExclamationTriangleIcon,
  XMarkIcon,
} from '@heroicons/vue/24/solid'
import ErrorState from '../components/ui/ErrorState.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import FormField from '../components/ui/FormField.vue'
import { useTickets } from '../composables/useTickets'

const router = useRouter()
const toast = useToast()
const tickets = useTickets()

// View state — kept local so we don't need to add a nested route to
// router/index.js (my collaborator is editing that file in parallel).
const mode = ref('list') // 'list' | 'detail'
const showCreate = ref(false)

const CATEGORY_LABEL = {
  billing: 'Billing',
  transfer: 'Transfer',
  account: 'Account',
  other: 'Other',
}

// Status vocabulary — we normalise to a small set the badge understands.
const STATUS_META = {
  open: { label: 'Open', kind: 'open' },
  pending: { label: 'Pending', kind: 'pending' },
  resolved: { label: 'Resolved', kind: 'resolved' },
  closed: { label: 'Closed', kind: 'closed' },
}

function statusMeta(status) {
  const key = String(status || '').toLowerCase()
  return STATUS_META[key] || { label: status || 'Unknown', kind: 'unknown' }
}

function categoryLabel(cat) {
  return CATEGORY_LABEL[String(cat || '').toLowerCase()] || 'General'
}

function formatRelative(raw) {
  if (!raw) return ''
  const d = new Date(String(raw).replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return String(raw)
  const diffMs = Date.now() - d.getTime()
  const diffMin = Math.round(diffMs / 60_000)
  if (diffMin < 1) return 'just now'
  if (diffMin < 60) return `${diffMin}m ago`
  const diffHr = Math.round(diffMin / 60)
  if (diffHr < 24) return `${diffHr}h ago`
  const diffDay = Math.round(diffHr / 24)
  if (diffDay < 7) return `${diffDay}d ago`
  return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatTimestamp(raw) {
  if (!raw) return ''
  const d = new Date(String(raw).replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return String(raw)
  return d.toLocaleString(undefined, {
    day: 'numeric',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function lastMessagePreview(ticket) {
  const list = Array.isArray(ticket?.messages) ? ticket.messages : []
  const last = list[list.length - 1]
  if (!last?.body) return ''
  const trimmed = String(last.body).trim().replace(/\s+/g, ' ')
  return trimmed.length > 90 ? `${trimmed.slice(0, 90)}…` : trimmed
}

function messageCount(ticket) {
  return Array.isArray(ticket?.messages) ? ticket.messages.length : 0
}

// --- List → detail navigation --------------------------------------------

async function openTicket(id) {
  await tickets.loadDetail(id)
  mode.value = 'detail'
  await nextTick()
  scrollThreadToEnd()
}

function backToList() {
  mode.value = 'list'
  tickets.current.value = null
}

// --- New ticket form -----------------------------------------------------

const newSubject = ref('')
const newCategory = ref('billing')
const newMessage = ref('')
const newErrors = ref({ subject: '', category: '', message: '' })

function resetNewForm() {
  newSubject.value = ''
  newCategory.value = 'billing'
  newMessage.value = ''
  newErrors.value = { subject: '', category: '', message: '' }
}

function openCreate() {
  resetNewForm()
  showCreate.value = true
}

function closeCreate() {
  showCreate.value = false
}

function validateCreate() {
  const errors = { subject: '', category: '', message: '' }
  if (!newSubject.value.trim()) errors.subject = 'Give the ticket a short subject.'
  else if (newSubject.value.trim().length > 120) errors.subject = 'Keep it under 120 characters.'
  if (!newCategory.value) errors.category = 'Pick a category.'
  if (!newMessage.value.trim()) errors.message = 'Describe how we can help.'
  newErrors.value = errors
  return !errors.subject && !errors.category && !errors.message
}

async function submitCreate() {
  if (!validateCreate()) return
  try {
    const ticket = await tickets.create({
      subject: newSubject.value.trim(),
      category: newCategory.value,
      message: newMessage.value.trim(),
    })
    toast.success('Ticket created')
    closeCreate()
    if (ticket?.id != null) {
      await openTicket(ticket.id)
    }
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Unable to create ticket')
  }
}

// --- Reply composer ------------------------------------------------------

const replyBody = ref('')
const threadEl = ref(null)

function scrollThreadToEnd() {
  const el = threadEl.value
  if (!el) return
  el.scrollTop = el.scrollHeight
}

watch(
  () => tickets.current.value?.messages?.length,
  () => {
    // Any time the thread grows, keep the newest message in view.
    nextTick(scrollThreadToEnd)
  },
)

const canReply = computed(() => {
  const t = tickets.current.value
  if (!t) return false
  const status = statusMeta(t.status).kind
  return status !== 'closed'
})

async function sendReply() {
  const body = replyBody.value.trim()
  if (!body) return
  const t = tickets.current.value
  if (!t?.id) return
  try {
    await tickets.reply({ id: t.id, message: body })
    replyBody.value = ''
    await nextTick()
    scrollThreadToEnd()
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Reply failed')
  }
}

// --- Close ticket --------------------------------------------------------

async function closeCurrent() {
  const t = tickets.current.value
  if (!t?.id) return
  const proceed = window.confirm('Close this ticket? You can start a new one anytime.')
  if (!proceed) return
  try {
    await tickets.close(t.id)
    toast.success('Ticket closed')
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Unable to close ticket')
  }
}

// --- Sender bubble presentation -----------------------------------------

function isCustomer(msg) {
  const role = String(msg?.sender_role || '').toLowerCase()
  // Treat customer/user/self as own-side; anything else (agent/admin/staff)
  // is the support side.
  return role === 'customer' || role === 'user' || role === 'self'
}

onMounted(async () => {
  await tickets.load()
})
</script>

<template>
  <div class="page">
    <div class="content">
      <!-- ================= LIST VIEW ================= -->
      <template v-if="mode === 'list'">
        <header class="header">
          <button type="button" class="back" aria-label="Go back" @click="router.push('/dashboard')">
            <ChevronLeftIcon class="back-icon" aria-hidden="true" />
          </button>
          <div class="titles">
            <h1 class="title">Support</h1>
            <p class="subtitle">Get help from our team</p>
          </div>
        </header>

        <div v-if="tickets.placeholder.value" class="preview-banner" role="status">
          <ExclamationTriangleIcon class="preview-icon" aria-hidden="true" />
          <div>
            <p class="preview-title">Preview mode</p>
            <p class="preview-body">Ticket endpoints are not wired up yet, so this list shows sample data. Messages you send stay in your browser.</p>
          </div>
        </div>

        <button type="button" class="new-ticket" @click="openCreate">
          <PlusIcon class="new-ticket-icon" aria-hidden="true" />
          <span>New ticket</span>
        </button>

        <template v-if="tickets.loading.value">
          <div class="skeleton-group">
            <div v-for="i in 3" :key="i" class="skeleton-row" />
          </div>
        </template>

        <ErrorState v-else-if="tickets.error.value" :message="tickets.error.value" />

        <EmptyState
          v-else-if="!tickets.tickets.value.length"
          :icon="ChatBubbleLeftRightIcon"
          message="No support tickets yet. Reach out anytime — we usually reply within a business day."
        >
          <button type="button" class="request-btn" @click="openCreate">Start a conversation</button>
        </EmptyState>

        <section v-else class="list" aria-label="Your tickets">
          <button
            v-for="ticket in tickets.tickets.value"
            :key="ticket.id"
            type="button"
            class="ticket-card"
            @click="openTicket(ticket.id)"
          >
            <div class="ticket-head">
              <p class="ticket-subject">{{ ticket.subject }}</p>
              <span class="status-badge" :class="`status-badge--${statusMeta(ticket.status).kind}`">
                <span class="status-dot" aria-hidden="true"></span>
                {{ statusMeta(ticket.status).label }}
              </span>
            </div>
            <div class="ticket-meta">
              <span class="tag">
                <TagIcon class="tag-icon" aria-hidden="true" />
                {{ categoryLabel(ticket.category) }}
              </span>
              <span v-if="ticket.reference" class="ref">{{ ticket.reference }}</span>
              <span class="dot" aria-hidden="true">·</span>
              <span class="time">{{ formatRelative(ticket.updated_at || ticket.created_at) }}</span>
            </div>
            <p v-if="lastMessagePreview(ticket)" class="ticket-preview">{{ lastMessagePreview(ticket) }}</p>
            <p class="ticket-count">
              {{ messageCount(ticket) }} message{{ messageCount(ticket) === 1 ? '' : 's' }}
            </p>
          </button>
        </section>
      </template>

      <!-- ================= DETAIL VIEW ================= -->
      <template v-else-if="mode === 'detail' && tickets.current.value">
        <header class="header">
          <button type="button" class="back" aria-label="Back to tickets" @click="backToList">
            <ChevronLeftIcon class="back-icon" aria-hidden="true" />
          </button>
          <div class="titles">
            <h1 class="title detail-title">{{ tickets.current.value.subject }}</h1>
            <p class="subtitle">
              <span v-if="tickets.current.value.reference">{{ tickets.current.value.reference }}</span>
              <span v-if="tickets.current.value.reference" class="dot" aria-hidden="true">·</span>
              <span>{{ categoryLabel(tickets.current.value.category) }}</span>
            </p>
          </div>
          <button
            v-if="statusMeta(tickets.current.value.status).kind !== 'closed'"
            type="button"
            class="close-btn"
            :disabled="tickets.submitting.value"
            @click="closeCurrent"
          >
            <CheckCircleIcon class="close-icon" aria-hidden="true" />
            <span>Close</span>
          </button>
        </header>

        <div class="detail-status">
          <span
            class="status-badge"
            :class="`status-badge--${statusMeta(tickets.current.value.status).kind}`"
          >
            <span class="status-dot" aria-hidden="true"></span>
            {{ statusMeta(tickets.current.value.status).label }}
          </span>
          <span class="detail-updated">Updated {{ formatRelative(tickets.current.value.updated_at) }}</span>
        </div>

        <section class="thread" ref="threadEl" aria-label="Message thread">
          <template v-if="tickets.current.value.messages && tickets.current.value.messages.length">
            <div
              v-for="msg in tickets.current.value.messages"
              :key="msg.id"
              class="bubble-row"
              :class="{ 'bubble-row--self': isCustomer(msg) }"
            >
              <div class="bubble" :class="{ 'bubble--self': isCustomer(msg) }">
                <p class="bubble-sender">{{ msg.sender_name || (isCustomer(msg) ? 'You' : 'Support') }}</p>
                <p class="bubble-body">{{ msg.body }}</p>
                <p class="bubble-time">{{ formatTimestamp(msg.created_at) }}</p>
              </div>
            </div>
          </template>
          <p v-else class="thread-empty">No messages yet.</p>
        </section>

        <form v-if="canReply" class="composer" @submit.prevent="sendReply">
          <textarea
            v-model="replyBody"
            class="composer-input"
            placeholder="Type your reply…"
            rows="3"
            :disabled="tickets.submitting.value"
          />
          <button
            type="submit"
            class="composer-send"
            :disabled="tickets.submitting.value || !replyBody.trim()"
            aria-label="Send reply"
          >
            <PaperAirplaneIcon class="composer-send-icon" aria-hidden="true" />
            <span>{{ tickets.submitting.value ? 'Sending…' : 'Send' }}</span>
          </button>
        </form>
        <p v-else class="closed-note">This ticket is closed. Start a new one if you need more help.</p>
      </template>
    </div>

    <!-- ================= CREATE MODAL ================= -->
    <div v-if="showCreate" class="modal" role="dialog" aria-modal="true" aria-labelledby="new-ticket-title">
      <button type="button" class="modal-backdrop" aria-label="Cancel" @click="closeCreate"></button>
      <div class="modal-panel">
        <div class="modal-head">
          <h2 id="new-ticket-title" class="modal-title">New ticket</h2>
          <button type="button" class="modal-close" aria-label="Close" @click="closeCreate">
            <XMarkIcon class="modal-close-icon" aria-hidden="true" />
          </button>
        </div>
        <form class="modal-form" @submit.prevent="submitCreate">
          <FormField label="Category" :error="newErrors.category" required>
            <template #default="{ id, describedBy }">
              <div class="input-wrap">
                <TagIcon class="input-icon" aria-hidden="true" />
                <select
                  :id="id"
                  v-model="newCategory"
                  class="input input--with-icon"
                  :aria-describedby="describedBy"
                >
                  <option value="" disabled>Select a category</option>
                  <option v-for="c in tickets.categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                </select>
              </div>
            </template>
          </FormField>

          <FormField label="Subject" :error="newErrors.subject" required>
            <template #default="{ id, describedBy }">
              <div class="input-wrap">
                <ChatBubbleLeftRightIcon class="input-icon" aria-hidden="true" />
                <input
                  :id="id"
                  v-model="newSubject"
                  type="text"
                  class="input input--with-icon"
                  maxlength="120"
                  placeholder="e.g. Wire transfer stuck in processing"
                  :aria-describedby="describedBy"
                />
              </div>
            </template>
          </FormField>

          <FormField label="Message" :error="newErrors.message" required>
            <template #default="{ id, describedBy }">
              <textarea
                :id="id"
                v-model="newMessage"
                class="input input--textarea"
                rows="5"
                placeholder="Share the details so we can help you faster."
                :aria-describedby="describedBy"
              />
            </template>
          </FormField>

          <div class="modal-actions">
            <button type="button" class="btn btn--ghost" :disabled="tickets.submitting.value" @click="closeCreate">
              Cancel
            </button>
            <button type="submit" class="btn btn--primary" :disabled="tickets.submitting.value">
              {{ tickets.submitting.value ? 'Sending…' : 'Send ticket' }}
            </button>
          </div>
        </form>
      </div>
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
.titles { flex: 1; min-width: 0; }
.title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  line-height: 1.1;
}
.detail-title {
  font-size: 1.2rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}
.subtitle {
  font-size: 0.8rem;
  color: var(--text-secondary);
  margin: 0.15rem 0 0;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
}
.dot { color: var(--text-muted); }
.close-btn {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.4rem 0.7rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border);
  background: transparent;
  color: var(--text-secondary);
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;
  flex-shrink: 0;
}
.close-btn:hover:not(:disabled) {
  color: var(--text-primary);
  background: color-mix(in srgb, var(--text-primary) 5%, transparent);
}
.close-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.close-icon { width: 0.95rem; height: 0.95rem; }

.preview-banner {
  display: flex;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  background: color-mix(in srgb, var(--warning-fg) 12%, transparent);
  border: 1px solid color-mix(in srgb, var(--warning-fg) 30%, transparent);
  border-radius: var(--radius-md);
  color: var(--warning-fg);
}
.preview-icon { width: 1.15rem; height: 1.15rem; flex-shrink: 0; margin-top: 0.1rem; }
.preview-title { font-weight: 700; margin: 0; font-size: 0.85rem; }
.preview-body {
  font-size: 0.75rem;
  margin: 0.15rem 0 0;
  color: color-mix(in srgb, var(--warning-fg) 85%, transparent);
  line-height: 1.4;
}

.new-ticket {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.85rem 1rem;
  border-radius: var(--radius-pill);
  border: none;
  background: var(--btn-primary-bg);
  color: var(--btn-primary-fg);
  font-weight: 600;
  font-size: 0.9rem;
  cursor: pointer;
  transition: transform 0.08s ease, opacity 0.15s ease;
  box-shadow: 0 8px 20px -8px color-mix(in srgb, var(--btn-primary-bg) 70%, transparent);
}
.new-ticket:hover { opacity: 0.92; }
.new-ticket:active { transform: scale(0.98); }
.new-ticket-icon { width: 1.1rem; height: 1.1rem; }

.request-btn {
  margin-top: var(--space-3);
  padding: 0.75rem 1.5rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--btn-outline-border);
  background: var(--surface);
  color: var(--text-primary);
  font-weight: 600;
  cursor: pointer;
}

.list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}
.ticket-card {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
  padding: var(--space-4);
  border-radius: var(--radius-lg);
  background: var(--surface);
  border: 1px solid transparent;
  box-shadow: var(--shadow-card);
  cursor: pointer;
  text-align: left;
  font-family: inherit;
  transition: transform 0.08s ease, border-color 0.15s ease;
}
.ticket-card:hover { border-color: var(--border); }
.ticket-card:active { transform: scale(0.995); }
.ticket-head {
  display: flex;
  align-items: flex-start;
  gap: var(--space-2);
  justify-content: space-between;
}
.ticket-subject {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
  flex: 1;
  min-width: 0;
}
.ticket-meta {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  flex-wrap: wrap;
  font-size: 0.75rem;
  color: var(--text-secondary);
}
.tag {
  display: inline-flex;
  align-items: center;
  gap: 0.25rem;
  padding: 0.15rem 0.5rem;
  border-radius: var(--radius-pill);
  background: var(--surface-muted);
  color: var(--text-secondary);
  font-weight: 600;
}
.tag-icon { width: 0.75rem; height: 0.75rem; }
.ref {
  font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
  font-size: 0.7rem;
  color: var(--text-muted);
}
.time { color: var(--text-muted); }
.ticket-preview {
  font-size: 0.8rem;
  color: var(--text-secondary);
  margin: 0;
  line-height: 1.4;
}
.ticket-count {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-weight: 600;
}

/* Status badges — matches the vocabulary from CardsView. */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.25rem 0.55rem;
  border-radius: var(--radius-pill);
  font-size: 0.7rem;
  font-weight: 600;
  background: var(--surface-muted);
  color: var(--text-secondary);
  flex-shrink: 0;
}
.status-dot {
  width: 0.4rem;
  height: 0.4rem;
  border-radius: 50%;
  background: currentColor;
  display: inline-block;
}
.status-badge--open { background: var(--accent-soft); color: var(--accent-strong); }
.status-badge--pending { background: rgba(180, 83, 9, 0.14); color: var(--warning-fg); }
.status-badge--resolved { background: color-mix(in srgb, var(--success-fg) 18%, transparent); color: var(--success-fg); }
.status-badge--closed { background: var(--surface-muted); color: var(--text-muted); }

/* Detail view */
.detail-status {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-2);
  padding: 0 var(--space-1);
}
.detail-updated {
  font-size: 0.75rem;
  color: var(--text-muted);
}
.thread {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-card);
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
  max-height: 26rem;
  overflow-y: auto;
}
.thread-empty {
  color: var(--text-muted);
  font-size: 0.85rem;
  margin: 0;
  text-align: center;
  padding: var(--space-4);
}
.bubble-row {
  display: flex;
  justify-content: flex-start;
}
.bubble-row--self { justify-content: flex-end; }
.bubble {
  max-width: 85%;
  padding: 0.65rem 0.85rem;
  border-radius: var(--radius-md);
  background: var(--surface-muted);
  color: var(--text-primary);
  display: flex;
  flex-direction: column;
  gap: 0.2rem;
}
.bubble--self {
  background: var(--accent-soft);
  color: var(--text-primary);
}
.bubble-sender {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--text-secondary);
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.bubble--self .bubble-sender { color: var(--accent-strong); }
.bubble-body {
  font-size: 0.9rem;
  margin: 0;
  line-height: 1.45;
  white-space: pre-wrap;
  word-break: break-word;
}
.bubble-time {
  font-size: 0.7rem;
  color: var(--text-muted);
  margin: 0;
  align-self: flex-end;
}

/* Reply composer */
.composer {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-3);
  box-shadow: var(--shadow-card);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.composer-input {
  width: 100%;
  padding: 0.75rem 0.85rem;
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
  background: var(--surface-muted);
  color: var(--text-primary);
  font-family: inherit;
  font-size: 0.9rem;
  resize: vertical;
  outline: none;
  transition: border-color 0.15s ease, box-shadow 0.15s ease, background-color 0.15s ease;
}
.composer-input:focus {
  border-color: var(--accent);
  background: var(--surface);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent);
}
.composer-input:disabled { opacity: 0.6; cursor: not-allowed; }
.composer-send {
  align-self: flex-end;
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.55rem 1rem;
  border-radius: var(--radius-pill);
  border: none;
  background: var(--btn-primary-bg);
  color: var(--btn-primary-fg);
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  transition: transform 0.08s ease, opacity 0.15s ease;
}
.composer-send:hover:not(:disabled) { opacity: 0.9; }
.composer-send:active:not(:disabled) { transform: scale(0.98); }
.composer-send:disabled { opacity: 0.5; cursor: not-allowed; }
.composer-send-icon { width: 0.95rem; height: 0.95rem; }
.closed-note {
  font-size: 0.8rem;
  color: var(--text-muted);
  text-align: center;
  margin: 0;
  padding: var(--space-3);
  background: var(--surface);
  border-radius: var(--radius-md);
  box-shadow: var(--shadow-card);
}

/* Skeleton */
.skeleton-group { display: flex; flex-direction: column; gap: var(--space-3); }
.skeleton-row {
  height: 6rem;
  border-radius: var(--radius-lg);
  background: var(--surface-muted);
  animation: pulse 1.6s ease-in-out infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 0.85; }
}

/* Modal */
.modal {
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: flex-end;
  justify-content: center;
  padding: var(--space-3);
}
@media (min-width: 640px) {
  .modal { align-items: center; }
}
.modal-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(10, 10, 20, 0.55);
  border: none;
  padding: 0;
  cursor: pointer;
}
.modal-panel {
  position: relative;
  width: 100%;
  max-width: 28rem;
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-5);
  box-shadow: var(--shadow-lift);
  z-index: 1;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
  max-height: calc(100vh - 2rem);
  overflow-y: auto;
}
.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
}
.modal-title {
  font-size: 1.15rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}
.modal-close {
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border);
  background: transparent;
  color: var(--text-secondary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: color 0.15s ease, background-color 0.15s ease;
}
.modal-close:hover { color: var(--text-primary); background: color-mix(in srgb, var(--text-primary) 5%, transparent); }
.modal-close-icon { width: 1rem; height: 1rem; }
.modal-form { display: flex; flex-direction: column; gap: var(--space-4); }
.modal-actions {
  display: flex;
  gap: var(--space-3);
  justify-content: flex-end;
}
.btn {
  padding: 0.65rem 1.1rem;
  border-radius: var(--radius-pill);
  font-weight: 600;
  font-size: 0.85rem;
  cursor: pointer;
  border: 1px solid transparent;
  transition: opacity 0.15s ease, transform 0.08s ease;
}
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn--ghost {
  background: transparent;
  color: var(--text-secondary);
  border-color: var(--border);
}
.btn--ghost:hover:not(:disabled) { color: var(--text-primary); }
.btn--primary {
  background: var(--btn-primary-bg);
  color: var(--btn-primary-fg);
}
.btn--primary:hover:not(:disabled) { opacity: 0.9; }
.btn--primary:active:not(:disabled) { transform: scale(0.98); }

/* Icon-prefixed inputs — mirrors the auth-input polish. */
.input-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.input-icon {
  position: absolute;
  left: 0.85rem;
  top: 50%;
  transform: translateY(-50%);
  width: 1.1rem;
  height: 1.1rem;
  color: var(--text-secondary);
  pointer-events: none;
}
.input {
  width: 100%;
  padding: 0.75rem 0.9rem;
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
  background: var(--surface-muted);
  color: var(--text-primary);
  font-size: 0.9rem;
  font-family: inherit;
  outline: none;
  transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
}
.input:focus {
  border-color: var(--accent);
  background: var(--surface);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent);
}
.input--with-icon { padding-left: 2.65rem; }
.input--textarea { resize: vertical; min-height: 6rem; }
select.input {
  appearance: none;
  background-image: linear-gradient(45deg, transparent 50%, var(--text-secondary) 50%),
                    linear-gradient(135deg, var(--text-secondary) 50%, transparent 50%);
  background-position: calc(100% - 1.15rem) 55%, calc(100% - 0.7rem) 55%;
  background-size: 0.35rem 0.35rem, 0.35rem 0.35rem;
  background-repeat: no-repeat;
  padding-right: 2rem;
}
</style>
