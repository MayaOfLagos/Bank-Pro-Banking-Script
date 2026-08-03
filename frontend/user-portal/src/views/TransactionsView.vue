<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChevronLeftIcon, ListBulletIcon } from '@heroicons/vue/24/solid'
import client from '../api/client'
import TransactionItem from '../components/dashboard/TransactionItem.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import ErrorState from '../components/ui/ErrorState.vue'

const router = useRouter()
const route = useRoute()

const loading = ref(true)
const error = ref('')
const transactions = ref([])

const TABS = ['All', 'Credits', 'Debits']
// /deposits mounts this same view pre-filtered to Credits, with a
// deposit-oriented page title/subtitle. Meta-driven so the routing table
// stays the single source of truth.
const initialTab = TABS.includes(route.meta?.defaultTab) ? route.meta.defaultTab : 'All'
const activeTab = ref(initialTab)
const pageTitle = computed(() => route.meta?.pageTitle || 'Activity')
const pageSubtitle = computed(() => route.meta?.pageSubtitle || 'All account movement')

// Re-sync the active tab when the user navigates between /transactions and
// /deposits without a full remount (router keeps the component alive).
watch(() => route.meta?.defaultTab, (val) => {
  activeTab.value = TABS.includes(val) ? val : 'All'
})

const filteredRows = computed(() => {
  const tab = activeTab.value
  if (tab === 'Credits') return transactions.value.filter((t) => Number(t.trans_type) === 1)
  if (tab === 'Debits') return transactions.value.filter((t) => Number(t.trans_type) === 2)
  return transactions.value
})

/**
 * Group by day so the list reads like a mini-statement.
 * Buckets: Today, Yesterday, or "Mon 3 Aug 2026".
 */
const groupedRows = computed(() => {
  const buckets = new Map()
  const today = startOfDay(new Date())
  const yesterday = startOfDay(new Date(today.getTime() - 86_400_000))

  for (const tx of filteredRows.value) {
    const raw = tx.created_at || tx.date || tx.time_created || ''
    const d = parseDate(raw)
    let key
    let label
    let sortKey
    if (!d) {
      key = 'unknown'
      label = 'Unknown date'
      sortKey = -Infinity
    } else {
      const day = startOfDay(d)
      sortKey = day.getTime()
      key = String(sortKey)
      if (sortKey === today.getTime()) label = 'Today'
      else if (sortKey === yesterday.getTime()) label = 'Yesterday'
      else label = day.toLocaleDateString(undefined, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })
    }
    const bucket = buckets.get(key) || { key, label, sortKey, rows: [] }
    bucket.rows.push(tx)
    buckets.set(key, bucket)
  }

  return Array.from(buckets.values()).sort((a, b) => b.sortKey - a.sortKey)
})

function startOfDay(d) {
  const copy = new Date(d)
  copy.setHours(0, 0, 0, 0)
  return copy
}

function parseDate(raw) {
  if (!raw) return null
  const d = new Date(String(raw).replace(' ', 'T'))
  return Number.isNaN(d.getTime()) ? null : d
}

async function loadTransactions() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/api/user/transactions.php?limit=200')
    if (data?.ok === false) throw new Error(data?.message || 'Failed to load transactions')
    transactions.value = data?.data || []
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'Unable to load transactions'
  } finally {
    loading.value = false
  }
}

const currency = computed(() => transactions.value[0]?.currency || '$')

onMounted(loadTransactions)
</script>

<template>
  <div class="page">
    <div class="content">
      <header class="header">
        <button type="button" class="back" aria-label="Go back" @click="router.back()">
          <ChevronLeftIcon class="back-icon" aria-hidden="true" />
        </button>
        <div class="titles">
          <h1 class="title">{{ pageTitle }}</h1>
          <p class="subtitle">{{ pageSubtitle }}</p>
        </div>
      </header>

      <div class="tabs" role="tablist" aria-label="Filter transactions">
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

      <template v-if="loading">
        <div class="skeleton-group">
          <div v-for="i in 6" :key="i" class="skeleton-row" />
        </div>
      </template>

      <ErrorState v-else-if="error" :message="error" />

      <template v-else>
        <p class="count" v-if="filteredRows.length">
          Showing {{ filteredRows.length }} record{{ filteredRows.length !== 1 ? 's' : '' }}
        </p>

        <EmptyState
          v-if="!filteredRows.length"
          :icon="ListBulletIcon"
          message="No transactions match this filter yet."
        />

        <section
          v-for="group in groupedRows"
          :key="group.key"
          class="group"
          :aria-label="group.label"
        >
          <h2 class="group-label">{{ group.label }}</h2>
          <div class="rows">
            <TransactionItem
              v-for="(tx, i) in group.rows"
              :key="tx.trans_id ?? tx.id ?? i"
              :transaction="tx"
              :currency="currency"
              :to="tx.trans_id ? `/transactions/${tx.trans_id}` : null"
            />
          </div>
        </section>
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
.back:hover {
  background: color-mix(in srgb, var(--text-primary) 6%, transparent);
}
.back:active {
  transform: scale(0.95);
}
.back-icon {
  width: 1.15rem;
  height: 1.15rem;
}
.titles {
  min-width: 0;
}
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
.tabs {
  display: flex;
  gap: var(--space-2);
  overflow-x: auto;
  scrollbar-width: none;
  padding-bottom: 0.25rem;
}
.tabs::-webkit-scrollbar {
  display: none;
}
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
.tab:hover {
  color: var(--text-primary);
}
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
.group {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.group-label {
  font-size: 0.75rem;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.06em;
  margin: 0;
  padding: 0 var(--space-1);
}
.rows {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-2) var(--space-4);
  box-shadow: var(--shadow-card);
}
.rows > * + * {
  border-top: 1px solid var(--divider);
}
.skeleton-group {
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.skeleton-row {
  height: 4.5rem;
  border-radius: var(--radius-lg);
  background: var(--surface-muted);
  animation: pulse 1.6s ease-in-out infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 0.5; }
  50% { opacity: 0.85; }
}
</style>
