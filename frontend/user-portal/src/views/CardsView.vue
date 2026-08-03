<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
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
import { useProfileStore } from '../stores/profile'
import { useCards } from '../composables/useCards'
import { formatMoney, formatMoneyInline } from '../utils/format'

const HIDE_KEY = 'bankpro:hide-card-balance'

const router = useRouter()
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

onMounted(() => {
  profileStore.loadDashboard().catch(() => {})
  profileStore.loadProfile().catch(() => {})
  cardsBundle.load()
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
</script>

<template>
  <div class="page">
    <div class="content">
      <BalanceHeader
        :first-name="firstName"
        :avatar-url="profile.image || ''"
        :has-unread="false"
      />

      <template v-if="card">
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
        <button v-if="cardsBundle.canRequest.value" type="button" class="request-btn" @click="router.push('/tickets')">
          Request a card
        </button>
      </EmptyState>

      <ErrorState v-if="cardsBundle.error.value" :message="cardsBundle.error.value" compact />
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
</style>
