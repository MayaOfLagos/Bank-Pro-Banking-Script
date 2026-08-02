<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ChevronLeftIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/outline'
import client from '../api/client'

const router = useRouter()

// ─── State ────────────────────────────────────────────────────────────────────
const loading     = ref(true)
const error       = ref('')
const withdrawals = ref([])

// ─── Helpers ──────────────────────────────────────────────────────────────────
const formatMoney = (currency, amount) =>
  `${currency || '$'}${Number(amount || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })}`

const txDate = (row) => {
  const raw = row.created_at || row.date || ''
  if (!raw) return ''
  const d = new Date(raw.replace(' ', 'T'))
  if (isNaN(d.getTime())) return raw
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

const statusLabel = (row) =>
  row.status_label || (row.status === '1' ? 'Approved' : row.status === '2' ? 'Rejected' : 'Pending')

const statusBadgeClass = (row) => {
  const s = statusLabel(row).toLowerCase()
  if (s.includes('approv') || s.includes('complet') || s.includes('success'))
    return 'bg-emerald-500/20 text-emerald-400'
  if (s.includes('reject') || s.includes('fail') || s.includes('cancel'))
    return 'bg-red-500/20 text-red-400'
  return 'bg-amber-500/20 text-amber-400'
}

const descriptionLabel = (row) =>
  row.description ||
  row.bankname ||
  row.withdraw_method ||
  row.method ||
  'Withdrawal'

// ─── Stats ────────────────────────────────────────────────────────────────────
const stats = computed(() => {
  const all      = withdrawals.value
  const total    = all.length
  const pending  = all.filter((r) => {
    const s = statusLabel(r).toLowerCase()
    return s.includes('pending') || s.includes('hold') || s.includes('process')
  }).length
  const approved = all.filter((r) => {
    const s = statusLabel(r).toLowerCase()
    return s.includes('approv') || s.includes('complet') || s.includes('success')
  }).length
  return [
    { label: 'Total',    value: total },
    { label: 'Pending',  value: pending },
    { label: 'Approved', value: approved }
  ]
})

// ─── Data fetch ───────────────────────────────────────────────────────────────
const loadWithdrawals = async () => {
  loading.value = true
  error.value   = ''
  try {
    const { data } = await client.get('/api/user/withdrawals.php')
    if (!data?.ok && data?.ok !== undefined) throw new Error(data?.message || 'Failed to load withdrawals')
    withdrawals.value = data?.data || []
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'An error occurred'
  } finally {
    loading.value = false
  }
}

onMounted(loadWithdrawals)
</script>

<template>
  <!-- Full-bleed dark page -->
  <div class="min-h-screen bg-[#080d18] -mx-4 -mt-6 sm:-mx-6 lg:-mx-8 lg:-mt-8 pb-6">

    <!-- ── HEADER ──────────────────────────────────────────────────────────────── -->
    <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-6">
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="h-9 w-9 rounded-full bg-[#1a2436] border border-[#1e2d44] flex items-center justify-center shrink-0"
          @click="router.back()"
        >
          <ChevronLeftIcon class="h-4 w-4 text-white" />
        </button>
        <div>
          <h1 class="text-xl font-semibold text-white">Withdrawals</h1>
          <p class="text-slate-400 text-sm">Cash out requests</p>
        </div>
      </div>
    </div>

    <!-- ── SKELETON ─────────────────────────────────────────────────────────────── -->
    <template v-if="loading">
      <!-- Stats skeleton -->
      <div class="grid grid-cols-3 gap-3 px-4 mt-4">
        <div v-for="i in 3" :key="i" class="h-20 rounded-2xl bg-[#111827] animate-pulse" />
      </div>
      <!-- List skeleton -->
      <div class="px-4 mt-4 space-y-2">
        <div v-for="i in 5" :key="i" class="h-20 rounded-2xl bg-[#111827] animate-pulse" />
      </div>
    </template>

    <!-- ── ERROR ──────────────────────────────────────────────────────────────── -->
    <div
      v-else-if="error"
      class="mx-4 mt-4 rounded-2xl bg-red-500/10 border border-red-500/30 p-5 text-red-400 text-sm"
    >
      {{ error }}
    </div>

    <!-- ── CONTENT ─────────────────────────────────────────────────────────────── -->
    <template v-else>

      <!-- ── Stats row ───────────────────────────────────────────────────────── -->
      <div class="grid grid-cols-3 gap-3 px-4 mt-4">
        <div
          v-for="stat in stats"
          :key="stat.label"
          class="bg-[#111827] rounded-2xl p-4 text-center"
        >
          <p class="text-slate-500 text-xs mb-1">{{ stat.label }}</p>
          <p class="text-xl font-bold text-white">{{ stat.value }}</p>
        </div>
      </div>

      <!-- ── Withdrawal list ─────────────────────────────────────────────────── -->
      <div class="px-4 mt-4 space-y-2">

        <!-- Empty state -->
        <div
          v-if="!withdrawals.length"
          class="bg-[#111827] rounded-2xl px-4 py-16 flex flex-col items-center gap-3"
        >
          <ArrowDownTrayIcon class="h-10 w-10 text-slate-600" />
          <p class="text-slate-500 text-sm">No withdrawal requests yet</p>
        </div>

        <!-- Rows -->
        <div
          v-for="row in withdrawals"
          :key="row.id || row.reference_id"
          class="bg-[#111827] rounded-2xl px-4 py-4"
        >
          <!-- Top row -->
          <div class="flex justify-between items-start">
            <p class="text-sm font-semibold text-white flex-1 min-w-0 pr-3 truncate">
              {{ descriptionLabel(row) }}
            </p>
            <p class="text-sm font-semibold text-white shrink-0">
              {{ formatMoney(row.currency, row.amount) }}
            </p>
          </div>

          <!-- Bottom row -->
          <div class="flex justify-between items-center mt-1">
            <p class="text-xs text-slate-500">{{ txDate(row) }}</p>
            <span
              class="rounded-full px-2 py-0.5 text-xs font-medium"
              :class="statusBadgeClass(row)"
            >
              {{ statusLabel(row) }}
            </span>
          </div>

          <!-- Detail line (account / method / wallet) -->
          <p
            v-if="row.account_number || row.wallet_address || row.acctname"
            class="text-xs text-slate-600 mt-1.5 truncate"
          >
            {{ row.account_number || row.acctname || row.wallet_address || '' }}
          </p>
        </div>
      </div>

    </template>

  </div>
</template>
