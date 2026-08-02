<template>
  <div class="min-h-screen bg-[#080d18] text-white flex flex-col">
    <!-- Gradient header -->
    <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-6">
      <p class="text-slate-500 text-xs mb-1">Transfer complete</p>
      <h1 class="text-2xl font-bold text-white">Transfer submitted</h1>
    </div>

    <!-- Loading state -->
    <div v-if="loading" class="flex flex-col items-center justify-center py-16 gap-4">
      <div class="h-10 w-10 rounded-full border-2 border-blue-500 border-t-transparent animate-spin"></div>
      <p class="text-slate-400 text-sm">Loading transfer details...</p>
    </div>

    <template v-else-if="transfer">
      <!-- Success icon -->
      <div class="flex flex-col items-center py-10">
        <div class="h-20 w-20 rounded-full bg-emerald-500/20 flex items-center justify-center mb-4">
          <CheckCircleIcon class="h-12 w-12 text-emerald-400" />
        </div>
        <h2 class="text-2xl font-bold text-white">Transfer submitted</h2>
        <p class="text-slate-400 text-sm mt-2">Your payment is being processed</p>
      </div>

      <!-- Receipt card -->
      <div class="bg-[#111827] rounded-2xl mx-4 border border-[#1e2d44] divide-y divide-[#1e2d44]">
        <!-- Amount -->
        <div class="px-4 py-3.5 flex justify-between items-center">
          <span class="text-slate-400 text-sm">Amount</span>
          <span class="text-white font-semibold">
            {{ transfer.currency }}{{ formatMoney(transfer.amount) }}
          </span>
        </div>

        <!-- Reference -->
        <div class="px-4 py-3.5 flex justify-between items-center">
          <span class="text-slate-400 text-sm">Reference</span>
          <span class="text-white text-xs font-mono">{{ transfer.reference_id }}</span>
        </div>

        <!-- Beneficiary -->
        <div class="px-4 py-3.5 flex justify-between items-center">
          <span class="text-slate-400 text-sm">Beneficiary</span>
          <span class="text-white text-sm">{{ transfer.acct_name }}</span>
        </div>

        <!-- Bank -->
        <div class="px-4 py-3.5 flex justify-between items-center">
          <span class="text-slate-400 text-sm">Bank</span>
          <span class="text-white text-sm">{{ transfer.bank_name }}</span>
        </div>

        <!-- Country (wire only) -->
        <div v-if="transfer.type === 'wire'" class="px-4 py-3.5 flex justify-between items-center">
          <span class="text-slate-400 text-sm">Country</span>
          <span class="text-white text-sm">{{ transfer.acct_country }}</span>
        </div>

        <!-- Status -->
        <div class="px-4 py-3.5 flex justify-between items-center">
          <span class="text-slate-400 text-sm">Status</span>
          <span
            class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
            :class="statusClass"
          >
            {{ transfer.status_label }}
          </span>
        </div>
      </div>

      <!-- Action buttons -->
      <div class="px-4 mt-6 flex gap-3 pb-8">
        <button
          @click="router.push('/transactions')"
          class="flex-1 bg-[#1a2436] border border-[#1e2d44] hover:bg-[#1e2d44] text-white font-semibold rounded-2xl py-4 transition text-sm"
        >
          View activity
        </button>
        <button
          @click="router.push(transfer.type === 'domestic' ? '/domestic-transfer' : '/wire-transfer')"
          class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-4 transition text-sm"
        >
          New transfer
        </button>
      </div>
    </template>

    <!-- Error / fallback state -->
    <div v-else class="mx-4 mt-4">
      <div class="bg-red-500/10 border border-red-500/20 text-red-400 text-sm rounded-xl px-4 py-3 mb-4">
        {{ error || 'No transfer record found.' }}
      </div>
      <button
        @click="router.push('/transactions')"
        class="w-full bg-[#1a2436] border border-[#1e2d44] hover:bg-[#1e2d44] text-white font-semibold rounded-2xl py-4 transition text-sm"
      >
        Go to transactions
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { CheckCircleIcon } from '@heroicons/vue/24/outline'
import client from '../api/client'

const router = useRouter()
const loading = ref(true)
const transfer = ref(null)
const error = ref('')

const formatMoney = (value) =>
  Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })

const statusClass = computed(() => {
  const label = (transfer.value?.status_label || '').toLowerCase()
  if (label.includes('complet') || label.includes('success')) {
    return 'bg-emerald-500/20 text-emerald-400'
  }
  if (label.includes('fail') || label.includes('reject') || label.includes('cancel')) {
    return 'bg-red-500/20 text-red-400'
  }
  return 'bg-amber-500/20 text-amber-400'
})

onMounted(async () => {
  try {
    const { data } = await client.get('/api/user/transfer-success.php')
    if (!data?.ok) throw new Error(data?.message || 'Unable to load transfer')
    transfer.value = data.data
  } catch (err) {
    error.value = err?.response?.data?.message || err.message
  } finally {
    loading.value = false
  }
})
</script>
