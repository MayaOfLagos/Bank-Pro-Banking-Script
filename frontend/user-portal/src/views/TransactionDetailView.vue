<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChevronLeftIcon, PrinterIcon } from '@heroicons/vue/24/solid'
import client from '../api/client'
import ErrorState from '../components/ui/ErrorState.vue'
import TransactionAvatar from '../components/ui/TransactionAvatar.vue'
import LoadingRegion from '../components/skeletons/LoadingRegion.vue'
import SkeletonText from '../components/skeletons/SkeletonText.vue'
import SkeletonDetailRows from '../components/skeletons/SkeletonDetailRows.vue'
import { formatMoney, coerceNumber } from '../utils/format'

const route = useRoute()
const router = useRouter()

const transaction = ref(null)
const loading = ref(true)
const error = ref('')

async function load(id, source) {
  loading.value = true
  error.value = ''
  transaction.value = null
  try {
    const query = `id=${encodeURIComponent(id)}&source=${encodeURIComponent(source || 'transaction')}`
    const { data } = await client.get(`/api/user/transaction.php?${query}`)
    if (!data?.ok) throw new Error(data?.message || 'Unable to load transaction')
    transaction.value = data.data
  } catch (err) {
    if (err?.response?.status === 404) {
      error.value = 'This transaction is no longer available.'
    } else {
      error.value = err?.response?.data?.message || err.message || 'Unable to load transaction'
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => load(route.params.id, route.params.source))
watch(() => [route.params.id, route.params.source], ([id, source]) => {
  if (id) load(id, source)
})

const tx = computed(() => transaction.value || {})
const currency = computed(() => tx.value.currency || '$')
const isCredit = computed(() => Number(tx.value.trans_type) === 1)
const amountValue = computed(() => coerceNumber(tx.value.amount))
const amountParts = computed(() => formatMoney(amountValue.value))
const merchant = computed(() => tx.value.description || tx.value.sender_name || 'Account transaction')

const statusLabel = computed(() => tx.value.status_label || '')
const statusKind = computed(() => {
  const s = statusLabel.value.toLowerCase()
  if (s === 'completed') return 'active'
  if (s === 'processing' || s === 'hold') return 'paused'
  if (s === 'cancelled') return 'hold'
  return 'unknown'
})

const dateLabel = computed(() => {
  const raw = tx.value.created_at
  if (!raw) return ''
  const d = new Date(String(raw).replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return String(raw)
  return d.toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' })
})

const timeLabel = computed(() => {
  const raw = tx.value.time_created || tx.value.created_at
  if (!raw) return ''
  const d = new Date(`1970-01-01T${String(raw)}`)
  if (!Number.isNaN(d.getTime()) && String(raw).match(/^\d{2}:\d{2}/)) {
    return d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
  }
  const full = new Date(String(tx.value.created_at || '').replace(' ', 'T'))
  return Number.isNaN(full.getTime()) ? String(raw) : full.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })
})

function printReceipt() {
  window.print()
}

// Only `transactions` rows name a counterparty in `sender_name`; the other
// four sources put a title there ('Wire Transfer'), which would render as
// "To: Wire Transfer" over the beneficiary that `extra` already carries.
const isCounterparty = computed(() => {
  const source = String(tx.value.source || 'transaction')
  return source === 'transaction'
})

// `extra` carries the fields that only make sense for one source — SWIFT and
// routing for a wire, destination wallet for a crypto withdrawal, and so on.
const detailRows = computed(() => [
  { label: 'Type', value: tx.value.type_label || (isCredit.value ? 'Credit' : 'Debit') },
  { label: 'Reference', value: tx.value.reference_id || tx.value.refrence_id || '—', mono: true },
  ...(isCounterparty.value
    ? [{ label: isCredit.value ? 'From' : 'To', value: tx.value.sender_name || '—' }]
    : []),
  { label: 'Description', value: tx.value.description || '—' },
  ...(tx.value.extra || []),
  { label: 'Date', value: dateLabel.value || '—' },
  { label: 'Time', value: timeLabel.value || '—' },
])
</script>

<template>
  <div class="page">
    <div class="content">
      <header class="header">
        <button type="button" class="back no-print" aria-label="Go back" @click="router.back()">
          <ChevronLeftIcon class="back-icon" aria-hidden="true" />
        </button>
        <div class="titles">
          <h1 class="title">Transaction</h1>
          <p class="subtitle">Details &amp; reference</p>
        </div>
      </header>

      <LoadingRegion v-if="loading" label="this transaction">
        <section class="hero">
          <div class="skeleton sk-avatar" />
          <SkeletonText size="2.5rem" :line-height="1" width="10rem" />
          <SkeletonText size="0.95rem" width="11rem" />
          <div class="skeleton sk-badge" />
        </section>

        <!-- Seven rows: Type, Reference, To/From, Description, Date, Time plus
             the one source-specific `extra` row most feeds carry. -->
        <SkeletonDetailRows :rows="7" />

        <div class="actions">
          <div class="skeleton sk-action" />
          <div class="skeleton sk-action sk-action--narrow" />
        </div>
      </LoadingRegion>

      <ErrorState v-else-if="error" :message="error" />

      <template v-else-if="transaction">
        <section class="hero">
          <TransactionAvatar :transaction="tx" />

          <p class="amount" :class="{ 'amount--credit': isCredit }">
            <span class="amount-sign">{{ isCredit ? '+' : '-' }}</span>
            <span class="amount-int">{{ currency }}{{ amountParts.integer }}</span>
            <span v-if="amountParts.fraction" class="amount-frac">.{{ amountParts.fraction }}</span>
          </p>

          <p class="merchant">{{ merchant }}</p>

          <span v-if="statusLabel" class="status-badge" :class="`status-badge--${statusKind}`">
            <span class="status-dot"></span>{{ statusLabel }}
          </span>
        </section>

        <section class="detail-card" aria-label="Transaction details">
          <div v-for="(row, i) in detailRows" :key="row.label + i" class="row">
            <span class="row-label">{{ row.label }}</span>
            <span class="row-value" :class="{ 'row-value--mono': row.mono }">{{ row.value }}</span>
          </div>
        </section>

        <div class="actions no-print">
          <button type="button" class="action" @click="printReceipt">
            <PrinterIcon class="action-icon" aria-hidden="true" />
            Print receipt
          </button>
          <button type="button" class="action" @click="router.push('/tickets')">
            Report an issue
          </button>
        </div>
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
  border-radius: 1.7rem;
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
.hero {
  --tx-avatar-size: 4rem;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-6) var(--space-4);
  background: var(--surface);
  border-radius: var(--radius-lg);
  box-shadow: var(--shadow-card);
  text-align: center;
}
.amount {
  margin: 0;
  color: var(--text-primary);
  font-weight: 800;
  line-height: 1;
  display: inline-flex;
  align-items: baseline;
  gap: 0.05rem;
}
.amount--credit {
  color: var(--accent-strong);
}
.amount-sign {
  font-size: 1.5rem;
  margin-right: 0.1rem;
}
.amount-int {
  font-size: 2.5rem;
  letter-spacing: -0.02em;
}
.amount-frac {
  font-size: 1.4rem;
  opacity: 0.9;
}
.merchant {
  font-size: 0.95rem;
  color: var(--text-secondary);
  margin: 0;
  max-width: 100%;
  word-break: break-word;
}
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.3rem 0.7rem;
  border-radius: var(--radius-pill);
  font-size: 0.75rem;
  font-weight: 600;
  background: var(--surface-muted);
  color: var(--text-primary);
}
.status-dot {
  width: 0.45rem;
  height: 0.45rem;
  border-radius: 50%;
  background: var(--text-secondary);
}
.status-badge--active { background: rgba(16, 185, 129, 0.12); color: var(--success-fg); }
.status-badge--active .status-dot { background: var(--success-fg); }
.status-badge--paused { background: rgba(180, 83, 9, 0.12); color: var(--warning-fg); }
.status-badge--paused .status-dot { background: var(--warning-fg); }
.status-badge--hold { background: var(--danger-bg); color: var(--danger-fg); }
.status-badge--hold .status-dot { background: var(--danger-fg); }
.detail-card {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-2) var(--space-4);
  box-shadow: var(--shadow-card);
}
.row {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: var(--space-4);
  padding: var(--space-3) 0;
}
.row + .row {
  border-top: 1px solid var(--divider);
}
.row-label {
  color: var(--text-secondary);
  font-size: 0.85rem;
  flex-shrink: 0;
}
.row-value {
  color: var(--text-primary);
  font-size: 0.9rem;
  font-weight: 600;
  text-align: right;
  min-width: 0;
  word-break: break-word;
}
.row-value--mono {
  font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
  letter-spacing: 0.02em;
  font-weight: 500;
}
.actions {
  display: flex;
  justify-content: center;
  gap: var(--space-3);
  flex-wrap: wrap;
}
.action {
  padding: 0.75rem 1.5rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--btn-outline-border);
  background: var(--surface);
  color: var(--text-primary);
  font-weight: 600;
  cursor: pointer;
  font-size: 0.9rem;
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
}
.action-icon { width: 1.1rem; height: 1.1rem; }
.action:hover {
  background: color-mix(in srgb, var(--text-primary) 4%, var(--surface));
}

/* Skeleton. The hero block reuses the real .hero rule above, so its padding,
   radius and 4rem --tx-avatar-size come along for free. */
.sk-avatar {
  width: var(--tx-avatar-size);
  height: var(--tx-avatar-size);
  border-radius: var(--radius-pill);
}
.sk-badge {
  width: 6rem;
  height: 1.6rem;
  border-radius: var(--radius-pill);
}
.sk-action {
  width: 10.75rem;
  height: 2.9rem;
  border-radius: var(--radius-pill);
}
.sk-action--narrow {
  width: 10rem;
}
</style>
