<script setup>
import { RouterLink } from 'vue-router'
import { ChevronRightIcon } from '@heroicons/vue/24/outline'
import TransactionItem from './TransactionItem.vue'
import { ledgerDetailPath } from '../../utils/ledger'

defineProps({
  transactions: { type: Array, default: () => [] },
  currency: { type: String, default: '$' },
  seeAllTo: { type: String, default: '/transactions' },
  title: { type: String, default: 'Transactions' },
  emptyMessage: { type: String, default: 'No transactions yet.' },
})
</script>

<template>
  <section class="list">
    <div class="head">
      <h2 class="title">{{ title }}</h2>
      <RouterLink :to="seeAllTo" class="see-all" :aria-label="`See all ${title.toLowerCase()}`">
        <ChevronRightIcon class="chev" aria-hidden="true" />
      </RouterLink>
    </div>

    <div v-if="!transactions.length" class="empty">{{ emptyMessage }}</div>

    <div v-else class="rows">
      <TransactionItem
        v-for="(tx, i) in transactions"
        :key="tx.id ?? tx.trans_id ?? i"
        :transaction="tx"
        :currency="currency"
        :to="ledgerDetailPath(tx)"
      />
    </div>
  </section>
</template>

<style scoped>
.list {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-4);
  box-shadow: var(--shadow-card);
}
.head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: var(--space-2);
}
.title {
  font-size: 1rem;
  font-weight: 700;
  color: var(--text-primary);
  margin: 0;
}
.see-all {
  color: var(--text-secondary);
  text-decoration: none;
}
.chev {
  width: 1.1rem;
  height: 1.1rem;
}
.empty {
  color: var(--text-secondary);
  font-size: 0.85rem;
  padding: var(--space-6) 0;
  text-align: center;
}
.rows > * + * {
  border-top: 1px solid var(--divider);
}
</style>
