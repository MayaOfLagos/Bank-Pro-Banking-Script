<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  ChevronLeftIcon, BanknotesIcon, PlusIcon, ClockIcon,
  DocumentTextIcon, CalendarDaysIcon, ChartPieIcon,
} from '@heroicons/vue/24/solid'
import client from '../api/client'
import ErrorState from '../components/ui/ErrorState.vue'
import { currencySymbol } from '../utils/currency'
import { formatMoneyInline } from '../utils/format'

const router = useRouter()
const toast = useToast()

const loading = ref(true)
const error = ref('')
const loans = ref([])
const canRequest = ref(false)

const showModal = ref(false)
const modalAmount = ref('')
const modalDescription = ref('')
const submitting = ref(false)

async function loadLoans() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/api/user/loans.php')
    if (!data?.ok) throw new Error(data?.message || 'Unable to load loans')
    loans.value = data.data.loans ?? []
    canRequest.value = Boolean(data.data.can_request)
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'Unable to load loans'
  } finally {
    loading.value = false
  }
}
onMounted(loadLoans)

// ─── Derived stats ────────────────────────────────────────────────────────
const totalLoans = computed(() => loans.value.length)
const pendingLoans = computed(() =>
  loans.value.filter((l) => statusKind(l.status) === 'paused').length,
)
const approvedBalance = computed(() =>
  loans.value
    .filter((l) => statusKind(l.status) === 'active')
    .reduce((sum, l) => sum + Number(l.amount || 0), 0),
)
const currency = computed(() => loans.value[0]?.currency || '$')
const approvedDisplay = computed(() =>
  `${currency.value}${formatMoneyInline(approvedBalance.value, { trimTrailingZero: true })}`,
)

function statusKind(status) {
  const s = String(status ?? '').toLowerCase()
  if (s.includes('approv')) return 'active'
  if (s.includes('pending') || s.includes('process') || s.includes('hold')) return 'paused'
  if (s.includes('reject') || s.includes('deni') || s.includes('declin') || s.includes('cancel')) return 'hold'
  return 'unknown'
}

function categoryFor(loan) {
  const raw = String(loan.loan_message || loan.description || '').toLowerCase()
  if (/mortgage|home|property|house/.test(raw)) return 'mortgage'
  if (/car|auto|vehicle/.test(raw)) return 'auto'
  if (/student|tuition|school|education/.test(raw)) return 'student'
  if (/business|startup|company/.test(raw)) return 'business'
  return 'personal'
}

function categoryLabel(loan) {
  switch (categoryFor(loan)) {
    case 'mortgage': return 'Mortgage'
    case 'auto': return 'Auto loan'
    case 'student': return 'Student loan'
    case 'business': return 'Business'
    default: return 'Personal'
  }
}

function amountDisplay(loan) {
  return `${loan.currency || '$'}${formatMoneyInline(loan.amount ?? 0, { trimTrailingZero: true })}`
}

function loanDate(loan) {
  const raw = loan.created_at
  if (!raw) return ''
  const d = new Date(String(raw).replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return String(raw)
  return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
}

// ─── Modal + submit ───────────────────────────────────────────────────────
function openModal() {
  modalAmount.value = ''
  modalDescription.value = ''
  showModal.value = true
}

async function submitLoanRequest() {
  const amt = Number(modalAmount.value) || 0
  if (amt <= 0) { toast.error('Enter a loan amount.'); return }
  submitting.value = true
  try {
    const { data } = await client.post('/api/user/loans-submit.php', {
      amount: amt,
      loan_remarks: modalDescription.value.trim(),
    })
    if (!data?.ok) throw new Error(data?.message || 'Loan request failed.')
    toast.success('Loan request submitted — we\'ll get back to you soon.')
    modalAmount.value = ''
    modalDescription.value = ''
    showModal.value = false
    await loadLoans()
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Loan request failed')
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="page">
    <div class="content">
      <header class="header">
        <button type="button" class="back" aria-label="Go back" @click="router.back()">
          <ChevronLeftIcon class="back-icon" aria-hidden="true" />
        </button>
        <div class="titles">
          <h1 class="title">Loans</h1>
          <p class="subtitle">Track requests and apply for new financing</p>
        </div>
      </header>

      <template v-if="loading">
        <div class="skeleton skeleton-stats" />
        <div class="skeleton skeleton-list" />
      </template>

      <ErrorState v-else-if="error" :message="error" />

      <template v-else>
        <!-- Stats row -->
        <section class="stats">
          <div class="stat-card">
            <div class="stat-icon stat-icon--accent">
              <ChartPieIcon aria-hidden="true" />
            </div>
            <p class="stat-value">{{ totalLoans }}</p>
            <p class="stat-label">Total applications</p>
          </div>
          <div class="stat-card">
            <div class="stat-icon stat-icon--warning">
              <ClockIcon aria-hidden="true" />
            </div>
            <p class="stat-value">{{ pendingLoans }}</p>
            <p class="stat-label">Awaiting review</p>
          </div>
          <div class="stat-card">
            <div class="stat-icon stat-icon--success">
              <BanknotesIcon aria-hidden="true" />
            </div>
            <p class="stat-value stat-value--money">{{ approvedDisplay }}</p>
            <p class="stat-label">Approved total</p>
          </div>
        </section>

        <!-- Apply CTA -->
        <button v-if="canRequest" type="button" class="apply-btn" @click="openModal">
          <PlusIcon class="apply-icon" aria-hidden="true" />
          Apply for a loan
        </button>

        <!-- Loan list -->
        <section v-if="loans.length" class="list" aria-label="Loan applications">
          <div v-for="loan in loans" :key="loan.loan_id" class="loan-card">
            <div class="loan-head">
              <div class="loan-category">
                <span class="loan-category-tag">{{ categoryLabel(loan) }}</span>
                <span v-if="loanDate(loan)" class="loan-date">
                  <CalendarDaysIcon class="loan-date-icon" aria-hidden="true" />
                  {{ loanDate(loan) }}
                </span>
              </div>
              <span class="status-badge" :class="`status-badge--${statusKind(loan.status)}`">
                <span class="status-dot"></span>{{ loan.status }}
              </span>
            </div>

            <p class="loan-amount">{{ amountDisplay(loan) }}</p>

            <div v-if="loan.interest_rate || loan.duration" class="loan-meta">
              <span v-if="loan.interest_rate">
                <span class="loan-meta-label">Rate</span>
                <strong>{{ loan.interest_rate }}%</strong>
              </span>
              <span v-if="loan.duration">
                <span class="loan-meta-label">Duration</span>
                <strong>{{ loan.duration }}</strong>
              </span>
            </div>

            <p v-if="loan.loan_message" class="loan-message">
              <DocumentTextIcon class="loan-message-icon" aria-hidden="true" />
              {{ loan.loan_message }}
            </p>
          </div>
        </section>

        <!-- Empty state -->
        <section v-else class="empty">
          <div class="empty-icon">
            <BanknotesIcon aria-hidden="true" />
          </div>
          <h3 class="empty-title">No loan applications yet</h3>
          <p class="empty-body">
            <template v-if="canRequest">Tap "Apply for a loan" to submit your first request.</template>
            <template v-else>Contact support to enable loan applications on this account.</template>
          </p>
        </section>
      </template>
    </div>

    <!-- Apply bottom sheet -->
    <Teleport to="body">
      <div v-if="showModal" class="sheet-overlay" @click.self="showModal = false">
        <div class="sheet" role="dialog" aria-labelledby="loan-modal-title">
          <span class="sheet-handle" aria-hidden="true"></span>
          <h2 id="loan-modal-title" class="sheet-title">Apply for a loan</h2>
          <p class="sheet-sub">A member of our team will review your request within 1–2 business days.</p>

          <div class="field">
            <label class="label" for="loan-amount">Loan amount</label>
            <div class="amount-input">
              <span class="amount-currency">{{ currency }}</span>
              <input
                id="loan-amount" v-model="modalAmount"
                type="number" min="1" step="0.01"
                inputmode="decimal" placeholder="0.00"
                class="input input--amount"
                :disabled="submitting"
              />
            </div>
          </div>

          <div class="field">
            <label class="label" for="loan-purpose">Purpose <span class="hint-optional">Optional</span></label>
            <textarea
              id="loan-purpose" v-model="modalDescription"
              rows="4" placeholder="Briefly describe what this loan will fund…"
              class="input input--textarea"
              :disabled="submitting"
            ></textarea>
          </div>

          <div class="sheet-actions">
            <button type="button" class="btn btn--outlined" :disabled="submitting" @click="showModal = false">
              Cancel
            </button>
            <button type="button" class="btn btn--filled" :disabled="submitting || !modalAmount" @click="submitLoanRequest">
              {{ submitting ? 'Submitting…' : 'Submit request' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
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

/* Stats row */
.stats {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: var(--space-2);
}
.stat-card {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-3) var(--space-2);
  box-shadow: var(--shadow-card);
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 0.35rem;
  min-width: 0;
}
.stat-icon {
  width: 2rem; height: 2rem;
  border-radius: var(--radius-md);
  display: inline-flex; align-items: center; justify-content: center;
}
.stat-icon > svg { width: 1.1rem; height: 1.1rem; }
.stat-icon--accent { background: var(--accent-tint); color: var(--accent-strong); }
.stat-icon--warning { background: rgba(180, 83, 9, 0.14); color: var(--warning-fg); }
.stat-icon--success { background: rgba(16, 185, 129, 0.14); color: var(--success-fg); }
.stat-value {
  font-size: 1.35rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  letter-spacing: -0.01em;
  line-height: 1;
}
.stat-value--money { font-size: 1rem; word-break: break-word; }
.stat-label {
  font-size: 0.7rem;
  color: var(--text-secondary);
  margin: 0;
  line-height: 1.2;
}

/* Apply button — filled black pill matching ActionButtons.btn--filled */
.apply-btn {
  width: 100%; height: 3rem;
  padding: 0 var(--space-4);
  border-radius: var(--radius-pill);
  border: 1px solid transparent;
  background: var(--btn-primary-bg);
  color: var(--btn-primary-fg);
  font-weight: 600; font-size: 0.9rem; font-family: inherit;
  cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
  transition: transform 0.08s ease, opacity 0.15s ease;
}
.apply-btn:hover { opacity: 0.92; }
.apply-btn:active { transform: scale(0.98); }
.apply-icon { width: 1.15rem; height: 1.15rem; }

/* Loan card list */
.list { display: flex; flex-direction: column; gap: var(--space-3); }
.loan-card {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-card);
  display: flex;
  flex-direction: column;
  gap: var(--space-2);
}
.loan-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: var(--space-3);
}
.loan-category { display: flex; flex-direction: column; gap: 0.25rem; }
.loan-category-tag {
  font-size: 0.7rem;
  font-weight: 700;
  color: var(--accent-strong);
  background: var(--accent-tint);
  padding: 0.15rem 0.55rem;
  border-radius: var(--radius-pill);
  align-self: flex-start;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.loan-date {
  display: inline-flex; align-items: center; gap: 0.3rem;
  font-size: 0.75rem; color: var(--text-secondary);
}
.loan-date-icon { width: 0.9rem; height: 0.9rem; }
.loan-amount {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  letter-spacing: -0.01em;
}
.loan-meta {
  display: flex; gap: var(--space-4);
  font-size: 0.85rem; color: var(--text-secondary);
  padding-top: var(--space-2);
  border-top: 1px solid var(--divider);
}
.loan-meta strong { color: var(--text-primary); font-weight: 700; }
.loan-meta-label { display: block; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); margin-bottom: 0.15rem; }
.loan-message {
  display: flex; gap: 0.5rem; align-items: flex-start;
  font-size: 0.85rem; color: var(--text-secondary);
  padding: 0.65rem 0.85rem;
  background: var(--surface-muted);
  border-radius: var(--radius-md);
  margin: 0;
  line-height: 1.5;
}
.loan-message-icon { width: 1rem; height: 1rem; color: var(--text-muted); flex-shrink: 0; margin-top: 0.15rem; }

/* Status badge — same tokens the rest of the app uses */
.status-badge {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  padding: 0.3rem 0.7rem;
  border-radius: var(--radius-pill);
  font-size: 0.72rem;
  font-weight: 600;
  background: var(--surface-muted);
  color: var(--text-primary);
  text-transform: capitalize;
  flex-shrink: 0;
}
.status-dot { width: 0.4rem; height: 0.4rem; border-radius: 50%; background: var(--text-secondary); }
.status-badge--active { background: rgba(16, 185, 129, 0.12); color: var(--success-fg); }
.status-badge--active .status-dot { background: var(--success-fg); }
.status-badge--paused { background: rgba(180, 83, 9, 0.12); color: var(--warning-fg); }
.status-badge--paused .status-dot { background: var(--warning-fg); }
.status-badge--hold { background: var(--danger-bg); color: var(--danger-fg); }
.status-badge--hold .status-dot { background: var(--danger-fg); }

/* Empty state */
.empty {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-8) var(--space-4);
  box-shadow: var(--shadow-card);
  text-align: center;
  display: flex; flex-direction: column; align-items: center; gap: var(--space-3);
}
.empty-icon {
  width: 3rem; height: 3rem;
  border-radius: var(--radius-pill);
  background: var(--accent-tint);
  color: var(--accent-strong);
  display: inline-flex; align-items: center; justify-content: center;
}
.empty-icon > svg { width: 1.5rem; height: 1.5rem; }
.empty-title { font-size: 1rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.empty-body { font-size: 0.85rem; color: var(--text-secondary); margin: 0; max-width: 20rem; line-height: 1.5; }

/* Bottom sheet */
.sheet-overlay {
  position: fixed; inset: 0; z-index: 60;
  display: flex; align-items: flex-end; justify-content: center;
  background: color-mix(in srgb, #000 55%, transparent);
  backdrop-filter: blur(4px);
  -webkit-backdrop-filter: blur(4px);
}
.sheet {
  position: relative;
  width: 100%; max-width: 30rem;
  background: var(--surface);
  border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  padding: var(--space-4) var(--space-5) var(--space-6);
  box-shadow: 0 -20px 60px -20px color-mix(in srgb, #000 40%, transparent);
  display: flex; flex-direction: column; gap: var(--space-3);
}
.sheet-handle {
  width: 2.5rem; height: 0.3rem;
  background: var(--divider);
  border-radius: var(--radius-pill);
  margin: 0 auto var(--space-3);
  display: block;
}
.sheet-title { font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.01em; }
.sheet-sub { font-size: 0.85rem; color: var(--text-secondary); margin: -0.2rem 0 var(--space-3); }

.field { display: flex; flex-direction: column; gap: 0.4rem; }
.label { font-size: 0.75rem; font-weight: 600; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; }
.hint-optional { color: var(--text-muted); text-transform: none; letter-spacing: 0; font-weight: 500; margin-left: 0.35rem; }

.amount-input {
  display: flex; align-items: stretch;
  border-radius: var(--radius-md); background: var(--surface-muted);
  border: 1px solid transparent; overflow: hidden;
  transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
}
.amount-input:focus-within { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent); }
.amount-currency { padding: 0.85rem 0.95rem; color: var(--text-secondary); font-weight: 700; background: transparent; border-right: 1px solid var(--border); display: inline-flex; align-items: center; }
.input--amount { border: none; background: transparent; flex: 1; padding-left: 0.85rem; font-size: 1.15rem; font-weight: 700; color: var(--text-primary); }

.input { width: 100%; padding: 0.85rem 1rem; border-radius: var(--radius-md); border: 1px solid transparent; background: var(--surface-muted); color: var(--text-primary); font-size: 0.95rem; font-family: inherit; outline: none; transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease; }
.input:focus { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent); }
.input:disabled { opacity: 0.6; cursor: not-allowed; }
.input--textarea { resize: none; padding-top: 0.85rem; min-height: 6rem; }

.sheet-actions { display: flex; gap: var(--space-3); margin-top: var(--space-3); }
.btn {
  flex: 1 1 0; height: 3rem;
  padding: 0 var(--space-4);
  border-radius: var(--radius-pill);
  border: 1px solid transparent;
  font-weight: 600; font-size: 0.9rem; font-family: inherit;
  cursor: pointer;
  display: inline-flex; align-items: center; justify-content: center;
  transition: transform 0.08s ease, background-color 0.15s ease, opacity 0.15s ease;
}
.btn:active { transform: scale(0.98); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }
.btn--filled { background: var(--btn-primary-bg); color: var(--btn-primary-fg); }
.btn--filled:hover:not(:disabled) { opacity: 0.92; }
.btn--outlined { background: var(--btn-outline-bg); color: var(--btn-outline-fg); border-color: var(--btn-outline-border); }
.btn--outlined:hover:not(:disabled) { background: color-mix(in srgb, var(--text-primary) 5%, transparent); }

.skeleton { border-radius: var(--radius-lg); background: var(--surface-muted); animation: pulse 1.6s ease-in-out infinite; }
.skeleton-stats { height: 6rem; }
.skeleton-list { height: 20rem; }
@keyframes pulse { 0%, 100% { opacity: 0.5; } 50% { opacity: 0.85; } }
</style>
