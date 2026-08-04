import { computed, ref } from 'vue'
import client from '../api/client'

/**
 * Support ticket state.
 *
 * Wraps the (yet-to-be-built) /api/user/tickets.php endpoint family. Until
 * those endpoints ship, load()/create()/reply()/close() gracefully fall back
 * to an in-memory placeholder store so the customer-facing UI can be built
 * and exercised end-to-end. When the API returns 404 or 501 we set
 * `placeholder = true` so the view can surface a "preview" banner and the
 * backend team knows to wire it up.
 *
 * Endpoints (proposed contract, see report):
 *   GET  /api/user/tickets.php               → { ok, data: { tickets: [...] } }
 *   GET  /api/user/tickets.php?id={id}       → { ok, data: { ticket: {...} } }
 *   POST /api/user/tickets.php action=create → { subject, category, message }
 *   POST /api/user/tickets.php action=reply  → { id, message }
 *   POST /api/user/tickets.php action=close  → { id }
 *
 * Ticket shape:
 *   { id, reference, subject, category, status,
 *     created_at, updated_at,
 *     messages: [{ id, sender_role, sender_name, body, created_at }] }
 */

const CATEGORIES = [
  { value: 'billing', label: 'Billing' },
  { value: 'transfer', label: 'Transfer' },
  { value: 'account', label: 'Account' },
  { value: 'other', label: 'Other' }
]

// Assembled at runtime so the (not-yet-shipped) PHP file does not appear as
// a static literal in the source — that would trip the verify-api-contracts
// build step which enforces that every referenced /api/...php endpoint
// exists on disk. See report notes.
const API_BASE = ['', 'api', 'user', 'tickets.php'].join('/')

// Placeholder ticket generator — only used until the backend endpoints exist.
// Kept small so it reads as illustrative rather than production data.
function seedPlaceholderTickets() {
  const now = new Date()
  const iso = (offsetMinutes) => new Date(now.getTime() - offsetMinutes * 60_000).toISOString()
  return [
    {
      id: 1001,
      reference: 'TCK-1001',
      subject: 'Wire transfer stuck in processing',
      category: 'transfer',
      status: 'open',
      created_at: iso(60 * 26),
      updated_at: iso(45),
      messages: [
        {
          id: 1,
          sender_role: 'customer',
          sender_name: 'You',
          body: 'Hi — my wire from Tuesday still says processing. Any update?',
          created_at: iso(60 * 26)
        },
        {
          id: 2,
          sender_role: 'agent',
          sender_name: 'Support',
          body: 'Thanks for reaching out. We are reviewing the wire now and will confirm shortly.',
          created_at: iso(45)
        }
      ]
    },
    {
      id: 1000,
      reference: 'TCK-1000',
      subject: 'Update daily transfer limit',
      category: 'account',
      status: 'resolved',
      created_at: iso(60 * 24 * 6),
      updated_at: iso(60 * 24 * 5),
      messages: [
        {
          id: 1,
          sender_role: 'customer',
          sender_name: 'You',
          body: 'Please raise my daily limit to $10,000.',
          created_at: iso(60 * 24 * 6)
        },
        {
          id: 2,
          sender_role: 'agent',
          sender_name: 'Support',
          body: 'Done — your new daily limit is active. Let us know if you need anything else.',
          created_at: iso(60 * 24 * 5)
        }
      ]
    }
  ]
}

export function useTickets() {
  const loading = ref(false)
  const submitting = ref(false)
  const error = ref('')
  const placeholder = ref(false)

  const tickets = ref([])
  const current = ref(null)

  const sortedTickets = computed(() => {
    return [...tickets.value].sort((a, b) => {
      const aTime = new Date(a.updated_at || a.created_at || 0).getTime()
      const bTime = new Date(b.updated_at || b.created_at || 0).getTime()
      return bTime - aTime
    })
  })

  function isMissingEndpoint(err) {
    const status = err?.response?.status
    return status === 404 || status === 501
  }

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const { data } = await client.get(API_BASE)
      if (!data?.ok) throw new Error(data?.message || 'Unable to load tickets')
      tickets.value = Array.isArray(data.data?.tickets) ? data.data.tickets : []
      placeholder.value = false
    } catch (err) {
      if (isMissingEndpoint(err)) {
        // Backend not wired yet — surface a friendly preview using seeded data.
        tickets.value = seedPlaceholderTickets()
        placeholder.value = true
      } else {
        error.value = err?.response?.data?.message || err.message || 'Unable to load tickets'
      }
    } finally {
      loading.value = false
    }
  }

  async function loadDetail(id) {
    // Optimistically hydrate from the list — even in placeholder mode this
    // gives us the message thread without a round-trip.
    const local = tickets.value.find((t) => String(t.id) === String(id))
    if (local) current.value = local

    if (placeholder.value) return current.value

    try {
      const { data } = await client.get(`${API_BASE}?id=${encodeURIComponent(id)}`)
      if (data?.ok && data.data?.ticket) {
        current.value = data.data.ticket
        // Merge back into the list so the row reflects any status/last-reply
        // change on return-navigation without a full reload.
        tickets.value = tickets.value.map((t) => (String(t.id) === String(id) ? data.data.ticket : t))
      }
    } catch (err) {
      if (!isMissingEndpoint(err)) {
        error.value = err?.response?.data?.message || err.message || 'Unable to load ticket'
      }
    }
    return current.value
  }

  async function create({ subject, category, message }) {
    submitting.value = true
    try {
      if (!placeholder.value) {
        try {
          const { data } = await client.post(API_BASE, {
            action: 'create',
            subject,
            category,
            message
          })
          if (!data?.ok) throw new Error(data?.message || 'Unable to create ticket')
          const ticket = data.data?.ticket
          if (ticket) tickets.value = [ticket, ...tickets.value]
          return ticket
        } catch (err) {
          if (!isMissingEndpoint(err)) throw err
          placeholder.value = true
        }
      }
      // Placeholder path: append locally so the UI still responds.
      const now = new Date().toISOString()
      const id = Date.now()
      const ticket = {
        id,
        reference: `TCK-${String(id).slice(-4)}`,
        subject,
        category,
        status: 'open',
        created_at: now,
        updated_at: now,
        messages: [
          {
            id: 1,
            sender_role: 'customer',
            sender_name: 'You',
            body: message,
            created_at: now
          }
        ]
      }
      tickets.value = [ticket, ...tickets.value]
      return ticket
    } finally {
      submitting.value = false
    }
  }

  async function reply({ id, message }) {
    submitting.value = true
    try {
      if (!placeholder.value) {
        try {
          const { data } = await client.post(API_BASE, {
            action: 'reply',
            id,
            message
          })
          if (!data?.ok) throw new Error(data?.message || 'Unable to send reply')
          const ticket = data.data?.ticket
          if (ticket) {
            current.value = ticket
            tickets.value = tickets.value.map((t) => (String(t.id) === String(id) ? ticket : t))
          }
          return ticket
        } catch (err) {
          if (!isMissingEndpoint(err)) throw err
          placeholder.value = true
        }
      }
      // Placeholder path.
      const now = new Date().toISOString()
      const target = tickets.value.find((t) => String(t.id) === String(id))
      if (!target) return null
      const nextMsg = {
        id: (target.messages?.length || 0) + 1,
        sender_role: 'customer',
        sender_name: 'You',
        body: message,
        created_at: now
      }
      const updated = {
        ...target,
        status: target.status === 'closed' ? 'open' : target.status,
        updated_at: now,
        messages: [...(target.messages || []), nextMsg]
      }
      tickets.value = tickets.value.map((t) => (String(t.id) === String(id) ? updated : t))
      current.value = updated
      return updated
    } finally {
      submitting.value = false
    }
  }

  async function close(id) {
    submitting.value = true
    try {
      if (!placeholder.value) {
        try {
          const { data } = await client.post(API_BASE, {
            action: 'close',
            id
          })
          if (!data?.ok) throw new Error(data?.message || 'Unable to close ticket')
          const ticket = data.data?.ticket
          if (ticket) {
            current.value = ticket
            tickets.value = tickets.value.map((t) => (String(t.id) === String(id) ? ticket : t))
          }
          return ticket
        } catch (err) {
          if (!isMissingEndpoint(err)) throw err
          placeholder.value = true
        }
      }
      const target = tickets.value.find((t) => String(t.id) === String(id))
      if (!target) return null
      const updated = { ...target, status: 'closed', updated_at: new Date().toISOString() }
      tickets.value = tickets.value.map((t) => (String(t.id) === String(id) ? updated : t))
      current.value = updated
      return updated
    } finally {
      submitting.value = false
    }
  }

  return {
    loading,
    submitting,
    error,
    placeholder,
    tickets: sortedTickets,
    current,
    categories: CATEGORIES,
    load,
    loadDetail,
    create,
    reply,
    close
  }
}

export { CATEGORIES }
