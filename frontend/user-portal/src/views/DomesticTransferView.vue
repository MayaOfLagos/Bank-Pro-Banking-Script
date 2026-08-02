<script setup>
import { useRouter } from 'vue-router'
import { z } from 'zod'
import FormField from '../components/ui/FormField.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import MoneyValue from '../components/ui/MoneyValue.vue'
import { useTransferForm } from '../composables/useTransferForm'
import { moneyAmount, nonEmptyString, accountNumber } from '../validation/schemas'

const router = useRouter()

const ACCOUNT_TYPES = ['Checking', 'Savings', 'Business Checking', 'Business Savings']

const schema = z.object({
  amount: moneyAmount({ label: 'Amount' }),
  acct_name: nonEmptyString('Beneficiary name'),
  bank_name: nonEmptyString('Bank name'),
  acct_number: accountNumber,
  acct_type: nonEmptyString('Account type'),
  acct_remarks: z.string().trim().max(500, 'Remarks are too long').optional().or(z.literal('')),
})

const {
  loading,
  canTransfer,
  meta,
  serverError,
  defineField,
  errors,
  isSubmitting,
  submit,
} = useTransferForm({
  metaEndpoint: '/api/user/domestic-transfer-meta.php',
  submitEndpoint: '/api/user/domestic-transfer-submit.php',
  schema,
  initialValues: {
    amount: '',
    acct_name: '',
    bank_name: '',
    acct_number: '',
    acct_type: '',
    acct_remarks: '',
  },
})

const [amount, amountAttrs] = defineField('amount')
const [acctName, acctNameAttrs] = defineField('acct_name')
const [bankName, bankNameAttrs] = defineField('bank_name')
const [acctNumber, acctNumberAttrs] = defineField('acct_number')
const [acctType, acctTypeAttrs] = defineField('acct_type')
const [acctRemarks, acctRemarksAttrs] = defineField('acct_remarks')
</script>

<template>
  <div class="min-h-screen bg-[#080d18] pb-28">
    <!-- Header -->
    <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-6">
      <p class="text-slate-400 text-sm">Domestic banking</p>
      <h1 class="text-2xl font-bold text-white mt-1">Domestic Transfer</h1>
    </div>

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
          <p class="text-white text-xl font-bold">
            <MoneyValue :value="meta.acct_balance" :currency="meta.currency" />
          </p>
        </div>
        <div class="h-10 w-10 rounded-full bg-blue-600/20 flex items-center justify-center">
          <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
          </svg>
        </div>
      </div>

      <!-- Restricted state -->
      <div v-if="!canTransfer" class="mx-4 mt-4 bg-[#111827] rounded-2xl p-8 text-center">
        <div class="h-14 w-14 rounded-full bg-red-500/20 flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
          </svg>
        </div>
        <h3 class="text-white font-semibold text-lg">Transfers Restricted</h3>
        <p class="text-slate-400 text-sm mt-2 leading-relaxed max-w-xs mx-auto">
          Domestic transfers are currently unavailable for your account. Contact support to restore access.
        </p>
        <button
          @click="router.push('/tickets')"
          class="mt-5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-3 px-6 transition text-sm"
        >
          Contact Support
        </button>
      </div>

      <!-- Transfer form -->
      <form v-else @submit.prevent="submit" class="px-4 mt-4 space-y-4">
        <FormField label="Transfer Amount" :error="errors.amount" required>
          <template #default="{ id, describedBy }">
            <input
              :id="id"
              v-bind="amountAttrs"
              v-model="amount"
              type="number"
              step="0.01"
              inputmode="decimal"
              placeholder="0.00"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.amount || null"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
            />
          </template>
        </FormField>

        <FormField label="Beneficiary Name" :error="errors.acct_name" required>
          <template #default="{ id, describedBy }">
            <input
              :id="id"
              v-bind="acctNameAttrs"
              v-model="acctName"
              type="text"
              autocomplete="name"
              placeholder="Full legal name"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.acct_name || null"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
            />
          </template>
        </FormField>

        <FormField label="Bank Name" :error="errors.bank_name" required>
          <template #default="{ id, describedBy }">
            <input
              :id="id"
              v-bind="bankNameAttrs"
              v-model="bankName"
              type="text"
              placeholder="Receiving bank name"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.bank_name || null"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
            />
          </template>
        </FormField>

        <FormField label="Account Number" :error="errors.acct_number" required>
          <template #default="{ id, describedBy }">
            <input
              :id="id"
              v-bind="acctNumberAttrs"
              v-model="acctNumber"
              type="text"
              inputmode="numeric"
              placeholder="Beneficiary account number"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.acct_number || null"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
            />
          </template>
        </FormField>

        <FormField label="Account Type" :error="errors.acct_type" required>
          <template #default="{ id, describedBy }">
            <select
              :id="id"
              v-bind="acctTypeAttrs"
              v-model="acctType"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.acct_type || null"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white focus:outline-none focus:border-blue-500 text-sm appearance-none"
            >
              <option value="" disabled class="bg-[#1a2436]">Select account type</option>
              <option v-for="type in ACCOUNT_TYPES" :key="type" :value="type" class="bg-[#1a2436]">{{ type }}</option>
            </select>
          </template>
        </FormField>

        <FormField label="Transfer Remarks" hint="Optional" :error="errors.acct_remarks">
          <template #default="{ id, describedBy }">
            <textarea
              :id="id"
              v-bind="acctRemarksAttrs"
              v-model="acctRemarks"
              rows="3"
              placeholder="Payment purpose or reference note"
              :aria-describedby="describedBy"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm resize-none"
            ></textarea>
          </template>
        </FormField>

        <ErrorState v-if="serverError" :message="serverError" compact />

        <button
          type="submit"
          :disabled="isSubmitting"
          class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-4 transition disabled:opacity-50 disabled:cursor-not-allowed mt-6"
        >
          {{ isSubmitting ? 'Processing...' : 'Send Domestic Transfer' }}
        </button>
      </form>
    </template>
  </div>
</template>
