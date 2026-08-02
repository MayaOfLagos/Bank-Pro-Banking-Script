<script setup>
import { useRouter } from 'vue-router'
import { z } from 'zod'
import FormField from '../components/ui/FormField.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import MoneyValue from '../components/ui/MoneyValue.vue'
import { useTransferForm } from '../composables/useTransferForm'
import {
  moneyAmount,
  nonEmptyString,
  accountNumber,
  swiftCode,
  routingNumber,
} from '../validation/schemas'

const router = useRouter()

const ACCOUNT_TYPES = ['Checking', 'Savings', 'Business Checking', 'Business Savings']

const schema = z.object({
  amount: moneyAmount({ label: 'Amount' }),
  acct_name: nonEmptyString('Beneficiary name'),
  bank_name: nonEmptyString('Bank name'),
  acct_number: accountNumber,
  acct_country: nonEmptyString('Destination country'),
  acct_swift: swiftCode,
  acct_routing: routingNumber,
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
  metaEndpoint: '/api/user/wire-transfer-meta.php',
  submitEndpoint: '/api/user/wire-transfer-submit.php',
  schema,
  initialValues: {
    amount: '',
    acct_name: '',
    bank_name: '',
    acct_number: '',
    acct_country: '',
    acct_swift: '',
    acct_routing: '',
    acct_type: '',
    acct_remarks: '',
  },
})

const [amount, amountAttrs] = defineField('amount')
const [acctName, acctNameAttrs] = defineField('acct_name')
const [bankName, bankNameAttrs] = defineField('bank_name')
const [acctNumber, acctNumberAttrs] = defineField('acct_number')
const [acctCountry, acctCountryAttrs] = defineField('acct_country')
const [acctSwift, acctSwiftAttrs] = defineField('acct_swift')
const [acctRouting, acctRoutingAttrs] = defineField('acct_routing')
const [acctType, acctTypeAttrs] = defineField('acct_type')
const [acctRemarks, acctRemarksAttrs] = defineField('acct_remarks')
</script>

<template>
  <div class="min-h-screen bg-[#080d18] pb-28">
    <!-- Header -->
    <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-6">
      <p class="text-slate-400 text-sm">International banking</p>
      <h1 class="text-2xl font-bold text-white mt-1">Wire Transfer</h1>
    </div>

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
          <p class="text-white text-xl font-bold">
            <MoneyValue :value="meta.acct_balance" :currency="meta.currency" />
          </p>
        </div>
        <div class="h-10 w-10 rounded-full bg-blue-600/20 flex items-center justify-center">
          <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253" />
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
          Your account is not currently enabled for wire transfers. Please contact support to activate international transfers.
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

        <FormField label="Account Number / IBAN" :error="errors.acct_number" required>
          <template #default="{ id, describedBy }">
            <input
              :id="id"
              v-bind="acctNumberAttrs"
              v-model="acctNumber"
              type="text"
              placeholder="Account number or IBAN"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.acct_number || null"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
            />
          </template>
        </FormField>

        <FormField label="Destination Country" :error="errors.acct_country" required>
          <template #default="{ id, describedBy }">
            <input
              :id="id"
              v-bind="acctCountryAttrs"
              v-model="acctCountry"
              type="text"
              autocomplete="country-name"
              placeholder="e.g. United States"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.acct_country || null"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
            />
          </template>
        </FormField>

        <FormField label="SWIFT / BIC Code" hint="Optional" :error="errors.acct_swift">
          <template #default="{ id, describedBy }">
            <input
              :id="id"
              v-bind="acctSwiftAttrs"
              v-model="acctSwift"
              type="text"
              placeholder="e.g. CHASUS33"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.acct_swift || null"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
            />
          </template>
        </FormField>

        <FormField label="Routing / ABA Number" hint="Optional" :error="errors.acct_routing">
          <template #default="{ id, describedBy }">
            <input
              :id="id"
              v-bind="acctRoutingAttrs"
              v-model="acctRouting"
              type="text"
              inputmode="numeric"
              placeholder="9-digit ABA routing number"
              :aria-describedby="describedBy"
              :aria-invalid="!!errors.acct_routing || null"
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
          {{ isSubmitting ? 'Processing...' : 'Initiate Wire Transfer' }}
        </button>
      </form>
    </template>
  </div>
</template>
