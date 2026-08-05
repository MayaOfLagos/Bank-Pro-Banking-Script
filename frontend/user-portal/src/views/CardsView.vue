<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useToast } from 'vue-toastification'
import {
  PlusIcon,
  ArrowUpRightIcon,
  EyeIcon,
  EyeSlashIcon,
  InformationCircleIcon,
  ReceiptPercentIcon,
  ChartBarSquareIcon,
} from '@heroicons/vue/24/solid'
import BalanceHeader from '../components/dashboard/BalanceHeader.vue'
import CardDetailHero from '../components/dashboard/CardDetailHero.vue'
import ActionButtons from '../components/dashboard/ActionButtons.vue'
import OptionsList from '../components/dashboard/OptionsList.vue'
import TransactionList from '../components/dashboard/TransactionList.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import LoadingRegion from '../components/skeletons/LoadingRegion.vue'
import SkeletonText from '../components/skeletons/SkeletonText.vue'
import SkeletonTransactionRows from '../components/skeletons/SkeletonTransactionRows.vue'
import { useProfileStore } from '../stores/profile'
import { useCards } from '../composables/useCards'
import { formatMoney, formatMoneyInline } from '../utils/format'

const HIDE_KEY = 'bankpro:hide-card-balance'

const profileStore = useProfileStore()
const cardsBundle = useCards()

const hideBalance = ref(readHidePreference())

function readHidePreference() {
  try {
    return window.localStorage.getItem(HIDE_KEY) === '1'
  } catch {
    return false
  }
}

watch(hideBalance, (val) => {
  try {
    window.localStorage.setItem(HIDE_KEY, val ? '1' : '0')
  } catch {
    // ignore
  }
})

// useCards() exposes `loading`, but it is still false on the first paint — long
// enough to flash the "No card linked to your account yet." empty state at
// customers who do have one. Track settlement explicitly instead; load()
// swallows its own errors, so `finally` is the honest hook.
const cardsSettled = ref(false)

onMounted(() => {
  profileStore.loadDashboard().catch(() => {})
  profileStore.loadProfile().catch(() => {})
  cardsBundle.load().finally(() => { cardsSettled.value = true })
})

const profile = computed(() => profileStore.profile ?? {})
const currency = computed(() => cardsBundle.primaryCard.value?.currency || profile.value.currency || '$')

const card = computed(() => cardsBundle.primaryCard.value)

// Hydrate the hero's holder from the profile if the card row doesn't have
// one, so the visual is populated even for legacy accounts.
const cardWithHolder = computed(() => {
  if (!card.value) return null
  return {
    ...card.value,
    card_name: card.value.card_name || profile.value.full_name || '',
  }
})

const balanceParts = computed(() => formatMoney(card.value?.balance ?? 0))

const firstName = computed(() => {
  const full = profile.value.full_name || `${profile.value.firstname || ''} ${profile.value.lastname || ''}`.trim()
  return String(full).split(' ')[0] || ''
})

const detailActions = [
  { label: 'Transfer', icon: PlusIcon, variant: 'outlined', to: '/wire-transfer' },
  { label: 'Withdraw', icon: ArrowUpRightIcon, variant: 'outlined', to: '/withdrawals' },
]

// Roughly the rendered width of "Card details" / "Tariff information" /
// "Spending statistics" at 0.95rem.
const OPTION_WIDTHS = ['6.5rem', '9rem', '9.5rem']

const cardOptions = [
  { label: 'Card details', icon: InformationCircleIcon, onClick: () => {} },
  { label: 'Tariff information', icon: ReceiptPercentIcon, onClick: () => {} },
  { label: 'Spending statistics', icon: ChartBarSquareIcon, to: '/transactions' },
]

const recentTransactions = computed(() => {
  const rows = profileStore.balances?.recent_transactions
  return Array.isArray(rows) ? rows.slice(0, 4) : []
})

function toggleBalance() {
  hideBalance.value = !hideBalance.value
}

const toast = useToast()

const cardStatusLabel = computed(() => card.value?.card_status || '')
const cardStatusKind = computed(() => {
  const label = cardStatusLabel.value.toLowerCase()
  if (label === 'active') return 'active'
  if (label === 'paused') return 'paused'
  if (label === 'processing') return 'processing'
  if (label === 'hold') return 'hold'
  return 'unknown'
})

const cardLimit = computed(() => Number(card.value?.card_limit ?? 0))
const cardLimitRemain = computed(() => Number(card.value?.card_limit_remain ?? 0))
const hasLimit = computed(() => cardLimit.value > 0)
const limitDisplay = computed(() => `${currency.value}${formatMoneyInline(cardLimit.value, { trimTrailingZero: true })}`)
const remainDisplay = computed(() => `${currency.value}${formatMoneyInline(cardLimitRemain.value, { trimTrailingZero: true })}`)
const limitProgress = computed(() => {
  if (!hasLimit.value) return 0
  const used = Math.max(0, cardLimit.value - cardLimitRemain.value)
  return Math.min(1, Math.max(0, used / cardLimit.value))
})
const limitProgressPct = computed(() => Math.round(limitProgress.value * 100))

const canPause = computed(() => cardStatusKind.value === 'active')
const canActivate = computed(() => cardStatusKind.value === 'paused')

async function onPause() {
  try {
    const res = await cardsBundle.updateStatus('pause')
    toast.success(res?.message || 'Card paused')
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Unable to pause card')
  }
}

async function onActivate() {
  try {
    const res = await cardsBundle.updateStatus('active')
    toast.success(res?.message || 'Card activated')
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Unable to activate card')
  }
}

const REASON_MAX = 500
const REASON_MIN = 5

const showRequest = ref(false)
const requestType = ref('VISA')
const requestReason = ref('')

const reasonLength = computed(() => requestReason.value.trim().length)
const reasonValid = computed(() => reasonLength.value >= REASON_MIN && reasonLength.value <= REASON_MAX)

const latestRequest = computed(() => cardsBundle.latestRequest.value)
const pendingRequest = computed(() =>
  cardsBundle.hasRequestInProgress.value ? latestRequest.value : null,
)

function openRequest() {
  requestType.value = 'VISA'
  requestReason.value = ''
  showRequest.value = true
}

async function submitRequest() {
  if (!reasonValid.value) {
    toast.error(`Tell us why you need this card (at least ${REASON_MIN} characters).`)
    return
  }
  try {
    const res = await cardsBundle.requestCard({
      card_type: requestType.value,
      card_reason: requestReason.value.trim(),
    })
    showRequest.value = false
    toast.success(res?.message || 'Card request submitted')
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Card request failed')
  }
}
</script>

<template>
  <div class="page">
    <div class="content">
      <BalanceHeader
        :first-name="firstName"
        :avatar-url="profile.image || ''"
        :has-unread="false"
      />

      <!-- BalanceHeader above stays live: it degrades to "Hi there" on its own
           and owns the theme toggle, which should never be inert. Everything
           below it is card data, so it gets placeholders.
           The limit block is included because a card row normally carries a
           limit; on the minority of cards without one the page settles one
           block shorter. -->
      <LoadingRegion v-if="!card && !cardsSettled" label="your card">
        <div class="skeleton sk-hero" />

        <div class="status-row">
          <div class="skeleton sk-status" />
          <SkeletonText size="0.8rem" width="5.5rem" />
        </div>

        <section class="balance-block">
          <SkeletonText size="0.85rem" width="6.5rem" />
          <div class="amount-row">
            <SkeletonText size="2.5rem" :line-height="1" width="9rem" />
            <div class="skeleton sk-reveal" />
          </div>
          <div class="sk-actions">
            <div v-for="i in 2" :key="i" class="skeleton sk-action" />
          </div>
        </section>

        <section class="limit-block">
          <div class="limit-head">
            <SkeletonText size="0.9rem" width="5rem" />
            <SkeletonText size="0.9rem" width="8rem" />
          </div>
          <div class="skeleton sk-track" />
        </section>

        <div class="sk-options">
          <div v-for="i in 3" :key="i" class="sk-option">
            <div class="skeleton sk-option-icon" />
            <SkeletonText class="sk-grow" size="0.95rem" :width="OPTION_WIDTHS[i - 1]" />
            <div class="skeleton sk-chev" />
          </div>
        </div>

        <div class="sk-tx-card">
          <div class="sk-tx-head">
            <SkeletonText size="1rem" width="7rem" />
            <div class="skeleton sk-chev" />
          </div>
          <SkeletonTransactionRows :rows="4" />
        </div>
      </LoadingRegion>

      <template v-else-if="card">
        <CardDetailHero :card="cardWithHolder" :masked="hideBalance" />

        <div v-if="cardStatusLabel" class="status-row">
          <span class="status-badge" :class="`status-badge--${cardStatusKind}`">
            <span class="status-dot"></span>{{ cardStatusLabel }}
          </span>
          <button
            v-if="canPause"
            type="button"
            class="status-action"
            :disabled="cardsBundle.updating.value"
            @click="onPause"
          >
            {{ cardsBundle.updating.value ? 'Pausing…' : 'Pause card' }}
          </button>
          <button
            v-else-if="canActivate"
            type="button"
            class="status-action status-action--primary"
            :disabled="cardsBundle.updating.value"
            @click="onActivate"
          >
            {{ cardsBundle.updating.value ? 'Activating…' : 'Activate card' }}
          </button>
        </div>

        <section class="balance-block">
          <p class="label">Card Balance</p>
          <div class="amount-row">
            <p class="amount">
              <template v-if="hideBalance">
                <span class="int">••••••</span>
              </template>
              <template v-else>
                <span class="int">{{ currency }}{{ balanceParts.integer }}</span>
                <span v-if="balanceParts.fraction" class="frac">.{{ balanceParts.fraction }}</span>
              </template>
            </p>
            <button
              type="button"
              class="reveal"
              :aria-label="hideBalance ? 'Show card balance' : 'Hide card balance'"
              :aria-pressed="hideBalance"
              @click="toggleBalance"
            >
              <EyeIcon v-if="hideBalance" class="reveal-icon" aria-hidden="true" />
              <EyeSlashIcon v-else class="reveal-icon" aria-hidden="true" />
            </button>
          </div>
          <ActionButtons :actions="detailActions" />
        </section>

        <section v-if="hasLimit" class="limit-block" aria-label="Card spending limit">
          <div class="limit-head">
            <span class="limit-title">Card limit</span>
            <span class="limit-values">{{ remainDisplay }} <span class="limit-of">of {{ limitDisplay }}</span></span>
          </div>
          <div class="limit-track" :aria-valuenow="limitProgressPct" aria-valuemin="0" aria-valuemax="100" role="progressbar">
            <div class="limit-fill" :style="{ width: `${limitProgressPct}%` }"></div>
          </div>
        </section>

        <OptionsList :options="cardOptions" />

        <TransactionList
          :transactions="recentTransactions"
          :currency="currency"
          see-all-to="/transactions"
        />
      </template>

      <EmptyState
        v-else-if="!cardsBundle.loading.value && !cardsBundle.error.value"
        message="No card linked to your account yet."
      >
        <div v-if="pendingRequest" class="request-pending">
          <span class="status-badge status-badge--processing">
            <span class="status-dot"></span>{{ pendingRequest.status }}
          </span>
          <p class="request-note">
            Your {{ pendingRequest.card_type }} request is with our team. Reference
            <span class="mono">{{ pendingRequest.reference_id }}</span>.
          </p>
        </div>

        <button
          v-else-if="cardsBundle.canRequest.value"
          type="button"
          class="request-btn"
          @click="openRequest"
        >
          Request a card
        </button>

        <p v-else-if="cardsBundle.requestBlockedReason.value" class="request-note">
          {{ cardsBundle.requestBlockedReason.value }}
        </p>
      </EmptyState>

      <ErrorState v-if="cardsBundle.error.value" :message="cardsBundle.error.value" compact />
    </div>

    <Teleport to="body">
      <div v-if="showRequest" class="sheet-overlay" @click.self="showRequest = false">
        <div class="sheet" role="dialog" aria-labelledby="card-request-title">
          <span class="sheet-handle" aria-hidden="true"></span>
          <h2 id="card-request-title" class="sheet-title">Request a card</h2>
          <p class="sheet-sub">We'll review your request and issue the card to your account.</p>

          <div class="field">
            <span class="label">Card type</span>
            <div class="type-choice" role="radiogroup" aria-label="Card type">
              <button
                v-for="option in ['VISA', 'MASTERCARD']"
                :key="option"
                type="button"
                role="radio"
                :aria-checked="requestType === option"
                class="type-option"
                :class="{ 'type-option--on': requestType === option }"
                :disabled="cardsBundle.requesting.value"
                @click="requestType = option"
              >
                {{ option }}
              </button>
            </div>
          </div>

          <div class="field">
            <label class="label" for="card-reason">Why do you need this card?</label>
            <textarea
              id="card-reason"
              v-model="requestReason"
              rows="4"
              :maxlength="REASON_MAX"
              placeholder="Tell us how you plan to use it…"
              class="input input--textarea"
              :disabled="cardsBundle.requesting.value"
            ></textarea>
            <span class="counter">{{ reasonLength }} / {{ REASON_MAX }}</span>
          </div>

          <div class="sheet-actions">
            <button
              type="button"
              class="btn btn--outlined"
              :disabled="cardsBundle.requesting.value"
              @click="showRequest = false"
            >
              Cancel
            </button>
            <button
              type="button"
              class="btn btn--filled"
              :disabled="cardsBundle.requesting.value || !reasonValid"
              @click="submitRequest"
            >
              {{ cardsBundle.requesting.value ? 'Submitting…' : 'Submit request' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
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
.balance-block {
  padding: var(--space-3) var(--space-1) 0;
  display: flex;
  flex-direction: column;
  gap: var(--space-4);
}
.label {
  font-size: 0.85rem;
  color: var(--text-secondary);
  margin: 0;
}
.amount-row {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: var(--space-3);
}
.amount {
  margin: 0;
  color: var(--text-primary);
  font-weight: 800;
  line-height: 1;
}
.int {
  font-size: 2.5rem;
  letter-spacing: -0.01em;
}
.frac {
  font-size: 1.5rem;
  color: var(--text-secondary);
  font-weight: 700;
  margin-left: 0.15rem;
}
.reveal {
  align-self: center;
  width: 2.25rem;
  height: 2.25rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.reveal-icon {
  width: 1.15rem;
  height: 1.15rem;
}
.request-btn {
  margin-top: var(--space-3);
  padding: 0.75rem 1.5rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--btn-outline-border);
  background: var(--surface);
  color: var(--text-primary);
  font-weight: 600;
  cursor: pointer;
}
.status-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: 0 var(--space-1);
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
  display: inline-block;
}
.status-badge--active { background: rgba(16, 185, 129, 0.12); color: var(--success-fg); }
.status-badge--active .status-dot { background: var(--success-fg); }
.status-badge--paused { background: rgba(180, 83, 9, 0.12); color: var(--warning-fg); }
.status-badge--paused .status-dot { background: var(--warning-fg); }
.status-badge--processing { background: var(--accent-soft); color: var(--accent-strong); }
.status-badge--processing .status-dot { background: var(--accent-strong); }
.status-badge--hold { background: var(--danger-bg); color: var(--danger-fg); }
.status-badge--hold .status-dot { background: var(--danger-fg); }
.status-action {
  background: transparent;
  border: none;
  color: var(--text-secondary);
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  padding: 0.25rem 0.4rem;
}
.status-action:hover { color: var(--text-primary); }
.status-action--primary { color: var(--accent-strong); }
.status-action:disabled { opacity: 0.6; cursor: not-allowed; }
.limit-block {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-card);
}
.limit-head {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  margin-bottom: var(--space-3);
}
.limit-title {
  font-size: 0.9rem;
  font-weight: 600;
  color: var(--text-primary);
}
.limit-values {
  font-size: 0.9rem;
  font-weight: 700;
  color: var(--text-primary);
}
.limit-of {
  color: var(--text-secondary);
  font-weight: 500;
  margin-left: 0.15rem;
}
.limit-track {
  width: 100%;
  height: 0.4rem;
  background: var(--surface-muted);
  border-radius: var(--radius-pill);
  overflow: hidden;
}
.limit-fill {
  height: 100%;
  background: var(--accent);
  border-radius: inherit;
  transition: width 0.3s ease;
}
.request-pending {
  margin-top: var(--space-3);
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-2);
}
.request-note {
  margin: 0;
  font-size: 0.85rem;
  color: var(--text-secondary);
  text-align: center;
  max-width: 22rem;
}
.mono {
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  color: var(--text-primary);
}

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
.counter { align-self: flex-end; font-size: 0.7rem; color: var(--text-muted); }

.type-choice { display: flex; gap: var(--space-3); }
.type-option {
  flex: 1 1 0; height: 3rem;
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
  background: var(--surface-muted);
  color: var(--text-secondary);
  font-family: inherit; font-weight: 700; font-size: 0.85rem;
  letter-spacing: 0.03em;
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease, color 0.15s ease;
}
.type-option--on {
  border-color: var(--accent);
  background: var(--accent-soft);
  color: var(--accent-strong);
}
.type-option:disabled { opacity: 0.6; cursor: not-allowed; }

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

/* Skeleton — .status-row, .balance-block, .amount-row, .limit-block and
   .limit-head are reused, so only the leaf shapes and the two child-component
   surfaces (OptionsList, TransactionList) are restated here. */
.sk-hero { min-height: 12.5rem; border-radius: var(--radius-lg); }
.sk-status { width: 4.5rem; height: 1.63rem; border-radius: var(--radius-pill); }
.sk-reveal { align-self: center; width: 2.25rem; height: 2.25rem; border-radius: var(--radius-pill); }
.sk-actions { display: flex; gap: var(--space-3); width: 100%; }
.sk-action { flex: 1 1 0; height: 3rem; border-radius: var(--radius-pill); }
.sk-track { width: 100%; height: 0.4rem; border-radius: var(--radius-pill); }

.sk-options { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-card); overflow: hidden; }
.sk-option { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-4); }
.sk-option + .sk-option { border-top: 1px solid var(--divider); }
.sk-option-icon { width: 2.5rem; height: 2.5rem; border-radius: var(--radius-md); }
.sk-grow { flex: 1; min-width: 0; }
.sk-chev { width: 1.1rem; height: 1.1rem; border-radius: var(--radius-sm); }

.sk-tx-card { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-4); box-shadow: var(--shadow-card); }
.sk-tx-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-2); }
</style>
