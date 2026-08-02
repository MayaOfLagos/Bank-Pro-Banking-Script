<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import client from '../api/client'

const router = useRouter()

const loading = ref(true)
const submitting = ref(false)
const error = ref('')
const canTransfer = ref(false)
const meta = reactive({ acct_balance: '0.00', currency: '$' })

const form = reactive({
  amount: '',
  acct_name: '',
  bank_name: '',
  acct_number: '',
  acct_type: '',
  acct_remarks: ''
})

const accountTypes = ['Checking', 'Savings', 'Business Checking', 'Business Savings']

onMounted(async () => {
  try {
    const { data } = await client.get('/api/user/domestic-transfer-meta.php')
    if (data?.ok) {
      canTransfer.value = Boolean(data.data.can_transfer)
      meta.acct_balance = data.data.acct_balance ?? '0.00'
      meta.currency = data.data.currency ?? '$'
    }
  } catch {
    // leave defaults
  } finally {
    loading.value = false
  }
})

async function handleSubmit() {
  error.value = ''
  submitting.value = true
  try {
    const { data } = await client.post('/api/user/domestic-transfer-submit.php', {
      amount: form.amount,
      acct_name: form.acct_name,
      bank_name: form.bank_name,
      acct_number: form.acct_number,
      acct_type: form.acct_type,
      acct_remarks: form.acct_remarks
    })
    if (!data?.ok) throw new Error(data?.message || 'Transfer failed. Please try again.')
    router.push(data.data.next_route)
  } catch (err) {
    error.value = err?.response?.data?.message || err.message
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-[#080d18] pb-28">
    <!-- Header -->
    <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-6">
      <p class="text-slate-400 text-sm">Domestic banking</p>
      <h1 class="text-2xl font-bold text-white mt-1">Domestic Transfer</h1>
    </div>

    <!-- Skeleton -->
    <template v-if="loading">
      <div class="mx-4 mt-4 h-20 rounded-2xl bg-[#111827] animate-pulse"></div>
      <div class="px-4 mt-4 space-y-4">
        <div v-for="i in 5" :key="i" class="h-14 rounded-xl bg-[#111827] animate-pulse"></div>
      </div>
    </template>

    <template v-else>
      <!-- Balance pill -->
      <div class="bg-[#1a2436] rounded-2xl px-5 py-4 mx-4 mt-4 flex items-center justify-between">
        <div>
          <p class="text-slate-400 text-xs mb-0.5">Available balance</p>
          <p class="text-white text-xl font-bold">{{ meta.currency }}{{ meta.acct_balance }}</p>
        </div>
        <div class="h-10 w-10 rounded-full bg-blue-600/20 flex items-center justify-center">
          <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
          </svg>
        </div>
      </div>

      <!-- Cannot transfer notice -->
      <div v-if="!canTransfer" class="mx-4 mt-4 bg-[#111827] rounded-2xl p-8 text-center">
        <div class="h-14 w-14 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
          </svg>
        </div>
        <h3 class="text-white font-semibold text-lg">Transfers Restricted</h3>
        <p class="text-slate-400 text-sm mt-2 leading-relaxed max-w-xs mx-auto">Domestic transfers are currently unavailable for your account. Contact support to restore access.</p>
        <button
          @click="router.push('/tickets')"
          class="mt-5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-3 px-6 transition text-sm"
        >
          Contact Support
        </button>
      </div>

      <!-- Transfer form -->
      <form v-else @submit.prevent="handleSubmit" class="px-4 mt-4 space-y-4">
        <!-- Amount -->
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">Transfer Amount</label>
          <input
            v-model="form.amount"
            type="number"
            min="1"
            step="0.01"
            required
            placeholder="0.00"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
          />
        </div>

        <!-- Beneficiary Name -->
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">Beneficiary Name</label>
          <input
            v-model="form.acct_name"
            type="text"
            required
            placeholder="Full legal name"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
          />
        </div>

        <!-- Bank Name -->
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">Bank Name</label>
          <input
            v-model="form.bank_name"
            type="text"
            required
            placeholder="Receiving bank name"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
          />
        </div>

        <!-- Account Number -->
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">Account Number</label>
          <input
            v-model="form.acct_number"
            type="text"
            required
            placeholder="Beneficiary account number"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
          />
        </div>

        <!-- Account Type -->
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">Account Type</label>
          <select
            v-model="form.acct_type"
            required
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white focus:outline-none focus:border-blue-500 text-sm appearance-none"
          >
            <option value="" disabled class="bg-[#1a2436]">Select account type</option>
            <option v-for="type in accountTypes" :key="type" :value="type" class="bg-[#1a2436]">{{ type }}</option>
          </select>
        </div>

        <!-- Remarks (optional) -->
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">
            Transfer Remarks
            <span class="text-slate-600 ml-1">(optional)</span>
          </label>
          <textarea
            v-model="form.acct_remarks"
            rows="3"
            placeholder="Payment purpose or reference note"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm resize-none"
          ></textarea>
        </div>

        <!-- Error -->
        <div v-if="error" class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl px-4 py-3 mt-2">
          {{ error }}
        </div>

        <!-- Submit -->
        <button
          type="submit"
          :disabled="submitting"
          class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-4 transition disabled:opacity-50 disabled:cursor-not-allowed mt-6"
        >
          {{ submitting ? 'Processing...' : 'Send Domestic Transfer' }}
        </button>
      </form>
    </template>
  </div>
</template>
