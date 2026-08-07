<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { z } from 'zod'
import {
  ChevronLeftIcon, BanknotesIcon, UserIcon, BuildingLibraryIcon,
  HashtagIcon, GlobeAltIcon, LockClosedIcon,
  ExclamationTriangleIcon, ChatBubbleLeftEllipsisIcon,
} from '@heroicons/vue/24/solid'
import FormField from '../components/ui/FormField.vue'
import ErrorState from '../components/ui/ErrorState.vue'
import LoadingRegion from '../components/skeletons/LoadingRegion.vue'
import SkeletonBalanceCard from '../components/skeletons/SkeletonBalanceCard.vue'
import SkeletonField from '../components/skeletons/SkeletonField.vue'
import { useTransferForm } from '../composables/useTransferForm'
import {
  moneyAmount, nonEmptyString, accountNumber, swiftCode, routingNumber,
} from '../validation/schemas'
import { countryList } from '../utils/countries'
import { currencySymbol } from '../utils/currency'
import { formatMoneyInline } from '../utils/format'

const router = useRouter()

// Matches the legacy /user/wire-transfer.php option list so admin reports
// keep classifying transfers with the same vocabulary.
const ACCOUNT_TYPES = [
  'Savings', 'Current', 'Checking', 'Fixed Deposit',
  'Non Resident', 'Online Banking', 'Domiciliary', 'Joint',
]
const COUNTRIES = countryList()

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
  loading, canTransfer, meta, serverError,
  defineField, errors, isSubmitting, submit,
} = useTransferForm({
  metaEndpoint: '/api/user/wire-transfer-meta.php',
  submitEndpoint: '/api/user/wire-transfer-submit.php',
  schema,
  initialValues: {
    amount: '', acct_name: '', bank_name: '', acct_number: '',
    acct_country: '', acct_swift: '', acct_routing: '',
    acct_type: '', acct_remarks: '',
  },
})

const [amount, amountAttrs] = defineField('amount')
const [acctName, acctNameAttrs] = defineField('acct_name')
const [bankName, bankNameAttrs] = defineField('bank_name')
const [acctNumberField, acctNumberAttrs] = defineField('acct_number')
const [acctCountry, acctCountryAttrs] = defineField('acct_country')
const [acctSwift, acctSwiftAttrs] = defineField('acct_swift')
const [acctRouting, acctRoutingAttrs] = defineField('acct_routing')
const [acctType, acctTypeAttrs] = defineField('acct_type')
const [acctRemarks, acctRemarksAttrs] = defineField('acct_remarks')

// `meta` from useTransferForm is a `reactive` object, not a ref — access
// keys directly (no `.value`) or the reads become undefined and the
// balance falls back to 0.
const currencyText = computed(() => meta.currency || currencySymbol(meta.acct_currency || 'USD'))
const balanceDisplay = computed(() => `${currencyText.value}${formatMoneyInline(meta.acct_balance ?? 0, { trimTrailingZero: true })}`)
// Show the limit hint whenever the account has one configured (finite
// value). Depleted state (≤ 0) renders with a warning so the user
// knows the transfer will bounce before they hit Continue.
const hasLimit = computed(() => Number.isFinite(meta.limit_remain))
const limitDepleted = computed(() => hasLimit.value && meta.limit_remain <= 0)
const limitDisplay = computed(() => {
  const value = Math.max(0, meta.limit_remain)
  return `${currencyText.value}${formatMoneyInline(value, { trimTrailingZero: true })}`
})
</script>

<template>
  <div class="page">
    <div class="content">
      <header class="header">
        <button type="button" class="back" aria-label="Go back" @click="router.back()">
          <ChevronLeftIcon class="back-icon" aria-hidden="true" />
        </button>
        <div class="titles">
          <h1 class="title">Wire transfer</h1>
          <p class="subtitle">Send money internationally</p>
        </div>
      </header>

      <LoadingRegion v-if="loading" label="the transfer form">
        <SkeletonBalanceCard />
        <div class="form">
          <section class="form-card">
            <SkeletonField label="4.5rem" height="3.55rem" />
            <SkeletonField label="9rem" />
            <SkeletonField label="5.5rem" />
            <SkeletonField label="11rem" />
            <SkeletonField label="10rem" />
            <!-- SWIFT and Routing share a 1fr 1fr grid and both carry the
                 "Optional" hint, so the pair is a line taller than a plain row. -->
            <div class="pair">
              <SkeletonField label="6rem" hint="3.2rem" />
              <SkeletonField label="6.5rem" hint="3.2rem" />
            </div>
            <SkeletonField label="7rem" />
            <SkeletonField label="8rem" height="6.1rem" hint="3.2rem" />
          </section>
          <div class="skeleton sk-submit" />
        </div>
      </LoadingRegion>

      <template v-else>
        <section class="balance-card">
          <div class="balance-copy">
            <p class="balance-label">Available balance</p>
            <p class="balance-amount">{{ balanceDisplay }}</p>
            <p v-if="hasLimit" class="balance-limit" :class="{ 'balance-limit--depleted': limitDepleted }">
              <template v-if="limitDepleted">
                Transfer limit reached — contact support to raise it.
              </template>
              <template v-else>
                Remaining transfer limit · <strong>{{ limitDisplay }}</strong>
              </template>
            </p>
          </div>
          <div class="balance-icon">
            <BanknotesIcon aria-hidden="true" />
          </div>
        </section>

        <section v-if="!canTransfer" class="restricted">
          <div class="restricted-icon">
            <ExclamationTriangleIcon aria-hidden="true" />
          </div>
          <h3 class="restricted-title">Wire transfers restricted</h3>
          <p class="restricted-body">
            International transfers are not enabled on this account.
            Contact support to activate.
          </p>
          <button type="button" class="restricted-btn" @click="router.push('/tickets')">
            Contact support
          </button>
        </section>

        <form v-else class="form" @submit.prevent="submit">
          <section class="form-card">
            <FormField label="Amount" :error="errors.amount" required>
              <template #default="{ id, describedBy }">
                <div class="amount-input">
                  <span class="amount-currency">{{ currencyText }}</span>
                  <input
                    :id="id" v-bind="amountAttrs" v-model="amount"
                    type="number" step="0.01" inputmode="decimal" placeholder="0.00"
                    :aria-describedby="describedBy"
                    :aria-invalid="!!errors.amount || null"
                    class="input input--amount"
                  />
                </div>
              </template>
            </FormField>

            <FormField label="Beneficiary name" :error="errors.acct_name" required>
              <template #default="{ id, describedBy }">
                <div class="input-wrap">
                  <UserIcon class="input-icon" aria-hidden="true" />
                  <input :id="id" v-bind="acctNameAttrs" v-model="acctName" type="text"
                         autocomplete="name" placeholder="Full legal name"
                         :aria-describedby="describedBy" :aria-invalid="!!errors.acct_name || null"
                         class="input input--with-icon" />
                </div>
              </template>
            </FormField>

            <FormField label="Bank name" :error="errors.bank_name" required>
              <template #default="{ id, describedBy }">
                <div class="input-wrap">
                  <BuildingLibraryIcon class="input-icon" aria-hidden="true" />
                  <input :id="id" v-bind="bankNameAttrs" v-model="bankName" type="text"
                         placeholder="Receiving bank name"
                         :aria-describedby="describedBy" :aria-invalid="!!errors.bank_name || null"
                         class="input input--with-icon" />
                </div>
              </template>
            </FormField>

            <FormField label="Account number / IBAN" :error="errors.acct_number" required>
              <template #default="{ id, describedBy }">
                <div class="input-wrap">
                  <HashtagIcon class="input-icon" aria-hidden="true" />
                  <input :id="id" v-bind="acctNumberAttrs" v-model="acctNumberField" type="text"
                         placeholder="Account number or IBAN"
                         :aria-describedby="describedBy" :aria-invalid="!!errors.acct_number || null"
                         class="input input--with-icon input--mono" />
                </div>
              </template>
            </FormField>

            <FormField label="Destination country" :error="errors.acct_country" required>
              <template #default="{ id, describedBy }">
                <div class="input-wrap">
                  <GlobeAltIcon class="input-icon" aria-hidden="true" />
                  <select :id="id" v-bind="acctCountryAttrs" v-model="acctCountry"
                          :aria-describedby="describedBy" :aria-invalid="!!errors.acct_country || null"
                          class="input input--with-icon">
                    <option value="" disabled>Select destination country</option>
                    <!-- Value is the full name (not the ISO code) so admin
                         reports render a human-readable destination
                         alongside legacy records. -->
                    <option v-for="c in COUNTRIES" :key="c.code" :value="c.name">{{ c.name }}</option>
                  </select>
                </div>
              </template>
            </FormField>

            <div class="pair">
              <FormField label="SWIFT / BIC" hint="Optional" :error="errors.acct_swift">
                <template #default="{ id, describedBy }">
                  <div class="input-wrap">
                    <LockClosedIcon class="input-icon" aria-hidden="true" />
                    <input :id="id" v-bind="acctSwiftAttrs" v-model="acctSwift" type="text"
                           placeholder="e.g. CHASUS33"
                           :aria-describedby="describedBy" :aria-invalid="!!errors.acct_swift || null"
                           class="input input--with-icon input--mono" />
                  </div>
                </template>
              </FormField>

              <FormField label="Routing / ABA" hint="Optional" :error="errors.acct_routing">
                <template #default="{ id, describedBy }">
                  <div class="input-wrap">
                    <HashtagIcon class="input-icon" aria-hidden="true" />
                    <input :id="id" v-bind="acctRoutingAttrs" v-model="acctRouting" type="text"
                           inputmode="numeric" placeholder="9 digits"
                           :aria-describedby="describedBy" :aria-invalid="!!errors.acct_routing || null"
                           class="input input--with-icon input--mono" />
                  </div>
                </template>
              </FormField>
            </div>

            <FormField label="Account type" :error="errors.acct_type" required>
              <template #default="{ id, describedBy }">
                <div class="input-wrap">
                  <BuildingLibraryIcon class="input-icon" aria-hidden="true" />
                  <select :id="id" v-bind="acctTypeAttrs" v-model="acctType"
                          :aria-describedby="describedBy" :aria-invalid="!!errors.acct_type || null"
                          class="input input--with-icon">
                    <option value="" disabled>Select account type</option>
                    <option v-for="type in ACCOUNT_TYPES" :key="type" :value="type">{{ type }}</option>
                  </select>
                </div>
              </template>
            </FormField>

            <FormField label="Reference note" hint="Optional" :error="errors.acct_remarks">
              <template #default="{ id, describedBy }">
                <div class="input-wrap input-wrap--textarea">
                  <ChatBubbleLeftEllipsisIcon class="input-icon input-icon--top" aria-hidden="true" />
                  <textarea :id="id" v-bind="acctRemarksAttrs" v-model="acctRemarks" rows="3"
                            placeholder="Payment purpose or memo"
                            :aria-describedby="describedBy"
                            class="input input--with-icon input--textarea"></textarea>
                </div>
              </template>
            </FormField>
          </section>

          <ErrorState v-if="serverError" :message="serverError" compact />

          <button type="submit" class="submit" :disabled="isSubmitting">
            {{ isSubmitting ? 'Processing…' : 'Continue' }}
          </button>
        </form>
      </template>
    </div>
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
.header { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) 0 var(--space-1); }
.back { width: 2.5rem; height: 2.5rem; border-radius: 1.7rem; border: 1px solid var(--border); background: transparent; color: var(--text-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.back:hover { background: color-mix(in srgb, var(--text-primary) 6%, transparent); }
.back:active { transform: scale(0.95); }
.back-icon { width: 1.15rem; height: 1.15rem; }
.title { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; line-height: 1.1; }
.subtitle { font-size: 0.8rem; color: var(--text-secondary); margin: 0.15rem 0 0; }

.balance-card {
  display: flex; align-items: center; justify-content: space-between;
  gap: var(--space-3); padding: var(--space-4);
  background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-card);
}
.balance-label {
  font-size: 0.75rem; color: var(--text-secondary);
  text-transform: uppercase; letter-spacing: 0.06em; margin: 0 0 0.25rem;
}
.balance-amount { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.01em; }
.balance-limit { font-size: 0.75rem; color: var(--text-muted); margin: 0.35rem 0 0; }
.balance-limit strong { color: var(--text-secondary); font-weight: 700; }
.balance-limit--depleted { color: var(--warning-fg); font-weight: 600; }
.balance-icon {
  width: 2.75rem; height: 2.75rem; border-radius: var(--radius-pill);
  background: var(--accent-tint); color: var(--accent-strong);
  display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.balance-icon > svg { width: 1.35rem; height: 1.35rem; }

.restricted {
  background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-6);
  box-shadow: var(--shadow-card); text-align: center;
  display: flex; flex-direction: column; align-items: center; gap: var(--space-3);
}
.restricted-icon {
  width: 3rem; height: 3rem; border-radius: var(--radius-pill);
  background: var(--danger-bg); color: var(--danger-fg);
  display: inline-flex; align-items: center; justify-content: center;
}
.restricted-icon > svg { width: 1.5rem; height: 1.5rem; }
.restricted-title { font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.restricted-body { font-size: 0.9rem; color: var(--text-secondary); margin: 0; max-width: 20rem; line-height: 1.5; }
.restricted-btn {
  margin-top: var(--space-2); padding: 0.75rem 1.5rem;
  border-radius: var(--radius-pill); border: 1px solid var(--btn-outline-border);
  background: var(--surface); color: var(--text-primary); font-weight: 600; cursor: pointer;
}

.form { display: flex; flex-direction: column; gap: var(--space-4); }
.form-card {
  background: var(--surface); border-radius: var(--radius-lg);
  padding: var(--space-5) var(--space-4); box-shadow: var(--shadow-card);
  display: flex; flex-direction: column; gap: var(--space-4);
}
.pair { display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-3); }
@media (max-width: 480px) { .pair { grid-template-columns: 1fr; } }

.amount-input {
  display: flex; align-items: stretch;
  border-radius: var(--radius-md); background: var(--surface-muted);
  border: 1px solid transparent; overflow: hidden;
  transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
}
.amount-input:focus-within {
  border-color: var(--accent); background: var(--surface);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent);
}
.amount-currency {
  padding: 0.85rem 0.95rem; color: var(--text-secondary); font-weight: 700;
  background: transparent; border-right: 1px solid var(--border);
  display: inline-flex; align-items: center;
}
.input--amount {
  border: none; background: transparent; flex: 1; padding-left: 0.85rem;
  font-size: 1.15rem; font-weight: 700; color: var(--text-primary);
}

.input-wrap { position: relative; display: flex; align-items: center; }
.input-wrap--textarea { align-items: flex-start; }
.input {
  width: 100%; padding: 0.85rem 1rem;
  border-radius: var(--radius-md); border: 1px solid transparent;
  background: var(--surface-muted); color: var(--text-primary);
  font-size: 0.95rem; font-family: inherit; outline: none;
  transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease;
}
.input:focus {
  border-color: var(--accent); background: var(--surface);
  box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent);
}
.input--mono { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; letter-spacing: 0.02em; font-size: 0.9rem; }
.input--textarea { resize: none; padding-top: 0.85rem; }
.input-icon {
  position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%);
  width: 1.25rem; height: 1.25rem; color: var(--text-primary); pointer-events: none;
}
.input-icon--top { top: 0.95rem; transform: none; }
.input--with-icon { padding-left: 2.75rem; }

.submit {
  width: 100%; height: 3.1rem; padding: 0 var(--space-4);
  border-radius: var(--radius-pill); border: 1px solid transparent;
  background: var(--btn-primary-bg); color: var(--btn-primary-fg);
  font-weight: 700; font-size: 0.95rem; font-family: inherit;
  cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
  transition: transform 0.08s ease, opacity 0.15s ease;
}
.submit:hover:not(:disabled) { opacity: 0.92; }
.submit:active:not(:disabled) { transform: scale(0.98); }
.submit:disabled { opacity: 0.5; cursor: not-allowed; }

/* Skeleton — .form, .form-card and .pair are reused so the grid and the
   var(--space-4) field rhythm come from the same rules the real form uses. */
.sk-submit { width: 100%; height: 3.1rem; border-radius: var(--radius-pill); }
</style>
