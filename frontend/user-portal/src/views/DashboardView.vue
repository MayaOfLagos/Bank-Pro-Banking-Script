<script setup>
import { onMounted, ref, computed } from 'vue'
import { RouterLink, useRouter } from 'vue-router'
import {
  PlusIcon,
  ArrowsRightLeftIcon,
  ListBulletIcon,
  EllipsisHorizontalIcon,
  BanknotesIcon,
  WalletIcon
} from '@heroicons/vue/24/outline'
import client from '../api/client'

const router = useRouter()

// ─── State ────────────────────────────────────────────────────────────────────
const loading = ref(true)
const error   = ref('')
const dashboard = ref({
  currency: '$',
  acct_balance: '0.00',
  avail_balance: '0.00',
  limit_remain: '0.00',
  loan_balance: '0.00',
  profile: { full_name: '', acct_type: '', acct_no: '', image: '' },
  recent_transactions: [],
  counters: { wire_transfers: 0, domestic_transfers: 0, withdrawals: 0, loans: 0 },
  volume: { credit: 0, debit: 0, net: 0 }
})

// ─── Derived ──────────────────────────────────────────────────────────────────
const profile = computed(() => dashboard.value.profile || {})

const avatarSrc = computed(() => {
  const img = profile.value?.image || profile.value?.profile_image || ''
  if (!img) return null
  return img.startsWith('/') ? img : `/assets/profile/${img}`
})

const initials = computed(() => {
  const name = profile.value?.full_name || ''
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('')
})

const balanceParts = computed(() => {
  const raw = String(dashboard.value.acct_balance || '0.00')
  const [whole, cents = '00'] = raw.replace(/,/g, '').split('.')
  const wholeFormatted = Number(whole).toLocaleString()
  return { whole: wholeFormatted, cents: cents.slice(0, 2).padEnd(2, '0') }
})

const accountLabel = computed(() => {
  const type = profile.value?.acct_type || 'Personal'
  const currency = dashboard.value.currency === '$' ? 'USD' : dashboard.value.currency
  return `${type} · ${currency}`
})

const recentTransactions = computed(() =>
  (dashboard.value.recent_transactions || []).slice(0, 5)
)

const formatMoney = (value) =>
  Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })

const txInitials = (tx) => {
  const name = tx.description || tx.sender_name || '??'
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
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
}

// ─── Quick actions ────────────────────────────────────────────────────────────
const quickActions = [
  { icon: PlusIcon,               label: 'Add money',  to: '/wire-transfer' },
  { icon: ArrowsRightLeftIcon,    label: 'Move',       to: '/domestic-transfer' },
  { icon: ListBulletIcon,         label: 'Activity',   to: '/transactions' },
  { icon: EllipsisHorizontalIcon, label: 'More',       to: '/profile' }
]

// ─── Data fetch ───────────────────────────────────────────────────────────────
const loadDashboard = async () => {
  loading.value = true
  error.value   = ''
  try {
    const { data } = await client.get('/api/user/dashboard.php')
    if (!data?.ok) throw new Error(data?.message || 'Failed to load dashboard')
    dashboard.value = { ...dashboard.value, ...data.data }
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'An error occurred'
  } finally {
    loading.value = false
  }
}

onMounted(loadDashboard)
</script>

<template>
  <!-- Root: full-bleed dark page, overrides any shell padding via negative margins -->
  <div class="min-h-screen bg-[#080d18] -mx-4 -mt-6 sm:-mx-6 lg:-mx-8 lg:-mt-8 pb-6">

    <!-- ── SKELETON ─────────────────────────────────────────────────────────── -->
    <template v-if="loading">
      <!-- Hero skeleton -->
      <div class="bg-gradient-to-b from-[#0d1b38] via-[#090e1c] to-[#080d18] px-5 pt-12 pb-6">
        <div class="flex items-center justify-between">
          <div class="h-10 w-10 rounded-full bg-[#1a2436] animate-pulse" />
          <div class="h-4 w-24 rounded-full bg-[#1a2436] animate-pulse" />
        </div>
        <div class="mt-6 mx-auto h-4 w-28 rounded-full bg-[#1a2436] animate-pulse" />
        <div class="mt-3 mx-auto h-14 w-48 rounded-xl bg-[#1a2436] animate-pulse" />
        <div class="mt-5 mx-auto h-9 w-32 rounded-full bg-[#1a2436] animate-pulse" />
        <div class="mt-4 flex justify-center gap-2">
          <div class="h-1.5 w-4 rounded-full bg-white/20 animate-pulse" />
          <div v-for="i in 4" :key="i" class="h-1.5 w-1.5 rounded-full bg-white/10 animate-pulse" />
        </div>
      </div>
      <!-- Actions skeleton -->
      <div class="flex justify-around px-6 py-5">
        <div v-for="i in 4" :key="i" class="flex flex-col items-center gap-2">
          <div class="h-14 w-14 rounded-full bg-[#1a2436] animate-pulse" />
          <div class="h-3 w-12 rounded bg-[#1a2436] animate-pulse" />
        </div>
      </div>
      <!-- Transactions skeleton -->
      <div class="px-4 mt-2 space-y-2">
        <div v-for="i in 5" :key="i" class="h-16 rounded-2xl bg-[#111827] animate-pulse" />
      </div>
    </template>

    <!-- ── ERROR ─────────────────────────────────────────────────────────────── -->
    <div
      v-else-if="error"
      class="mx-4 mt-12 rounded-2xl bg-red-500/10 border border-red-500/30 p-5 text-red-400 text-sm"
    >
      {{ error }}
    </div>

    <!-- ── CONTENT ────────────────────────────────────────────────────────────── -->
    <template v-else>

      <!-- ── SECTION 1: Gradient hero header ─────────────────────────────────── -->
      <div class="bg-gradient-to-b from-[#0d1b38] via-[#0c1628] to-[#080d18] px-5 pt-12 pb-6">

        <!-- Top row: avatar left | greeting right -->
        <div class="flex items-center justify-between">
          <!-- Avatar -->
          <div class="h-10 w-10 rounded-full bg-[#1a2436] border border-[#1e2d44] overflow-hidden flex items-center justify-center shrink-0">
            <img
              v-if="avatarSrc"
              :src="avatarSrc"
              :alt="profile.full_name"
              class="h-full w-full object-cover"
            />
            <span v-else class="text-sm font-semibold text-white">{{ initials || 'U' }}</span>
          </div>
          <!-- Greeting -->
          <span class="text-slate-400 text-sm">
            Welcome back{{ profile.full_name ? ', ' + profile.full_name.split(' ')[0] : '' }}
          </span>
        </div>

        <!-- Account type label -->
        <p class="text-slate-400 text-sm text-center mt-6">{{ accountLabel }}</p>

        <!-- Big balance -->
        <div class="text-center mt-1">
          <span class="text-5xl font-bold text-white">
            {{ dashboard.currency }}{{ balanceParts.whole }}
          </span>
          <span class="text-3xl font-bold text-white">.{{ balanceParts.cents }}</span>
        </div>

        <!-- Accounts pill -->
        <RouterLink
          to="/transactions"
          class="mt-5 mx-auto block w-fit bg-[#1a2436] text-white text-sm rounded-full px-5 py-2 border border-[#1e2d44] text-center"
        >
          Accounts
        </RouterLink>

        <!-- Pagination dots -->
        <div class="flex justify-center items-center gap-1.5 mt-4">
          <span class="w-4 h-1.5 rounded-full bg-white inline-block" />
          <span v-for="i in 4" :key="i" class="w-1.5 h-1.5 rounded-full bg-white/25 inline-block" />
        </div>
      </div>

      <!-- ── SECTION 2: Quick actions row ─────────────────────────────────────── -->
      <div class="flex justify-around px-6 py-5 bg-[#080d18]">
        <RouterLink
          v-for="action in quickActions"
          :key="action.label"
          :to="action.to"
          class="flex flex-col items-center gap-2"
        >
          <div class="h-14 w-14 rounded-full bg-[#1a2436] hover:bg-[#1e2d44] flex items-center justify-center transition">
            <component :is="action.icon" class="h-6 w-6 text-white" />
          </div>
          <span class="text-xs text-slate-400">{{ action.label }}</span>
        </RouterLink>
      </div>

      <!-- ── SECTION 3: Recent transactions ──────────────────────────────────── -->
      <div class="px-4 mt-2">
        <!-- Section header -->
        <div class="flex justify-between items-center mb-3">
          <span class="text-base font-semibold text-white">Recent</span>
          <RouterLink to="/transactions" class="text-blue-400 text-sm">See all</RouterLink>
        </div>

        <!-- Transactions list -->
        <div
          v-if="recentTransactions.length"
          class="bg-[#111827] rounded-2xl overflow-hidden divide-y divide-[#1e2d44]"
        >
          <div
            v-for="tx in recentTransactions"
            :key="tx.trans_id"
            class="flex items-center gap-3 px-4 py-4"
          >
            <!-- Icon circle -->
            <div class="h-10 w-10 rounded-full bg-[#1a2436] flex items-center justify-center shrink-0">
              <span class="text-sm font-semibold text-white">{{ txInitials(tx) }}</span>
            </div>
            <!-- Description + date -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-semibold text-white truncate">
                {{ tx.description || tx.sender_name || 'Account transaction' }}
              </p>
              <p class="text-xs text-slate-500 mt-0.5">{{ txDate(tx) }}</p>
            </div>
            <!-- Amount -->
            <span
              class="text-sm font-semibold"
              :class="Number(tx.trans_type) === 1 ? 'text-emerald-400' : 'text-red-400'"
            >
              {{ Number(tx.trans_type) === 1 ? '+' : '-' }}{{ tx.currency || dashboard.currency }}{{ formatMoney(tx.amount) }}
            </span>
          </div>
        </div>

        <!-- Empty state -->
        <div
          v-else
          class="bg-[#111827] rounded-2xl px-4 py-10 flex flex-col items-center gap-3"
        >
          <ListBulletIcon class="h-10 w-10 text-slate-600" />
          <p class="text-slate-500 text-sm">No transactions yet</p>
        </div>
      </div>

      <!-- ── SECTION 4: Widgets ──────────────────────────────────────────────── -->
      <div class="px-4 mt-6 pb-4">
        <!-- Header -->
        <div class="flex justify-between items-center mb-3">
          <span class="text-base font-semibold text-white">Widgets</span>
        </div>

        <!-- Widget card -->
        <div class="bg-[#111827] rounded-2xl p-5">
          <p class="text-slate-400 text-xs mb-1">Total assets</p>
          <p class="text-3xl font-bold text-white mb-4">
            {{ dashboard.currency }}{{ formatMoney(dashboard.acct_balance) }}
          </p>

          <!-- Asset rows -->
          <div class="space-y-3">
            <!-- Cash -->
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-full bg-blue-600/20 flex items-center justify-center shrink-0">
                <BanknotesIcon class="h-5 w-5 text-blue-400" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm text-white font-medium">Cash</p>
              </div>
              <span class="text-sm font-semibold text-white">
                {{ dashboard.currency }}{{ formatMoney(dashboard.acct_balance) }}
              </span>
            </div>

            <!-- Transfer limit -->
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-full bg-purple-600/20 flex items-center justify-center shrink-0">
                <ArrowsRightLeftIcon class="h-5 w-5 text-purple-400" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm text-white font-medium">Transfer limit</p>
              </div>
              <span class="text-sm font-semibold text-white">
                {{ dashboard.currency }}{{ formatMoney(dashboard.limit_remain) }}
              </span>
            </div>

            <!-- Loans -->
            <div class="flex items-center gap-3">
              <div class="h-9 w-9 rounded-full bg-orange-600/20 flex items-center justify-center shrink-0">
                <WalletIcon class="h-5 w-5 text-orange-400" />
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm text-white font-medium">Loans</p>
              </div>
              <span class="text-sm font-semibold text-white">
                {{ dashboard.currency }}{{ formatMoney(dashboard.loan_balance) }}
              </span>
            </div>
          </div>
        </div>
      </div>

    </template>
  </div>
</template>
