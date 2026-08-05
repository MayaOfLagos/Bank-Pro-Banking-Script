<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowsRightLeftIcon, PlusIcon } from '@heroicons/vue/24/solid'
import BalanceHeader from '../components/dashboard/BalanceHeader.vue'
import ActionButtons from '../components/dashboard/ActionButtons.vue'
import CardCarousel from '../components/dashboard/CardCarousel.vue'
import LoanBalanceWidget from '../components/dashboard/LoanBalanceWidget.vue'
import TransactionList from '../components/dashboard/TransactionList.vue'
import TransferDrawer from '../components/layout/TransferDrawer.vue'
import DepositDrawer from '../components/layout/DepositDrawer.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import { useProfileStore } from '../stores/profile'
import { useCards } from '../composables/useCards'
import { formatMoney } from '../utils/format'

const router = useRouter()

const profileStore = useProfileStore()
const cardsBundle = useCards()

// 'transfer' | 'deposit' | null — one slot so the two sheets can never stack.
const openSheet = ref(null)

onMounted(() => {
  profileStore.loadDashboard().catch(() => {})
  cardsBundle.load()
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

const quickActions = computed(() => balances.value.quick_actions ?? {})

const loanBalance = computed(() => balances.value.loan_balance ?? 0)
const activeLoanCount = computed(() => Number(balances.value.counters?.loans_active) || 0)

/**
 * Both buttons open a drawer rather than navigating, because each one fans
 * out to several flows. A flag the backend reports as false drops its button
 * entirely — the endpoints behind it would just 403.
 */
const dashboardActions = computed(() => {
  const actions = []
  if (quickActions.value.can_transfer !== false) {
    actions.push({
      label: 'Transfer',
      icon: ArrowsRightLeftIcon,
      variant: 'filled',
      ariaLabel: 'Choose a transfer type',
      opensDialog: true,
      expanded: openSheet.value === 'transfer',
      onClick: () => { openSheet.value = 'transfer' },
    })
  }
  if (quickActions.value.can_deposit !== false) {
    actions.push({
      label: 'Deposit',
      icon: PlusIcon,
      variant: 'outlined',
      ariaLabel: 'Choose a deposit method',
      opensDialog: true,
      expanded: openSheet.value === 'deposit',
      onClick: () => { openSheet.value = 'deposit' },
    })
  }
  return actions
})

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
        <ActionButtons v-if="dashboardActions.length" :actions="dashboardActions" />
      </section>

      <CardCarousel
        :cards="cardsBundle.cards.value"
        :currency="currency"
        @add-card="onAddCard"
        @select-card="router.push('/cards')"
      />

      <LoanBalanceWidget
        :balance="loanBalance"
        :active-count="activeLoanCount"
        :currency="currency"
        @open="router.push('/loans')"
      />

      <TransactionList
        :transactions="recentTransactions"
        :currency="currency"
        see-all-to="/transactions"
      />

      <ErrorState v-if="cardsBundle.error.value" :message="cardsBundle.error.value" compact />
    </div>

    <TransferDrawer :open="openSheet === 'transfer'" side="top" @close="openSheet = null" />
    <DepositDrawer :open="openSheet === 'deposit'" side="top" @close="openSheet = null" />
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
