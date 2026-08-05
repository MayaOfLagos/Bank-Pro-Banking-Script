<script setup>
import { computed } from 'vue'
import {
  ArrowDownLeftIcon,
  ArrowUpRightIcon,
  ArrowUpTrayIcon,
  BanknotesIcon,
  BuildingLibraryIcon,
  GlobeAltIcon,
} from '@heroicons/vue/24/solid'

const props = defineProps({
  transaction: { type: Object, required: true },
})

const isCredit = computed(() => Number(props.transaction?.trans_type) === 1)

// The unified ledger's five sources are every kind of movement the platform
// has. Rows written before it carry no `source` and can only have come from
// `transactions`, so the direction arrows are the right fallback.
const icon = computed(() => {
  switch (String(props.transaction?.source || '')) {
    case 'deposit':
      return BanknotesIcon
    case 'wire':
      return GlobeAltIcon
    case 'domestic':
      return BuildingLibraryIcon
    case 'withdrawal':
      return ArrowUpTrayIcon
    default:
      return isCredit.value ? ArrowDownLeftIcon : ArrowUpRightIcon
  }
})
</script>

<template>
  <span class="tx-avatar" :class="isCredit ? 'tx-avatar--credit' : 'tx-avatar--debit'" aria-hidden="true">
    <component :is="icon" class="tx-avatar-icon" />
  </span>
</template>

<style scoped>
.tx-avatar {
  /* Callers resize by setting --tx-avatar-size on any ancestor. */
  width: var(--tx-avatar-size, 2.5rem);
  height: var(--tx-avatar-size, 2.5rem);
  border-radius: var(--radius-pill);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.tx-avatar--credit {
  background: var(--accent-tint);
  color: var(--accent-strong);
}
.tx-avatar--debit {
  /* Mixed rather than --surface-muted, which is too close to the row's own
     white background for the disc to read at all. */
  background: color-mix(in srgb, var(--text-primary) 8%, transparent);
  color: var(--text-primary);
}
.tx-avatar-icon {
  width: 45%;
  height: 45%;
}
</style>
