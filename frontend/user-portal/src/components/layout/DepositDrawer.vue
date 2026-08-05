<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { CurrencyDollarIcon, BuildingLibraryIcon } from '@heroicons/vue/24/solid'
import SwipeDrawer from '../ui/SwipeDrawer.vue'
import DrawerTileGrid from '../ui/DrawerTileGrid.vue'
import { useDeposit } from '../../composables/useDeposit'

const props = defineProps({
  open: { type: Boolean, default: false },
  side: { type: String, default: 'top' },
})

const emit = defineEmits(['close'])

const router = useRouter()
const dep = useDeposit()

const metaLoaded = ref(false)

/**
 * Bank deposit is an admin toggle, and DepositView drops the Bank tab
 * entirely when it is off. Offering the tile regardless would land the
 * customer on a page missing the thing they just picked, so the tile only
 * appears once deposits-meta confirms the method is live — which also means
 * a failed meta request degrades to crypto-only rather than to a dead end.
 */
const options = computed(() => {
  const tiles = [
    {
      key: 'crypto',
      label: 'Crypto Deposit',
      hint: 'Fund from a crypto wallet',
      method: 'crypto',
      icon: CurrencyDollarIcon,
      tone: 'accent',
    },
  ]
  if (dep.bankDepositEnabled.value) {
    tiles.push({
      key: 'bank',
      label: 'Bank Deposit',
      hint: 'Wire in from your own bank',
      method: 'bank',
      icon: BuildingLibraryIcon,
      tone: 'muted',
    })
  }
  return tiles
})

function go(option) {
  emit('close')
  router.push({ path: '/deposits', query: { method: option.method } })
}

watch(
  () => props.open,
  (open) => {
    if (!open || metaLoaded.value) return
    metaLoaded.value = true
    dep.loadMeta().catch(() => {})
  },
)
</script>

<template>
  <SwipeDrawer :open="open" :side="side" title="Add money" @close="emit('close')">
    <p class="lead">Choose how you want to fund your account.</p>
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
