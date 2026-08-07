<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  ChevronLeftIcon,
  ClipboardDocumentIcon,
  PhotoIcon,
  ExclamationTriangleIcon,
  BuildingLibraryIcon,
  CurrencyDollarIcon,
} from '@heroicons/vue/24/solid'
import { useDeposit } from '../composables/useDeposit'
import { formatMoneyInline } from '../utils/format'
import ErrorState from '../components/ui/ErrorState.vue'
import LoadingRegion from '../components/skeletons/LoadingRegion.vue'
import SkeletonText from '../components/skeletons/SkeletonText.vue'

const route = useRoute()
const router = useRouter()
const toast = useToast()
const dep = useDeposit()

const TABS = ['Crypto', 'Bank']
const activeTab = ref('Crypto')

const amount = ref('')
const cryptoId = ref('')
const walletAddress = ref('')
const imageFile = ref(null)
const imagePreview = ref('')
const submitError = ref('')

const selectedCrypto = computed(() => dep.cryptoList.value.find((c) => String(c.id) === String(cryptoId.value)) || null)

watch(selectedCrypto, (crypto) => {
  walletAddress.value = crypto?.wallet_address || ''
})

const amountNumber = computed(() => Number(amount.value) || 0)

const canPickBankTab = computed(() => dep.bankDepositEnabled.value)
const tabList = computed(() => (canPickBankTab.value ? TABS : ['Crypto']))

onMounted(async () => {
  // ?method=bank comes from the dashboard deposit drawer. Applied after meta
  // resolves so a stale or hand-typed link cannot select a tab the admin has
  // switched off.
  const requested = String(route.query.method || '').toLowerCase()
  await dep.loadMeta()
  if (requested === 'bank' && canPickBankTab.value) activeTab.value = 'Bank'
  if (!canPickBankTab.value) activeTab.value = 'Crypto'
})

function onImagePicked(event) {
  const file = event.target?.files?.[0] || null
  imageFile.value = file
  if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
  imagePreview.value = file ? URL.createObjectURL(file) : ''
}

function validate() {
  submitError.value = ''
  if (!dep.canSubmit.value) {
    submitError.value = 'Your account status does not allow deposits right now.'
    return false
  }
  if (!amountNumber.value || amountNumber.value <= 0) {
    submitError.value = 'Enter a deposit amount.'
    return false
  }
  if (dep.limitMin.value > 0 && amountNumber.value < dep.limitMin.value) {
    submitError.value = `Minimum deposit is ${dep.currency.value}${formatMoneyInline(dep.limitMin.value)}.`
    return false
  }
  if (dep.limitMax.value > 0 && amountNumber.value > dep.limitMax.value) {
    submitError.value = `Maximum deposit is ${dep.currency.value}${formatMoneyInline(dep.limitMax.value)}.`
    return false
  }
  if (!cryptoId.value) {
    submitError.value = 'Pick a cryptocurrency.'
    return false
  }
  if (!walletAddress.value) {
    submitError.value = 'Wallet address is missing — reselect the currency.'
    return false
  }
  if (!imageFile.value) {
    submitError.value = 'Attach your payment screenshot.'
    return false
  }
  return true
}

async function onSubmit() {
  if (!validate()) {
    toast.error(submitError.value)
    return
  }
  try {
    const res = await dep.submit({
      amount: amountNumber.value,
      cryptoId: cryptoId.value,
      walletAddress: walletAddress.value,
      image: imageFile.value,
    })
    toast.success(res?.message || 'Deposit submitted successfully.')
    // Reset the form so the user isn't tempted to double-submit.
    amount.value = ''
    cryptoId.value = ''
    walletAddress.value = ''
    imageFile.value = null
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value)
    imagePreview.value = ''
    router.push('/transactions')
  } catch (err) {
    const msg = err?.response?.data?.message || err.message || 'Deposit failed'
    submitError.value = msg
    toast.error(msg)
  }
}

async function copy(text, label) {
  if (!text) return
  try {
    await navigator.clipboard.writeText(text)
    toast.success(`${label} copied`, { timeout: 1500 })
  } catch {
    toast.error('Copy failed — long-press the field instead.')
  }
}

const bankFields = computed(() => {
  const b = dep.virtualBank.value || {}
  return [
    { label: 'Bank name', value: b.bank_name || '' },
    { label: 'Account number', value: b.acct_no || '' },
    { label: 'Routing number', value: b.routine_no || '' },
    { label: 'SWIFT / BIC', value: b.swift_code || '' },
  ]
})

const limitHint = computed(() => {
  const min = dep.limitMin.value
  const max = dep.limitMax.value
  if (!min && !max) return ''
  const parts = []
  if (min) parts.push(`Min ${dep.currency.value}${formatMoneyInline(min)}`)
  if (max) parts.push(`Max ${dep.currency.value}${formatMoneyInline(max)}`)
  return parts.join(' · ')
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
          <h1 class="title">Deposit</h1>
          <p class="subtitle">Fund your account</p>
        </div>
      </header>

      <div v-if="!dep.canSubmit.value && !dep.loading.value" class="hold-banner">
        <ExclamationTriangleIcon class="hold-icon" aria-hidden="true" />
        <div>
          <p class="hold-title">Deposits are paused for this account</p>
          <p class="hold-body">Contact support to lift the hold and continue funding.</p>
        </div>
      </div>

      <div v-if="tabList.length > 1" class="tabs" role="tablist" aria-label="Deposit method">
        <button
          v-for="tab in tabList"
          :key="tab"
          type="button"
          role="tab"
          :aria-selected="activeTab === tab"
          class="tab"
          :class="{ 'tab--active': activeTab === tab }"
          @click="activeTab = tab"
        >
          <CurrencyDollarIcon v-if="tab === 'Crypto'" class="tab-icon" aria-hidden="true" />
          <BuildingLibraryIcon v-else class="tab-icon" aria-hidden="true" />
          {{ tab }}
        </button>
      </div>

      <ErrorState v-if="dep.error.value" :message="dep.error.value" compact />

      <!-- Mirrors the Crypto tab, which is the tab this view always opens on.
           The tab strip itself is deliberately absent: it only renders once meta
           says bank deposits are enabled, so drawing it here would guarantee a
           shift for every account that has them switched off. -->
      <LoadingRegion v-if="dep.loading.value" label="deposit options">
        <section class="card">
          <div class="form">
            <div class="field">
              <SkeletonText size="0.8rem" :line-height="1.2" width="4rem" />
              <div class="skeleton sk-input" />
              <SkeletonText size="0.75rem" width="11rem" />
            </div>

            <div class="field">
              <SkeletonText size="0.8rem" :line-height="1.2" width="7.5rem" />
              <div class="skeleton sk-input" />
            </div>

            <div class="field">
              <SkeletonText size="0.8rem" :line-height="1.2" width="7rem" />
              <div class="copy-row">
                <div class="skeleton sk-input" />
                <div class="skeleton sk-copy" />
              </div>
            </div>

            <div class="field">
              <SkeletonText size="0.8rem" :line-height="1.2" width="9.5rem" />
              <div class="skeleton sk-upload" />
            </div>

            <div class="skeleton sk-submit" />
          </div>
        </section>
      </LoadingRegion>

      <section v-else-if="activeTab === 'Crypto'" class="card" aria-label="Crypto deposit">
        <form class="form" @submit.prevent="onSubmit">
          <div class="field">
            <label for="dep-amount" class="label">Amount</label>
            <div class="amount-input">
              <span class="amount-currency">{{ dep.currency.value }}</span>
              <input
                id="dep-amount"
                v-model="amount"
                type="number"
                inputmode="decimal"
                step="0.01"
                min="0"
                placeholder="0.00"
                autocomplete="off"
                class="input input--amount"
                :disabled="!dep.canSubmit.value"
              />
            </div>
            <p v-if="limitHint" class="hint">{{ limitHint }}</p>
          </div>

          <div class="field">
            <label for="dep-crypto" class="label">Cryptocurrency</label>
            <select
              id="dep-crypto"
              v-model="cryptoId"
              class="input"
              :disabled="!dep.canSubmit.value || !dep.cryptoList.value.length"
            >
              <option value="" disabled>Select a currency</option>
              <option
                v-for="c in dep.cryptoList.value"
                :key="c.id"
                :value="c.id"
              >
                {{ c.crypto_name }}
              </option>
            </select>
          </div>

          <div class="field">
            <label for="dep-wallet" class="label">Wallet address</label>
            <div class="copy-row">
              <input
                id="dep-wallet"
                :value="walletAddress"
                type="text"
                class="input input--mono"
                readonly
                placeholder="Pick a currency to reveal wallet"
              />
              <button
                type="button"
                class="copy-btn"
                :disabled="!walletAddress"
                aria-label="Copy wallet address"
                @click="copy(walletAddress, 'Wallet address')"
              >
                <ClipboardDocumentIcon class="copy-icon" aria-hidden="true" />
              </button>
            </div>
          </div>

          <div class="field">
            <label class="label">Payment screenshot</label>
            <label class="upload">
              <input
                type="file"
                accept="image/png,image/jpeg"
                class="upload-input"
                :disabled="!dep.canSubmit.value"
                @change="onImagePicked"
              />
              <template v-if="imagePreview">
                <img :src="imagePreview" alt="Payment screenshot preview" class="upload-preview" />
              </template>
              <template v-else>
                <PhotoIcon class="upload-icon" aria-hidden="true" />
                <span class="upload-label">Tap to upload PNG or JPG</span>
                <span class="upload-hint">Up to 10 MB</span>
              </template>
            </label>
          </div>

          <p v-if="submitError" class="submit-error">{{ submitError }}</p>

          <button
            type="submit"
            class="submit"
            :disabled="dep.submitting.value || !dep.canSubmit.value"
          >
            {{ dep.submitting.value ? 'Submitting…' : 'Submit deposit' }}
          </button>
        </form>
      </section>

      <section v-else class="card" aria-label="Bank deposit">
        <p class="intro">
          Wire funds to the account below using your online banking, then keep your receipt. Deposits usually clear within one business day.
        </p>
        <div class="bank-list">
          <div v-for="field in bankFields" :key="field.label" class="bank-row">
            <div class="bank-meta">
              <p class="bank-label">{{ field.label }}</p>
              <p class="bank-value">{{ field.value || '—' }}</p>
            </div>
            <button
              type="button"
              class="copy-btn"
              :disabled="!field.value"
              :aria-label="`Copy ${field.label}`"
              @click="copy(field.value, field.label)"
            >
              <ClipboardDocumentIcon class="copy-icon" aria-hidden="true" />
            </button>
          </div>
        </div>
      </section>
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
.header {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-2) 0 var(--space-1);
}
.back {
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 1.7rem;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--text-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  flex-shrink: 0;
}
.back-icon { width: 1.15rem; height: 1.15rem; }
.title {
  font-size: 1.5rem;
  font-weight: 800;
  color: var(--text-primary);
  margin: 0;
  line-height: 1.1;
}
.subtitle {
  font-size: 0.8rem;
  color: var(--text-secondary);
  margin: 0.15rem 0 0;
}
.hold-banner {
  display: flex;
  gap: var(--space-3);
  padding: var(--space-4);
  background: var(--danger-bg);
  border: 1px solid var(--danger-border);
  border-radius: var(--radius-lg);
  color: var(--danger-fg);
}
.hold-icon { width: 1.4rem; height: 1.4rem; flex-shrink: 0; margin-top: 0.1rem; }
.hold-title { font-weight: 700; margin: 0; font-size: 0.9rem; }
.hold-body { font-size: 0.8rem; margin: 0.2rem 0 0; color: color-mix(in srgb, var(--danger-fg) 80%, transparent); }
.tabs {
  display: flex;
  gap: var(--space-2);
}
.tab {
  flex: 1;
  padding: 0.7rem 1rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border);
  background: var(--surface);
  color: var(--text-secondary);
  font-size: 0.85rem;
  font-weight: 600;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.4rem;
  transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.tab-icon { width: 1rem; height: 1rem; }
.tab--active {
  background: var(--btn-primary-bg);
  color: var(--btn-primary-fg);
  border-color: var(--btn-primary-bg);
}
.card {
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-5) var(--space-4);
  box-shadow: var(--shadow-card);
}
.form { display: flex; flex-direction: column; gap: var(--space-4); }
.field { display: flex; flex-direction: column; gap: var(--space-2); }
.label {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-secondary);
}
.input {
  width: 100%;
  padding: 0.7rem 0.85rem;
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
  background: var(--surface-muted);
  color: var(--text-primary);
  font-size: 0.95rem;
  font-family: inherit;
  outline: none;
  transition: border-color 0.15s ease, background-color 0.15s ease;
}
.input:focus {
  border-color: var(--accent);
  background: var(--surface);
}
.input:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.input--mono {
  font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
  font-size: 0.85rem;
  letter-spacing: 0.02em;
}
.amount-input {
  display: flex;
  align-items: stretch;
  border-radius: var(--radius-md);
  background: var(--surface-muted);
  border: 1px solid var(--border);
  overflow: hidden;
}
.amount-input:focus-within {
  border-color: var(--accent);
  background: var(--surface);
}
.amount-currency {
  padding: 0.7rem 0.85rem;
  color: var(--text-secondary);
  font-weight: 700;
  background: transparent;
  border-right: 1px solid var(--border);
  display: inline-flex;
  align-items: center;
}
.input--amount {
  border: none;
  background: transparent;
  flex: 1;
  padding-left: 0.75rem;
}
.hint {
  font-size: 0.75rem;
  color: var(--text-muted);
  margin: 0;
}
.copy-row {
  display: flex;
  gap: var(--space-2);
  align-items: stretch;
}
.copy-row .input { flex: 1; }
.copy-btn {
  width: 2.75rem;
  border-radius: var(--radius-md);
  border: 1px solid var(--border);
  background: var(--surface-muted);
  color: var(--text-primary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.15s ease;
  flex-shrink: 0;
}
.copy-btn:hover { background: color-mix(in srgb, var(--accent) 10%, var(--surface-muted)); }
.copy-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.copy-icon { width: 1.1rem; height: 1.1rem; }
.upload {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  padding: var(--space-6) var(--space-4);
  border-radius: var(--radius-md);
  border: 2px dashed var(--border);
  background: var(--surface-muted);
  cursor: pointer;
  transition: border-color 0.15s ease, background-color 0.15s ease;
  min-height: 8rem;
  text-align: center;
}
.upload:hover {
  border-color: var(--accent);
}
.upload-input {
  position: absolute;
  width: 1px;
  height: 1px;
  opacity: 0;
  pointer-events: none;
}
.upload-icon {
  width: 2rem;
  height: 2rem;
  color: var(--text-muted);
}
.upload-label {
  font-weight: 600;
  color: var(--text-primary);
  font-size: 0.9rem;
}
.upload-hint {
  font-size: 0.75rem;
  color: var(--text-muted);
}
.upload-preview {
  max-height: 12rem;
  max-width: 100%;
  border-radius: var(--radius-md);
  object-fit: contain;
}
.submit {
  padding: 0.85rem 1rem;
  border-radius: var(--radius-pill);
  border: none;
  background: var(--btn-primary-bg);
  color: var(--btn-primary-fg);
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
  transition: transform 0.08s ease, opacity 0.15s ease;
}
.submit:hover { opacity: 0.9; }
.submit:active { transform: scale(0.98); }
.submit:disabled { opacity: 0.5; cursor: not-allowed; }
.submit-error {
  color: var(--danger-fg);
  font-size: 0.85rem;
  margin: 0;
}
.intro {
  font-size: 0.85rem;
  color: var(--text-secondary);
  margin: 0 0 var(--space-4);
  line-height: 1.5;
}
.bank-list {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}
.bank-row {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3);
  border-radius: var(--radius-md);
  background: var(--surface-muted);
}
.bank-meta { flex: 1; min-width: 0; }
.bank-label {
  font-size: 0.75rem;
  color: var(--text-secondary);
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.bank-value {
  font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace;
  font-size: 0.95rem;
  font-weight: 600;
  color: var(--text-primary);
  margin: 0.2rem 0 0;
  word-break: break-all;
}

/* Skeleton — .card, .form, .field and .copy-row are reused as-is. */
.sk-input { width: 100%; height: 2.95rem; border-radius: var(--radius-md); }
.copy-row .sk-input { flex: 1; }
.sk-copy { width: 2.75rem; border-radius: var(--radius-md); flex-shrink: 0; }
.sk-upload { width: 100%; height: 8rem; border-radius: var(--radius-md); }
.sk-submit { width: 100%; height: 3.13rem; border-radius: var(--radius-pill); }
</style>
