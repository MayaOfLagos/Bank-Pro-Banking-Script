<script setup>
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { GlobeAltIcon, HomeIcon } from '@heroicons/vue/24/solid'
import BalanceHeader from '../components/dashboard/BalanceHeader.vue'
import ActionButtons from '../components/dashboard/ActionButtons.vue'
import CardCarousel from '../components/dashboard/CardCarousel.vue'
import BudgetWidget from '../components/dashboard/BudgetWidget.vue'
import TransactionList from '../components/dashboard/TransactionList.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import { useProfileStore } from '../stores/profile'
import { useCards } from '../composables/useCards'
import { useBudget } from '../composables/useBudget'
import { formatMoney } from '../utils/format'

const router = useRouter()

const profileStore = useProfileStore()
const cardsBundle = useCards()
const budget = useBudget()

onMounted(() => {
  profileStore.loadDashboard().catch(() => {})
  cardsBundle.load()
  budget.load()
})

const balances = computed(() => profileStore.balances ?? {})
const profile = computed(() => profileStore.profile ?? balances.value.profile ?? {})
const currency = computed(() => balances.value.currency || profile.value.currency || '$')

const balanceParts = computed(() => formatMoney(balances.value.acct_balance ?? 0, { trimTrailingZero: true }))

const firstName = computed(() => {
  const full = profile.value.full_name || `${profile.value.firstname || ''} ${profile.value.lastname || ''}`.trim()
  return String(full).split(' ')[0] || ''
})

const avatar = computed(() => profile.value.image || '')

const dashboardActions = [
  { label: 'Wire', icon: GlobeAltIcon, variant: 'filled', to: '/wire-transfer', ariaLabel: 'Send a wire transfer' },
  { label: 'Domestic', icon: HomeIcon, variant: 'outlined', to: '/domestic-transfer', ariaLabel: 'Send a domestic transfer' },
]

const recentTransactions = computed(() => {
  const rows = balances.value.recent_transactions
  return Array.isArray(rows) ? rows.slice(0, 4) : []
})

function onAddCard() {
  router.push('/cards')
}
</script>

<template>
  <div class="page">
    <div class="content">
      <BalanceHeader
        :first-name="firstName"
        :avatar-url="avatar"
        :has-unread="false"
      />

      <section class="balance-block" aria-live="polite">
        <p class="label">Your Balance</p>
        <p class="amount">
          <span class="int">{{ currency }}{{ balanceParts.integer }}</span>
          <span v-if="balanceParts.fraction" class="frac">.{{ balanceParts.fraction }}</span>
        </p>
        <ActionButtons :actions="dashboardActions" />
      </section>

      <CardCarousel
        :cards="cardsBundle.cards.value"
        :currency="currency"
        @add-card="onAddCard"
        @select-card="router.push('/cards')"
      />

      <BudgetWidget
        v-if="budget.limit.value !== null"
        :spent="budget.spent.value"
        :limit="budget.limit.value"
        :currency="budget.currency.value"
        :week-start="budget.weekStart.value"
        :week-end="budget.weekEnd.value"
      />

      <TransactionList
        :transactions="recentTransactions"
        :currency="currency"
        see-all-to="/transactions"
      />

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
.amount {
  margin: 0;
  color: var(--text-primary);
  font-weight: 800;
  line-height: 1;
}
.int {
  font-size: 3rem;
  letter-spacing: -0.02em;
}
.frac {
  font-size: 1.75rem;
  color: var(--text-primary);
  font-weight: 800;
  margin-left: 0.05rem;
}
</style>
