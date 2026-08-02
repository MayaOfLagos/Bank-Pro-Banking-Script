<script setup>
import { ref, onMounted } from 'vue'
import client from '../api/client'

const loading = ref(true)
const error = ref('')
const tickets = ref([])

// New ticket form
const formSubject = ref('')
const formMessage = ref('')
const formSubmitting = ref(false)
const formError = ref('')
const formSuccess = ref(false)

function statusBadgeClass(status) {
  const s = String(status ?? '').toLowerCase()
  if (s === 'open') return 'bg-blue-500/20 text-blue-400'
  if (s === 'replied') return 'bg-emerald-500/20 text-emerald-400'
  if (s === 'closed') return 'bg-slate-500/20 text-slate-400'
  return 'bg-slate-500/20 text-slate-400'
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  try {
    return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
  } catch {
    return dateStr
  }
}

async function loadTickets() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/api/user/tickets.php')
    if (!data?.ok) throw new Error(data?.message || 'Unable to load tickets')
    tickets.value = data.data.tickets ?? []
  } catch (err) {
    error.value = err?.response?.data?.message || err.message
  } finally {
    loading.value = false
  }
}

async function handleCreateTicket() {
  if (!formSubject.value.trim() || !formMessage.value.trim()) return
  formSubmitting.value = true
  formError.value = ''
  formSuccess.value = false
  try {
    const { data } = await client.post('/api/user/tickets.php', {
      action: 'create',
      subject: formSubject.value.trim(),
      message: formMessage.value.trim()
    })
    if (!data?.ok) throw new Error(data?.message || 'Failed to create ticket.')
    formSuccess.value = true
    formSubject.value = ''
    formMessage.value = ''
    await loadTickets()
    setTimeout(() => { formSuccess.value = false }, 3500)
  } catch (err) {
    formError.value = err?.response?.data?.message || err.message
  } finally {
    formSubmitting.value = false
  }
}

onMounted(loadTickets)
</script>

<template>
  <div class="min-h-screen bg-[#080d18] pb-28">
    <!-- Header -->
    <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-6">
      <p class="text-slate-400 text-sm">Tickets & help</p>
      <h1 class="text-2xl font-bold text-white mt-1">Support</h1>
    </div>

    <!-- New ticket form (always visible) -->
    <div class="bg-[#111827] rounded-2xl mx-4 mt-4 p-4">
      <h2 class="text-white font-semibold text-sm mb-3 flex items-center gap-2">
        <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
        </svg>
        New Ticket
      </h2>

      <div class="space-y-3">
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">Subject</label>
          <input
            v-model="formSubject"
            type="text"
            placeholder="Brief description of your issue"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
          />
        </div>

        <div>
          <label class="block text-xs text-slate-400 mb-1.5">Message</label>
          <textarea
            v-model="formMessage"
            rows="3"
            placeholder="Describe your issue in detail..."
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm resize-none"
          ></textarea>
        </div>

        <div v-if="formError" class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl px-4 py-3">
          {{ formError }}
        </div>

        <div v-if="formSuccess" class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm rounded-xl px-4 py-3">
          Ticket submitted successfully. Our team will respond shortly.
        </div>

        <button
          @click="handleCreateTicket"
          :disabled="formSubmitting || !formSubject.trim() || !formMessage.trim()"
          class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-3.5 transition disabled:opacity-50 disabled:cursor-not-allowed text-sm"
        >
          {{ formSubmitting ? 'Submitting...' : 'Submit Ticket' }}
        </button>
      </div>
    </div>

    <!-- Skeleton for ticket list -->
    <template v-if="loading">
      <div class="px-4 mt-4 space-y-3">
        <div v-for="i in 3" :key="i" class="h-32 rounded-2xl bg-[#111827] animate-pulse"></div>
      </div>
    </template>

    <!-- Error -->
    <div v-else-if="error" class="mx-4 mt-4 bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-2xl px-5 py-4">
      {{ error }}
    </div>

    <template v-else>
      <!-- Ticket list -->
      <div class="px-4 mt-4 space-y-3">
        <div
          v-for="ticket in tickets"
          :key="ticket.ticket_id"
          class="bg-[#111827] rounded-2xl p-4"
        >
          <!-- Ticket header -->
          <div class="flex items-start justify-between gap-3">
            <div class="min-w-0 flex-1">
              <p class="text-white font-semibold text-sm leading-snug truncate">{{ ticket.subject }}</p>
              <p class="text-slate-500 text-xs mt-0.5">{{ formatDate(ticket.created_at) }}</p>
            </div>
            <span class="flex-shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="statusBadgeClass(ticket.status)">
              {{ ticket.status }}
            </span>
          </div>

          <!-- Message preview -->
          <p class="text-slate-400 text-sm mt-2 line-clamp-2 leading-relaxed">{{ ticket.message }}</p>

          <!-- Reply section -->
          <div v-if="ticket.reply" class="mt-3 border-t border-[#1e2d44] pt-3">
            <p class="text-xs text-slate-500 mb-1 flex items-center gap-1">
              <svg class="w-3 h-3 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M7.707 3.293a1 1 0 010 1.414L5.414 7H11a7 7 0 017 7v2a1 1 0 11-2 0v-2a5 5 0 00-5-5H5.414l2.293 2.293a1 1 0 11-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
              </svg>
              <span class="text-emerald-400">Support reply</span>
            </p>
            <p class="text-slate-300 text-sm leading-relaxed">{{ ticket.reply }}</p>
          </div>
        </div>

        <!-- Empty state -->
        <div v-if="!tickets.length" class="bg-[#111827] rounded-2xl p-8 text-center">
          <div class="h-14 w-14 rounded-2xl bg-blue-600/20 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
            </svg>
          </div>
          <h3 class="text-white font-semibold">No tickets yet</h3>
          <p class="text-slate-400 text-sm mt-2 leading-relaxed">Submit your first support ticket using the form above.</p>
        </div>
      </div>
    </template>
  </div>
</template>
