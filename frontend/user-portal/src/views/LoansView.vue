<script setup>
import { ref, computed, onMounted } from 'vue'
import client from '../api/client'

const loading = ref(true)
const error = ref('')
const loans = ref([])
const canRequest = ref(false)

const showModal = ref(false)
const modalAmount = ref('')
const modalDescription = ref('')
const submitting = ref(false)
const submitError = ref('')
const submitSuccess = ref(false)

const totalLoans = computed(() => loans.value.length)
const pendingLoans = computed(() => loans.value.filter(l => String(l.status).toLowerCase().includes('pending')).length)

function statusBadgeClass(status) {
  const s = String(status ?? '').toLowerCase()
  if (s.includes('approved')) return 'bg-emerald-500/20 text-emerald-400'
  if (s.includes('pending')) return 'bg-blue-500/20 text-blue-400'
  if (s.includes('rejected') || s.includes('denied') || s.includes('declined')) return 'bg-red-500/20 text-red-400'
  return 'bg-slate-500/20 text-slate-400'
}

function formatMoney(value) {
  return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

async function loadLoans() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/api/user/loans.php')
    if (!data?.ok) throw new Error(data?.message || 'Unable to load loans')
    loans.value = data.data.loans ?? []
    canRequest.value = Boolean(data.data.can_request)
  } catch (err) {
    error.value = err?.response?.data?.message || err.message
  } finally {
    loading.value = false
  }
}

async function submitLoanRequest() {
  if (!modalAmount.value) return
  submitting.value = true
  submitError.value = ''
  submitSuccess.value = false
  try {
    const { data } = await client.post('/api/user/loans-submit.php', {
      amount: modalAmount.value,
      loan_remarks: modalDescription.value
    })
    if (!data?.ok) throw new Error(data?.message || 'Loan request failed.')
    submitSuccess.value = true
    modalAmount.value = ''
    modalDescription.value = ''
    await loadLoans()
    setTimeout(() => {
      showModal.value = false
      submitSuccess.value = false
    }, 1800)
  } catch (err) {
    submitError.value = err?.response?.data?.message || err.message
  } finally {
    submitting.value = false
  }
}

function openModal() {
  submitError.value = ''
  submitSuccess.value = false
  modalAmount.value = ''
  modalDescription.value = ''
  showModal.value = true
}

onMounted(loadLoans)
</script>

<template>
  <div class="min-h-screen bg-[#080d18] pb-28">
    <!-- Header -->
    <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-6">
      <p class="text-slate-400 text-sm">Loan & mortgage requests</p>
      <h1 class="text-2xl font-bold text-white mt-1">Loans</h1>
    </div>

    <!-- Skeleton -->
    <template v-if="loading">
      <div class="grid grid-cols-2 gap-3 px-4 mt-4">
        <div class="h-24 rounded-2xl bg-[#111827] animate-pulse"></div>
        <div class="h-24 rounded-2xl bg-[#111827] animate-pulse"></div>
      </div>
      <div class="px-4 mt-4 space-y-3">
        <div v-for="i in 3" :key="i" class="h-28 rounded-2xl bg-[#111827] animate-pulse"></div>
      </div>
    </template>

    <!-- Error -->
    <div v-else-if="error" class="mx-4 mt-4 bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-2xl px-5 py-4">
      {{ error }}
    </div>

    <template v-else>
      <!-- Stats row -->
      <div class="grid grid-cols-2 gap-3 px-4 mt-4">
        <div class="bg-[#111827] rounded-2xl p-4">
          <p class="text-slate-400 text-xs mb-1.5">Total loans</p>
          <p class="text-white text-2xl font-bold">{{ totalLoans }}</p>
          <p class="text-slate-500 text-xs mt-1">All time applications</p>
        </div>
        <div class="bg-[#111827] rounded-2xl p-4">
          <p class="text-slate-400 text-xs mb-1.5">Pending</p>
          <p class="text-blue-400 text-2xl font-bold">{{ pendingLoans }}</p>
          <p class="text-slate-500 text-xs mt-1">Awaiting review</p>
        </div>
      </div>

      <!-- Request button -->
      <div v-if="canRequest" class="px-4 mt-4">
        <button
          @click="openModal"
          class="bg-blue-600 text-white rounded-2xl px-5 py-4 w-full font-semibold hover:bg-blue-700 transition flex items-center justify-center gap-2"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
          </svg>
          Request a Loan
        </button>
      </div>

      <!-- Loan list -->
      <div class="px-4 mt-4 space-y-3">
        <div
          v-for="loan in loans"
          :key="loan.loan_id"
          class="bg-[#111827] rounded-2xl p-4"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="text-xl font-bold text-white">{{ loan.currency }}{{ formatMoney(loan.amount) }}</p>
              <p class="text-slate-400 text-xs mt-0.5">{{ loan.created_at || 'Recently submitted' }}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold capitalize" :class="statusBadgeClass(loan.status)">
              {{ loan.status }}
            </span>
          </div>

          <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1">
            <p v-if="loan.interest_rate" class="text-slate-400 text-sm">
              <span class="text-slate-500">Rate:</span> {{ loan.interest_rate }}%
            </p>
            <p v-if="loan.duration" class="text-slate-400 text-sm">
              <span class="text-slate-500">Duration:</span> {{ loan.duration }}
            </p>
          </div>

          <p v-if="loan.loan_message" class="text-slate-400 text-sm mt-2 italic leading-relaxed">
            "{{ loan.loan_message }}"
          </p>
        </div>

        <!-- Empty state -->
        <div v-if="!loans.length" class="bg-[#111827] rounded-2xl p-8 text-center">
          <div class="h-14 w-14 rounded-2xl bg-blue-600/20 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
            </svg>
          </div>
          <h3 class="text-white font-semibold">No loan applications</h3>
          <p class="text-slate-400 text-sm mt-2 leading-relaxed">You haven't submitted any loan or mortgage requests yet.</p>
        </div>
      </div>
    </template>

    <!-- Loan Request Modal -->
    <Teleport to="body">
      <div
        v-if="showModal"
        class="fixed inset-0 z-50 flex items-end justify-center"
        @click.self="showModal = false"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="showModal = false"></div>

        <!-- Sheet -->
        <div class="relative w-full max-w-lg bg-[#111827] rounded-t-3xl p-6 pb-10 z-10">
          <!-- Handle -->
          <div class="w-10 h-1 rounded-full bg-[#1e2d44] mx-auto mb-5"></div>

          <h2 class="text-white font-bold text-xl mb-5">Request a Loan</h2>

          <div class="space-y-4">
            <div>
              <label class="block text-xs text-slate-400 mb-1.5">Loan Amount</label>
              <input
                v-model="modalAmount"
                type="number"
                min="1"
                step="0.01"
                placeholder="0.00"
                class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
              />
            </div>

            <div>
              <label class="block text-xs text-slate-400 mb-1.5">
                Purpose / Description
                <span class="text-slate-600 ml-1">(optional)</span>
              </label>
              <textarea
                v-model="modalDescription"
                rows="3"
                placeholder="Briefly describe the purpose of this loan"
                class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm resize-none"
              ></textarea>
            </div>

            <div v-if="submitError" class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl px-4 py-3">
              {{ submitError }}
            </div>

            <div v-if="submitSuccess" class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm rounded-xl px-4 py-3">
              Loan request submitted successfully!
            </div>

            <button
              @click="submitLoanRequest"
              :disabled="submitting || !modalAmount"
              class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-4 transition disabled:opacity-50 disabled:cursor-not-allowed"
            >
              {{ submitting ? 'Submitting...' : 'Submit Request' }}
            </button>

            <button
              @click="showModal = false"
              class="w-full bg-[#1a2436] border border-[#1e2d44] text-white font-semibold rounded-2xl py-4 transition hover:bg-[#1e2d44]"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
