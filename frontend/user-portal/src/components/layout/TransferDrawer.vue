<script setup>
import { useRouter } from 'vue-router'
import {
  GlobeAltIcon,
  PaperAirplaneIcon,
  BanknotesIcon,
} from '@heroicons/vue/24/solid'
import SwipeDrawer from '../ui/SwipeDrawer.vue'
import DrawerTileGrid from '../ui/DrawerTileGrid.vue'

defineProps({
  open: { type: Boolean, default: false },
  // The nav bar opens this as a bottom sheet; the dashboard button opens the
  // same content from the top, nearer where it was tapped.
  side: { type: String, default: 'bottom' },
})

const emit = defineEmits(['close'])

const router = useRouter()

// The three money-out flows that actually have endpoints and views. Legacy
// listed Domestic and Wire under transfers and Withdrawal separately, but
// all three are "send money out" from the customer's side, so they belong
// on one sheet rather than splitting one across the bar and the More menu.
const options = [
  {
    key: 'domestic',
    label: 'Domestic Transfer',
    hint: 'To another bank in your country',
    to: '/domestic-transfer',
    icon: PaperAirplaneIcon,
    tone: 'accent',
  },
  {
    key: 'wire',
    label: 'Wire Transfer',
    hint: 'International, sent by SWIFT',
    to: '/wire-transfer',
    icon: GlobeAltIcon,
    tone: 'accent',
  },
  {
    key: 'withdrawal',
    label: 'Withdrawal',
    hint: 'Cash out to a linked bank',
    to: '/withdrawals',
    icon: BanknotesIcon,
    tone: 'muted',
  },
]

function go(option) {
  emit('close')
  router.push(option.to)
}
</script>

<template>
  <SwipeDrawer :open="open" :side="side" title="Send money" @close="emit('close')">
    <p class="lead">Choose how you want to move your money.</p>
    <DrawerTileGrid :options="options" @select="go" />
  </SwipeDrawer>
</template>

<style scoped>
.lead {
  margin: 0 0 var(--space-4);
  font-size: 0.82rem;
  color: var(--text-secondary);
}
</style>
