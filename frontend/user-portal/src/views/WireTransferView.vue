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
  acct_country: '',
  acct_swift: '',
  acct_routing: '',
  acct_type: '',
  acct_remarks: ''
})

const accountTypes = ['Checking', 'Savings', 'Business Checking', 'Business Savings']

onMounted(async () => {
  try {
    const { data } = await client.get('/api/user/wire-transfer-meta.php')
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
    const { data } = await client.post('/api/user/wire-transfer-submit.php', {
      amount: form.amount,
      acct_name: form.acct_name,
      bank_name: form.bank_name,
      acct_number: form.acct_number,
      acct_country: form.acct_country,
      acct_swift: form.acct_swift,
      acct_routing: form.acct_routing,
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
      <p class="text-slate-400 text-sm">International banking</p>
      <h1 class="text-2xl font-bold text-white mt-1">Wire Transfer</h1>
    </div>

    <!-- Skeleton -->
    <template v-if="loading">
      <div class="mx-4 mt-4 h-20 rounded-2xl bg-[#111827] animate-pulse"></div>
      <div class="px-4 mt-4 space-y-4">
        <div v-for="i in 7" :key="i" class="h-14 rounded-xl bg-[#111827] animate-pulse"></div>
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
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253" />
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
        <p class="text-slate-400 text-sm mt-2 leading-relaxed max-w-xs mx-auto">Your account is not currently enabled for wire transfers. Please contact support to activate international transfers.</p>
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

        <!-- Account Number / IBAN -->
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">Account Number / IBAN</label>
          <input
            v-model="form.acct_number"
            type="text"
            required
            placeholder="Account number or IBAN"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
          />
        </div>

        <!-- Destination Country -->
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">Destination Country</label>
          <input
            v-model="form.acct_country"
            type="text"
            required
            placeholder="e.g. United States"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
          />
        </div>

        <!-- SWIFT / BIC (optional) -->
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">
            SWIFT / BIC Code
            <span class="text-slate-600 ml-1">(optional)</span>
          </label>
          <input
            v-model="form.acct_swift"
            type="text"
            placeholder="e.g. CHASUS33"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
          />
        </div>

        <!-- Routing Number (optional) -->
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">
            Routing / ABA Number
            <span class="text-slate-600 ml-1">(optional)</span>
          </label>
          <input
            v-model="form.acct_routing"
            type="text"
            placeholder="ABA routing number"
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
          {{ submitting ? 'Processing...' : 'Initiate Wire Transfer' }}
        </button>
      </form>
    </template>
  </div>
</template>
