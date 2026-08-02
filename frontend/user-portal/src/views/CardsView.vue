<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
  PlusIcon,
  ArrowUpRightIcon,
  EyeIcon,
  EyeSlashIcon,
  InformationCircleIcon,
  ReceiptPercentIcon,
  ChartBarSquareIcon,
} from '@heroicons/vue/24/outline'
import BalanceHeader from '../components/dashboard/BalanceHeader.vue'
import CardDetailHero from '../components/dashboard/CardDetailHero.vue'
import ActionButtons from '../components/dashboard/ActionButtons.vue'
import OptionsList from '../components/dashboard/OptionsList.vue'
import TransactionList from '../components/dashboard/TransactionList.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import { useProfileStore } from '../stores/profile'
import { useCards } from '../composables/useCards'
import { formatMoney } from '../utils/format'

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

        <section class="balance-block">
          <p class="label">Card Balance</p>
          <div class="amount-row">
            <p class="amount">
              <template v-if="hideBalance">
                <span class="int">••••••</span>
              </template>
              <template v-else>
                <span class="int">{{ currency }}{{ balanceParts.integer }}</span>
                <span class="frac">.{{ balanceParts.fraction }}</span>
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
  width: 2rem;
  height: 2rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-secondary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
}
.reveal-icon {
  width: 1rem;
  height: 1rem;
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
</style>
