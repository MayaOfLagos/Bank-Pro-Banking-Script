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
import LoadingRegion from '../components/skeletons/LoadingRegion.vue'
import SkeletonText from '../components/skeletons/SkeletonText.vue'
import SkeletonCardCarousel from '../components/skeletons/SkeletonCardCarousel.vue'
import SkeletonTransactionRows from '../components/skeletons/SkeletonTransactionRows.vue'
import { useProfileStore } from '../stores/profile'
import { useCards } from '../composables/useCards'
import { formatMoney } from '../utils/format'

const router = useRouter()

const profileStore = useProfileStore()
const cardsBundle = useCards()

// 'transfer' | 'deposit' | null — one slot so the two sheets can never stack.
const openSheet = ref(null)

// useCards() has no "has run yet" flag and its `loading` ref is still false on
// first paint, so track settlement here. load() swallows its own errors, hence
// finally rather than then.
const cardsSettled = ref(false)

onMounted(() => {
  profileStore.loadDashboard().catch(() => {})
  cardsBundle.load().finally(() => { cardsSettled.value = true })
})

/*
 * Drive the skeleton off "have we got a payload yet", not off the store's
 * `loading` flag: the flag is false for the tick between mount and the request
 * actually starting, which is exactly long enough to paint a $0.00 balance and
 * an empty card carousel before swapping them out. Errors settle it too, so a
 * failed fetch falls through to the (empty-state) UI rather than spinning
 * forever.
 */
const dashboardReady = computed(() => !!profileStore.balances || !!profileStore.error)

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

      <!--
        The header stays live while the rest loads: it degrades to "Hi there"
        on its own, and skeletoning it would take the theme toggle and the
        notifications link away from someone waiting on a slow connection.
      -->
      <LoadingRegion v-if="!dashboardReady" label="your account">
        <section class="balance-block">
          <SkeletonText size="0.85rem" width="6.5rem" />
          <SkeletonText size="3rem" :line-height="1" width="11rem" />
          <div class="sk-actions">
            <div class="skeleton sk-action" />
            <div class="skeleton sk-action" />
          </div>
        </section>

        <SkeletonCardCarousel />

        <div class="sk-widget">
          <div class="skeleton sk-widget-icon" />
          <div class="sk-widget-meta">
            <SkeletonText size="0.95rem" width="6.5rem" />
            <SkeletonText class="sk-sub" size="0.75rem" width="5rem" />
          </div>
          <div class="sk-widget-amounts">
            <SkeletonText size="1rem" width="4.5rem" />
            <SkeletonText class="sk-sub" size="0.75rem" width="2.6rem" />
          </div>
        </div>

        <section class="sk-list">
          <div class="sk-list-head">
            <SkeletonText size="1rem" width="6rem" />
            <div class="skeleton sk-chev" />
          </div>
          <SkeletonTransactionRows :rows="4" />
        </section>
      </LoadingRegion>

      <template v-else>
        <section class="balance-block" aria-live="polite">
          <p class="label">Your Balance</p>
          <p class="amount">
            <span class="int">{{ currency }}{{ balanceParts.integer }}</span>
            <span v-if="balanceParts.fraction" class="frac">.{{ balanceParts.fraction }}</span>
          </p>
          <ActionButtons v-if="dashboardActions.length" :actions="dashboardActions" />
        </section>

        <!-- Cards resolve on their own request, so the carousel keeps its own
             placeholder rather than blocking the balance behind it. -->
        <SkeletonCardCarousel v-if="!cardsSettled" />
        <CardCarousel
          v-else
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
      </template>
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

/*
 * Skeleton — mirrors ActionButtons (.btn 3rem pill, flex 1 1 0, gap space-3),
 * LoanBalanceWidget (space-4 padding, 2.75rem radius-md icon) and
 * TransactionList (space-4 padding, head margin-bottom space-2, 1.1rem chevron).
 */
.sk-actions {
  display: flex;
  gap: var(--space-3);
  width: 100%;
}
.sk-action {
  flex: 1 1 0;
  height: 3rem;
  border-radius: var(--radius-pill);
}
.sk-widget {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-card);
}
.sk-widget-icon {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-md);
}
.sk-widget-meta {
  flex: 1;
  min-width: 0;
}
.sk-widget-amounts {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: flex-end;
}
.sk-sub {
  margin-top: 0.15rem;
}
.sk-list {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-card);
}
.sk-list-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-2);
}
.sk-chev {
  width: 1.1rem;
  height: 1.1rem;
  border-radius: var(--radius-sm);
}
</style>
