<script setup>
import { onMounted, ref, computed } from 'vue'
import { RouterLink } from 'vue-router'
import { ChevronLeftIcon, ListBulletIcon } from '@heroicons/vue/24/outline'
import { useRouter } from 'vue-router'
import client from '../api/client'

const router = useRouter()

// ─── State ────────────────────────────────────────────────────────────────────
const loading       = ref(true)
const error         = ref('')
const transactions  = ref([])
const activeTab     = ref('All')

const TABS = ['All', 'Credits', 'Debits', 'Wire Transfers', 'Domestic', 'Withdrawals']

// ─── Helpers ──────────────────────────────────────────────────────────────────
const formatMoney = (currency, amount) =>
  `${currency || '$'}${Number(amount || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })}`

const txInitials = (tx) => {
  const name = tx.description || tx.sender_name || tx.type_label || '?'
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('')
}

const txDate = (tx) => {
  const raw = tx.created_at || tx.date || ''
  if (!raw) return ''
  const d = new Date(raw.replace(' ', 'T'))
  if (isNaN(d.getTime())) return raw
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

const statusBadgeClass = (statusLabel = '') => {
  const s = statusLabel.toLowerCase()
  if (s.includes('complet') || s.includes('approv') || s.includes('success'))
    return 'bg-emerald-500/20 text-emerald-400'
  if (s.includes('hold') || s.includes('pending') || s.includes('process'))
    return 'bg-amber-500/20 text-amber-400'
  if (s.includes('reject') || s.includes('fail') || s.includes('cancel'))
    return 'bg-red-500/20 text-red-400'
  return 'bg-slate-700 text-slate-300'
}

// ─── Filtered rows ────────────────────────────────────────────────────────────
const filteredRows = computed(() => {
  const tab = activeTab.value
  if (tab === 'All')            return transactions.value
  if (tab === 'Credits')        return transactions.value.filter((t) => t.trans_type === '1')
  if (tab === 'Debits')         return transactions.value.filter((t) => t.trans_type === '2')
  if (tab === 'Wire Transfers')
    return transactions.value.filter((t) =>
      String(t.type_label || '').toLowerCase().includes('wire')
    )
  if (tab === 'Domestic')
    return transactions.value.filter((t) =>
      String(t.type_label || '').toLowerCase().includes('domestic')
    )
  if (tab === 'Withdrawals')
    return transactions.value.filter((t) =>
      String(t.type_label || '').toLowerCase().includes('withdraw')
    )
  return transactions.value
})

// ─── Data fetch ───────────────────────────────────────────────────────────────
const loadTransactions = async () => {
  loading.value = true
  error.value   = ''
  try {
    const { data } = await client.get('/api/user/transactions.php?limit=50')
    if (!data?.ok && data?.ok !== undefined) throw new Error(data?.message || 'Failed to load transactions')
    transactions.value = data?.data || []
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'An error occurred'
  } finally {
    loading.value = false
  }
}

onMounted(loadTransactions)
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
          <h1 class="text-xl font-semibold text-white">Activity</h1>
          <p class="text-slate-400 text-sm">All account movement</p>
        </div>
      </div>
    </div>

    <!-- ── FILTER TABS ─────────────────────────────────────────────────────────── -->
    <div class="flex gap-2 px-4 mt-4 overflow-x-auto no-scrollbar pb-2">
      <button
        v-for="tab in TABS"
        :key="tab"
        type="button"
        class="rounded-full px-4 py-2 text-sm font-medium whitespace-nowrap transition"
        :class="activeTab === tab
          ? 'bg-blue-600 text-white'
          : 'bg-[#1a2436] text-slate-400 border border-[#1e2d44]'"
        @click="activeTab = tab"
      >
        {{ tab }}
      </button>
    </div>

    <!-- ── SKELETON ─────────────────────────────────────────────────────────────── -->
    <template v-if="loading">
      <div class="px-4 mt-3 space-y-2">
        <div v-for="i in 6" :key="i" class="h-16 rounded-2xl bg-[#111827] animate-pulse" />
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
      <!-- Record count -->
      <p class="text-slate-500 text-xs px-4 pb-2 mt-3">
        Showing {{ filteredRows.length }} record{{ filteredRows.length !== 1 ? 's' : '' }}
      </p>

      <!-- Empty state -->
      <div
        v-if="!filteredRows.length"
        class="px-4 mt-4 flex flex-col items-center gap-3 py-16"
      >
        <ListBulletIcon class="h-10 w-10 text-slate-600" />
        <p class="text-slate-500 text-sm">No transactions found</p>
      </div>

      <!-- Transaction list -->
      <div v-else class="px-4 mt-1 space-y-1">
        <div
          v-for="tx in filteredRows"
          :key="tx.trans_id"
          class="bg-[#111827] rounded-2xl px-4 py-4 flex items-center gap-3"
        >
          <!-- Icon circle -->
          <div
            class="h-10 w-10 rounded-full flex items-center justify-center shrink-0"
            :class="tx.trans_type === '1' ? 'bg-emerald-500/20' : 'bg-red-500/20'"
          >
            <span
              class="text-sm font-semibold"
              :class="tx.trans_type === '1' ? 'text-emerald-400' : 'text-red-400'"
            >
              {{ txInitials(tx) }}
            </span>
          </div>

          <!-- Description + meta -->
          <div class="flex-1 min-w-0">
            <p class="text-sm font-semibold text-white truncate">
              {{ tx.description || tx.sender_name || 'Account transaction' }}
            </p>
            <div class="flex items-center gap-2 mt-0.5">
              <p class="text-xs text-slate-500">{{ txDate(tx) }}</p>
              <span
                v-if="tx.status_label"
                class="rounded-full px-1.5 py-0.5 text-[10px] font-medium"
                :class="statusBadgeClass(tx.status_label)"
              >
                {{ tx.status_label }}
              </span>
            </div>
          </div>

          <!-- Amount -->
          <span
            class="text-sm font-semibold shrink-0"
            :class="tx.trans_type === '1' ? 'text-emerald-400' : 'text-red-400'"
          >
            {{ tx.trans_type === '1' ? '+' : '-' }}{{ formatMoney(tx.currency, tx.amount) }}
          </span>
        </div>
      </div>
    </template>

  </div>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
