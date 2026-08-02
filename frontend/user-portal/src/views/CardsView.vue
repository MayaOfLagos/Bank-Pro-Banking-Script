<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '../api/client'

const loading = ref(true)
const error = ref('')
const requesting = ref(false)
const requestError = ref('')
const requestSuccess = ref(false)
const requestType = ref('VISA')
const requestReason = ref('Virtual card for online payments')

const cardData = ref({ card: null, can_request: false })

const card = computed(() => cardData.value.card)
const canRequest = computed(() => cardData.value.can_request)
const hasRequestInProgress = computed(() => cardData.value.has_request_in_progress)

const last4 = computed(() => {
  return card.value?.last4 || '••••'
})

const maskedNumber = computed(() => {
  return card.value?.masked_number || '•••• •••• •••• ••••'
})

const statusColor = computed(() => {
  const s = String(card.value?.card_status ?? '').toLowerCase()
  if (s === 'active') return 'text-emerald-400'
  if (s === 'hold' || s === 'paused') return 'text-red-400'
  if (s === 'processing') return 'text-amber-400'
  return 'text-slate-400'
})

async function loadCards() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/api/user/card.php')
    if (!data?.ok) throw new Error(data?.message || 'Unable to load card data')
    cardData.value = data.data
  } catch (err) {
    error.value = err?.response?.data?.message || err.message
  } finally {
    loading.value = false
  }
}

async function requestCard() {
  requesting.value = true
  requestError.value = ''
  requestSuccess.value = false
  try {
    const { data } = await client.post('/api/user/card-request.php', {
      card_type: requestType.value,
      card_reason: requestReason.value.trim()
    })
    if (!data?.ok) throw new Error(data?.message || 'Card request failed.')
    requestSuccess.value = true
    await loadCards()
  } catch (err) {
    requestError.value = err?.response?.data?.message || err.message
  } finally {
    requesting.value = false
  }
}

onMounted(loadCards)
</script>

<template>
  <div class="min-h-screen bg-[#080d18] pb-28">
    <!-- Header -->
    <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-6">
      <p class="text-slate-400 text-sm">Virtual card management</p>
      <h1 class="text-2xl font-bold text-white mt-1">Cards</h1>
    </div>

    <!-- Skeleton -->
    <template v-if="loading">
      <div class="mx-4 mt-4 h-52 rounded-3xl bg-[#111827] animate-pulse"></div>
      <div class="mx-4 mt-4 h-36 rounded-2xl bg-[#111827] animate-pulse"></div>
    </template>

    <!-- Error -->
    <div v-else-if="error" class="mx-4 mt-4 bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-2xl px-5 py-4">
      {{ error }}
    </div>

    <template v-else>
      <!-- Card exists -->
      <template v-if="card">
        <!-- Visual card -->
        <div class="mx-4 mt-4 rounded-3xl bg-gradient-to-br from-blue-600 via-blue-700 to-[#0d1b38] p-6 shadow-2xl shadow-blue-900/50">
          <div class="flex justify-between items-start">
            <span class="text-white/70 text-sm font-medium">{{ card.card_name || 'Virtual Card' }}</span>
            <span class="text-white font-bold text-lg tracking-wide">{{ card.card_type }}</span>
          </div>

          <!-- Chip icon -->
          <div class="mt-6">
            <svg class="w-10 h-8 text-yellow-300/80" viewBox="0 0 40 30" fill="currentColor">
              <rect x="0" y="0" width="40" height="30" rx="4" fill="currentColor" opacity="0.3"/>
              <rect x="14" y="0" width="12" height="30" fill="currentColor" opacity="0.4"/>
              <rect x="0" y="10" width="40" height="10" fill="currentColor" opacity="0.4"/>
            </svg>
          </div>

          <p class="text-white text-xl font-mono mt-4 tracking-[0.2em]">•••• •••• •••• {{ last4 }}</p>

          <div class="flex justify-between mt-6">
            <div>
              <p class="text-white/50 text-xs uppercase tracking-wider">Expires</p>
              <p class="text-white text-sm font-medium mt-0.5">{{ card.expiry || '—' }}</p>
            </div>
            <div>
              <p class="text-white/50 text-xs uppercase tracking-wider">CVV</p>
              <p class="text-white text-sm font-medium mt-0.5">•••</p>
            </div>
            <div>
              <p class="text-white/50 text-xs uppercase tracking-wider">Status</p>
              <p class="text-sm font-medium mt-0.5 capitalize" :class="statusColor">{{ card.card_status }}</p>
            </div>
          </div>
        </div>

        <!-- Card details list -->
        <div class="bg-[#111827] rounded-2xl mx-4 mt-4 divide-y divide-[#1e2d44]">
          <div class="px-4 py-3.5 flex justify-between items-center">
            <span class="text-slate-400 text-sm">Card Type</span>
            <span class="text-white text-sm font-medium">{{ card.card_type || '—' }}</span>
          </div>
          <div class="px-4 py-3.5 flex justify-between items-center">
            <span class="text-slate-400 text-sm">Status</span>
            <span class="text-sm font-medium capitalize" :class="statusColor">{{ card.card_status || '—' }}</span>
          </div>
          <div class="px-4 py-3.5 flex justify-between items-center">
            <span class="text-slate-400 text-sm">Card Number</span>
            <span class="text-white text-sm font-medium font-mono">{{ maskedNumber }}</span>
          </div>
          <div v-if="card.expiry" class="px-4 py-3.5 flex justify-between items-center">
            <span class="text-slate-400 text-sm">Expiry Date</span>
            <span class="text-white text-sm font-medium">{{ card.expiry }}</span>
          </div>
        </div>

        <!-- Info note -->
        <p class="text-slate-500 text-xs text-center mt-4 px-8 leading-relaxed">
          For security, your full card number and CVV are not displayed. Contact support to retrieve sensitive card details.
        </p>
      </template>

      <!-- No card empty state -->
      <template v-else>
        <div class="mx-4 mt-4 bg-[#111827] rounded-2xl p-8 text-center">
          <div class="h-16 w-16 rounded-2xl bg-blue-600/20 flex items-center justify-center mx-auto mb-5">
            <svg class="w-8 h-8 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z" />
            </svg>
          </div>
          <h3 class="text-white font-semibold text-lg">No card yet</h3>
          <p class="text-slate-400 text-sm mt-2 leading-relaxed max-w-xs mx-auto">Request a virtual card to start spending and managing your transactions online.</p>

          <!-- Request success -->
          <div v-if="requestSuccess" class="mt-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm rounded-xl px-4 py-3">
            Card request submitted successfully. We'll review and issue your card shortly.
          </div>

          <!-- Request error -->
          <div v-if="requestError" class="mt-4 bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl px-4 py-3">
            {{ requestError }}
          </div>

          <div v-if="canRequest && !requestSuccess" class="mt-5 space-y-3 text-left">
            <div>
              <label class="block text-xs text-slate-400 mb-1.5">Preferred card type</label>
              <select
                v-model="requestType"
                class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white focus:outline-none focus:border-blue-500 text-sm"
              >
                <option value="VISA">Visa</option>
                <option value="MASTERCARD">Mastercard</option>
              </select>
            </div>
            <div>
              <label class="block text-xs text-slate-400 mb-1.5">Reason</label>
              <textarea
                v-model="requestReason"
                rows="3"
                maxlength="500"
                class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm resize-none"
              ></textarea>
            </div>
          </div>

          <button
            v-if="canRequest && !requestSuccess"
            @click="requestCard"
            :disabled="requesting || requestReason.trim().length < 5"
            class="mt-5 w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-4 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ requesting ? 'Requesting...' : 'Request a card' }}
          </button>

          <p v-if="!canRequest && !requestSuccess" class="mt-4 text-slate-500 text-xs">
            {{ hasRequestInProgress ? 'Your card request is being reviewed.' : 'Card requests are unavailable for this account. Contact support for assistance.' }}
          </p>
        </div>
      </template>
    </template>
  </div>
</template>
