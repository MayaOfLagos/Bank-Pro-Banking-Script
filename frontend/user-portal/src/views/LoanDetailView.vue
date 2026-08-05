<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ChevronLeftIcon, PrinterIcon } from '@heroicons/vue/24/solid'
import client from '../api/client'
import ErrorState from '../components/ui/ErrorState.vue'
import LoadingRegion from '../components/skeletons/LoadingRegion.vue'
import SkeletonText from '../components/skeletons/SkeletonText.vue'
import SkeletonDetailRows from '../components/skeletons/SkeletonDetailRows.vue'
import { formatMoney } from '../utils/format'

const route = useRoute()
const router = useRouter()

const loan = ref(null)
const loading = ref(true)
const error = ref('')

async function load(reference) {
  loading.value = true
  error.value = ''
  loan.value = null
  try {
    const { data } = await client.get(
      `/api/user/loan-detail.php?id=${encodeURIComponent(reference)}`,
    )
    if (!data?.ok) throw new Error(data?.message || 'Unable to load loan')
    loan.value = data.data
  } catch (err) {
    if (err?.response?.status === 404) {
      error.value = 'This loan application is no longer available.'
    } else {
      error.value = err?.response?.data?.message || err.message || 'Unable to load loan'
    }
  } finally {
    loading.value = false
  }
}

onMounted(() => load(route.params.reference))
watch(() => route.params.reference, (reference) => {
  if (reference) load(reference)
})

const item = computed(() => loan.value || {})
const currency = computed(() => item.value.currency || '$')
const amountParts = computed(() => formatMoney(item.value.amount))

const statusLabel = computed(() => item.value.status_label || item.value.status || '')
const statusKind = computed(() => {
  switch (Number(item.value.status_code)) {
    case 1: return 'active'
    case 2: return 'paused'
    case 3: return 'hold'
    default: return 'paused'
  }
})

const submittedLabel = computed(() => {
  const raw = item.value.created_at
  if (!raw) return '—'
  const d = new Date(String(raw).replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return String(raw)
  return d.toLocaleDateString(undefined, { day: 'numeric', month: 'long', year: 'numeric' })
})

function printReceipt() {
  window.print()
}

// Mirrors legacy user/viewloantrans.php, which showed amount, reference,
// reason, message and status. `loan_message` is the admin's reply and stays
// empty until someone reviews the application.
const detailRows = computed(() => [
  { label: 'Reference', value: item.value.loan_reference_id || '—', mono: true },
  { label: 'Status', value: statusLabel.value || '—' },
  { label: 'Purpose', value: item.value.loan_remarks || '—' },
  { label: 'Message', value: item.value.loan_message || 'N/A' },
  { label: 'Submitted', value: submittedLabel.value },
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
          <h1 class="title">Loan</h1>
          <p class="subtitle">Application details &amp; reference</p>
        </div>
      </header>

      <LoadingRegion v-if="loading" label="this loan">
        <section class="hero">
          <SkeletonText size="0.7rem" width="9rem" />
          <SkeletonText size="2.25rem" :line-height="1.1" width="9.5rem" />
          <div class="skeleton sk-status" />
        </section>

        <section class="detail-card">
          <SkeletonDetailRows :rows="5" :card="false" />
        </section>

        <div class="actions">
          <div class="skeleton sk-action" />
          <div class="skeleton sk-action" />
        </div>
      </LoadingRegion>

      <ErrorState v-else-if="error" :message="error" />

      <template v-else-if="loan">
        <section class="hero">
          <p class="hero-label">Requested amount</p>
          <p class="amount">
            <span class="amount-int">{{ currency }}{{ amountParts.integer }}</span>
            <span v-if="amountParts.fraction" class="amount-frac">.{{ amountParts.fraction }}</span>
          </p>
          <span v-if="statusLabel" class="status-badge" :class="`status-badge--${statusKind}`">
            <span class="status-dot"></span>{{ statusLabel }}
          </span>
        </section>

        <section class="detail-card" aria-label="Loan details">
          <div v-for="row in detailRows" :key="row.label" class="row">
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
            Ask about this loan
          </button>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>
.page { min-height: 100vh; background: var(--bg-gradient); padding: var(--space-5) var(--space-4) 7rem; }
.content { max-width: 30rem; margin: 0 auto; display: flex; flex-direction: column; gap: var(--space-4); }
.header { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) 0 var(--space-1); }
.back { width: 2.5rem; height: 2.5rem; border-radius: 0.7rem; border: 1px solid var(--border); background: transparent; color: var(--text-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.back:hover { background: color-mix(in srgb, var(--text-primary) 6%, transparent); }
.back:active { transform: scale(0.95); }
.back-icon { width: 1.15rem; height: 1.15rem; }
.title { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; line-height: 1.1; }
.subtitle { font-size: 0.8rem; color: var(--text-secondary); margin: 0.15rem 0 0; }

.hero { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-6) var(--space-4); box-shadow: var(--shadow-card); display: flex; flex-direction: column; align-items: center; gap: var(--space-2); text-align: center; }
.hero-label { font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.06em; margin: 0; }
.amount { font-size: 2.25rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.02em; line-height: 1.1; }
.amount-frac { font-size: 1.4rem; font-weight: 700; color: var(--text-secondary); }

.status-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.3rem 0.7rem; border-radius: var(--radius-pill); font-size: 0.72rem; font-weight: 600; background: var(--surface-muted); color: var(--text-primary); text-transform: capitalize; }
.status-dot { width: 0.4rem; height: 0.4rem; border-radius: 50%; background: var(--text-secondary); }
.status-badge--active { background: rgba(16, 185, 129, 0.12); color: var(--success-fg); }
.status-badge--active .status-dot { background: var(--success-fg); }
.status-badge--paused { background: rgba(180, 83, 9, 0.12); color: var(--warning-fg); }
.status-badge--paused .status-dot { background: var(--warning-fg); }
.status-badge--hold { background: var(--danger-bg); color: var(--danger-fg); }
.status-badge--hold .status-dot { background: var(--danger-fg); }

.detail-card { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-5) var(--space-4); box-shadow: var(--shadow-card); display: flex; flex-direction: column; }
.row { display: flex; justify-content: space-between; align-items: baseline; gap: var(--space-4); padding: var(--space-3) 0; }
.row + .row { border-top: 1px solid var(--divider); }
.row-label { color: var(--text-secondary); font-size: 0.85rem; flex-shrink: 0; }
.row-value { color: var(--text-primary); font-size: 0.9rem; font-weight: 600; text-align: right; min-width: 0; word-break: break-word; }
.row-value--mono { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; letter-spacing: 0.02em; }

.actions { display: flex; flex-direction: column; gap: var(--space-3); }
.action { width: 100%; height: 3rem; padding: 0 var(--space-4); border-radius: var(--radius-pill); border: 1px solid var(--btn-outline-border); background: var(--btn-outline-bg); color: var(--btn-outline-fg); font-weight: 600; font-size: 0.9rem; font-family: inherit; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.45rem; transition: background-color 0.15s ease, transform 0.08s ease; }
.action:hover { background: color-mix(in srgb, var(--text-primary) 5%, transparent); }
.action:active { transform: scale(0.98); }
.action-icon { width: 1.1rem; height: 1.1rem; }

/* Skeleton — .hero, .detail-card and .actions are reused as-is. */
.sk-status { width: 5.5rem; height: 1.68rem; border-radius: var(--radius-pill); }
.sk-action { width: 100%; height: 3rem; border-radius: var(--radius-pill); }
</style>
