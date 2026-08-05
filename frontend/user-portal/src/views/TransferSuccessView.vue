<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircleIcon, ArrowRightIcon, PlusCircleIcon } from '@heroicons/vue/24/solid'
import client from '../api/client'
import ErrorState from '../components/ui/ErrorState.vue'
import LoadingRegion from '../components/skeletons/LoadingRegion.vue'
import SkeletonText from '../components/skeletons/SkeletonText.vue'
import SkeletonDetailRows from '../components/skeletons/SkeletonDetailRows.vue'
import { countryName } from '../utils/countries'
import { formatMoneyInline } from '../utils/format'

const router = useRouter()

const loading = ref(true)
const transfer = ref(null)
const error = ref('')

async function load() {
  try {
    const { data } = await client.get('/api/user/transfer-success.php')
    if (!data?.ok) throw new Error(data?.message || 'Unable to load transfer')
    transfer.value = data.data
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'Unable to load transfer'
  } finally {
    loading.value = false
  }
}
onMounted(load)

const statusKind = computed(() => {
  const s = String(transfer.value?.status_label || '').toLowerCase()
  if (s.includes('complet') || s.includes('success')) return 'active'
  if (s.includes('fail') || s.includes('reject') || s.includes('cancel')) return 'hold'
  return 'paused'
})

const amountDisplay = computed(() => {
  const currency = transfer.value?.currency || '$'
  return `${currency}${formatMoneyInline(transfer.value?.amount ?? 0, { trimTrailingZero: true })}`
})

const countryDisplay = computed(() => {
  const raw = transfer.value?.acct_country
  if (!raw) return ''
  const name = countryName(raw)
  return name !== raw ? name : raw
})

const detailRows = computed(() => {
  const t = transfer.value || {}
  const rows = [
    { label: 'Reference', value: t.reference_id || '—', mono: true },
    { label: 'Beneficiary', value: t.acct_name || '—' },
    { label: 'Bank', value: t.bank_name || '—' },
  ]
  if (t.type === 'wire' && countryDisplay.value) {
    rows.push({ label: 'Destination', value: countryDisplay.value })
  }
  if (t.acct_number) rows.push({ label: 'Account', value: t.acct_number, mono: true })
  if (t.acct_swift) rows.push({ label: 'SWIFT / BIC', value: t.acct_swift, mono: true })
  return rows
})

function newTransferRoute() {
  return transfer.value?.type === 'domestic' ? '/domestic-transfer' : '/wire-transfer'
}
</script>

<template>
  <div class="page">
    <div class="content">
      <LoadingRegion v-if="loading" label="your transfer receipt">
        <section class="hero">
          <div class="skeleton sk-check" />
          <SkeletonText size="1.4rem" width="12rem" />
          <SkeletonText size="0.9rem" width="14rem" />
          <SkeletonText class="sk-amount" size="2.25rem" :line-height="1" width="9rem" />
          <div class="skeleton sk-status" />
        </section>

        <!-- Reference, beneficiary and bank always render; account number is
             present on every transfer type this screen can be reached from. -->
        <SkeletonDetailRows :rows="4" />

        <div class="actions">
          <div class="skeleton sk-btn" />
          <div class="skeleton sk-btn" />
        </div>
      </LoadingRegion>

      <template v-else-if="transfer">
        <section class="hero">
          <div class="check-icon" aria-hidden="true">
            <CheckCircleIcon />
          </div>
          <h1 class="hero-title">Transfer submitted</h1>
          <p class="hero-sub">Your payment is being processed</p>

          <p class="hero-amount">{{ amountDisplay }}</p>

          <span v-if="transfer.status_label" class="status-badge" :class="`status-badge--${statusKind}`">
            <span class="status-dot"></span>{{ transfer.status_label }}
          </span>
        </section>

        <section class="detail-card" aria-label="Transfer details">
          <div v-for="(row, i) in detailRows" :key="row.label + i" class="row">
            <span class="row-label">{{ row.label }}</span>
            <span class="row-value" :class="{ 'row-value--mono': row.mono }">{{ row.value }}</span>
          </div>
        </section>

        <div class="actions">
          <button type="button" class="btn btn--outlined" @click="router.push('/transactions')">
            <ArrowRightIcon class="btn-icon" aria-hidden="true" />
            View activity
          </button>
          <button type="button" class="btn btn--filled" @click="router.push(newTransferRoute())">
            <PlusCircleIcon class="btn-icon" aria-hidden="true" />
            New transfer
          </button>
        </div>
      </template>

      <template v-else>
        <ErrorState :message="error || 'No transfer record found.'" />
        <button type="button" class="btn btn--outlined btn--full" @click="router.push('/transactions')">
          Go to transactions
        </button>
      </template>
    </div>
  </div>
</template>

<style scoped>
.page {
  min-height: 100vh;
  background: var(--bg-gradient);
  padding: var(--space-6) var(--space-4) 7rem;
}
.content {
  max-width: 30rem;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}

.hero {
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
.check-icon {
  width: 4.5rem;
  height: 4.5rem;
  border-radius: var(--radius-pill);
  background: color-mix(in srgb, var(--success-fg) 14%, transparent);
  color: var(--success-fg);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 12px 28px -12px color-mix(in srgb, var(--success-fg) 60%, transparent);
}
.check-icon > svg { width: 3rem; height: 3rem; }
.hero-title {
  font-size: 1.4rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  letter-spacing: -0.01em;
}
.hero-sub {
  font-size: 0.9rem;
  color: var(--text-secondary);
  margin: 0;
}
.hero-amount {
  font-size: 2.25rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0.35rem 0 0;
  letter-spacing: -0.02em;
  line-height: 1;
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
.status-dot { width: 0.45rem; height: 0.45rem; border-radius: 50%; background: var(--text-secondary); }
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
.row + .row { border-top: 1px solid var(--divider); }
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
  gap: var(--space-3);
  align-items: stretch;
}
.btn {
  flex: 1 1 0;
  height: 3rem;
  padding: 0 var(--space-4);
  border-radius: var(--radius-pill);
  border: 1px solid transparent;
  font-weight: 600;
  font-size: 0.9rem;
  font-family: inherit;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  transition: transform 0.08s ease, background-color 0.15s ease, opacity 0.15s ease;
}
.btn:active { transform: scale(0.98); }
.btn--filled { background: var(--btn-primary-bg); color: var(--btn-primary-fg); }
.btn--filled:hover { opacity: 0.92; }
.btn--outlined {
  background: var(--btn-outline-bg);
  color: var(--btn-outline-fg);
  border-color: var(--btn-outline-border);
}
.btn--outlined:hover { background: color-mix(in srgb, var(--text-primary) 5%, transparent); }
.btn--full { flex: 1 1 100%; margin-top: var(--space-3); }
.btn-icon { width: 1.15rem; height: 1.15rem; }

/* Skeleton — .hero and .actions are reused as-is. */
.sk-check { width: 4.5rem; height: 4.5rem; border-radius: var(--radius-pill); }
.sk-amount { margin-top: 0.35rem; }
.sk-status { width: 6rem; height: 1.72rem; border-radius: var(--radius-pill); }
.sk-btn { flex: 1 1 0; height: 3rem; border-radius: var(--radius-pill); }
</style>
