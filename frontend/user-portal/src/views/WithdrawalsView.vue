<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import {
  ChevronLeftIcon, BanknotesIcon, BuildingLibraryIcon, HashtagIcon,
  UserIcon, CurrencyDollarIcon, WalletIcon, ClipboardDocumentIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/solid'
import client from '../api/client'
import ErrorState from '../components/ui/ErrorState.vue'
import LoadingRegion from '../components/skeletons/LoadingRegion.vue'
import SkeletonBalanceCard from '../components/skeletons/SkeletonBalanceCard.vue'
import SkeletonField from '../components/skeletons/SkeletonField.vue'
import { currencySymbol } from '../utils/currency'
import { formatMoneyInline } from '../utils/format'

const router = useRouter()
const toast = useToast()

const TABS = ['Bank', 'Crypto']
const activeTab = ref('Bank')

const loading = ref(true)
const error = ref('')
const bankMeta = ref({})
const cryptoMeta = ref({})

// Bank form
const bankAmount = ref('')
const bankName = ref('')
const bankAccountNumber = ref('')
const bankRoutingNo = ref('')
const submittingBank = ref(false)

// Crypto form
const cryptoAmount = ref('')
const cryptoId = ref('')
const cryptoWallet = ref('')
const submittingCrypto = ref(false)

const cryptoList = computed(() => Array.isArray(cryptoMeta.value?.crypto_currency) ? cryptoMeta.value.crypto_currency : [])
const selectedCrypto = computed(() => cryptoList.value.find((c) => String(c.id) === String(cryptoId.value)) || null)

function onCryptoChange() {
  // Prefill with the currency's canonical wallet address (the admin-managed
  // default). User can still overwrite for a different receiving wallet.
  cryptoWallet.value = selectedCrypto.value?.wallet_address || ''
}

async function loadMeta() {
  loading.value = true
  error.value = ''
  try {
    const [bank, crypto] = await Promise.all([
      client.get('/api/user/withdrawals-bank-meta.php'),
      client.get('/api/user/withdrawals-crypto-meta.php'),
    ])
    if (!bank.data?.ok) throw new Error(bank.data?.message || 'Unable to load bank options')
    if (!crypto.data?.ok) throw new Error(crypto.data?.message || 'Unable to load crypto options')
    bankMeta.value = bank.data.data || {}
    cryptoMeta.value = crypto.data.data || {}
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'Unable to load withdrawals'
  } finally {
    loading.value = false
  }
}
onMounted(loadMeta)

const currencyText = computed(() => bankMeta.value?.currency || currencySymbol('USD'))
const acctBalance = computed(() => Number(bankMeta.value?.acct_balance ?? cryptoMeta.value?.acct_balance ?? 0))
const balanceDisplay = computed(() => `${currencyText.value}${formatMoneyInline(acctBalance.value, { trimTrailingZero: true })}`)
const acctStatus = computed(() => String(bankMeta.value?.acct_status ?? cryptoMeta.value?.acct_status ?? ''))
const canWithdraw = computed(() => acctStatus.value === 'active')
const holderName = computed(() => bankMeta.value?.full_name || '')

async function submitBank() {
  const amt = Number(bankAmount.value) || 0
  if (amt <= 0) { toast.error('Enter a withdrawal amount.'); return }
  if (amt > acctBalance.value) { toast.error('Insufficient balance.'); return }
  if (!bankName.value.trim()) { toast.error('Bank name is required.'); return }
  if (!bankAccountNumber.value.trim()) { toast.error('Account number is required.'); return }
  if (!bankRoutingNo.value.trim()) { toast.error('Routing number is required.'); return }

  submittingBank.value = true
  try {
    const { data } = await client.post('/api/user/withdrawals-bank.php', {
      amount: amt,
      bankname: bankName.value.trim(),
      account_number: bankAccountNumber.value.trim(),
      routineno: bankRoutingNo.value.trim(),
    })
    if (!data?.ok) throw new Error(data?.message || 'Withdrawal failed')
    toast.success(data?.message || 'Bank withdrawal submitted.')
    bankAmount.value = ''; bankName.value = ''
    bankAccountNumber.value = ''; bankRoutingNo.value = ''
    router.push('/transactions')
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Withdrawal failed')
  } finally {
    submittingBank.value = false
  }
}

async function submitCrypto() {
  const amt = Number(cryptoAmount.value) || 0
  if (amt <= 0) { toast.error('Enter a withdrawal amount.'); return }
  if (amt > acctBalance.value) { toast.error('Insufficient balance.'); return }
  if (!cryptoId.value) { toast.error('Select a cryptocurrency.'); return }
  if (!cryptoWallet.value.trim()) { toast.error('Wallet address is required.'); return }

  submittingCrypto.value = true
  try {
    const withdrawMethod = selectedCrypto.value?.crypto_name || 'crypto'
    const { data } = await client.post('/api/user/withdrawals-crypto.php', {
      amount: amt,
      withdraw_method: withdrawMethod,
      wallet_address: cryptoWallet.value.trim(),
    })
    if (!data?.ok) throw new Error(data?.message || 'Withdrawal failed')
    toast.success(data?.message || 'Crypto withdrawal submitted.')
    cryptoAmount.value = ''; cryptoId.value = ''; cryptoWallet.value = ''
    router.push('/transactions')
  } catch (err) {
    toast.error(err?.response?.data?.message || err.message || 'Withdrawal failed')
  } finally {
    submittingCrypto.value = false
  }
}

async function copyWallet() {
  if (!cryptoWallet.value) return
  try {
    await navigator.clipboard.writeText(cryptoWallet.value)
    toast.success('Wallet copied', { timeout: 1500 })
  } catch {
    toast.error('Copy failed')
  }
}
</script>

<template>
  <div class="page">
    <div class="content">
      <header class="header">
        <button type="button" class="back" aria-label="Go back" @click="router.back()">
          <ChevronLeftIcon class="back-icon" aria-hidden="true" />
        </button>
        <div class="titles">
          <h1 class="title">Withdraw</h1>
          <p class="subtitle">Move money to your bank or wallet</p>
        </div>
      </header>

      <!-- Mirrors the Bank tab, which is what `activeTab` defaults to. The
           balance card here carries no remaining-limit line, hence :limit="false".
           Fields are hand-rolled (0.8rem label, 0.4rem gap) rather than
           <FormField>, so the caption metrics are passed explicitly. -->
      <LoadingRegion v-if="loading" label="withdrawal options">
        <SkeletonBalanceCard :limit="false" />

        <div class="tabs">
          <div v-for="tab in TABS" :key="tab" class="skeleton sk-tab" />
        </div>

        <section class="form-card">
          <div class="form">
            <SkeletonField label="4rem" label-size="0.8rem" gap="0.4rem" height="3.55rem" />
            <SkeletonField label="5.5rem" label-size="0.8rem" gap="0.4rem" />
            <SkeletonField label="7.5rem" label-size="0.8rem" gap="0.4rem" />
            <SkeletonField label="7rem" label-size="0.8rem" gap="0.4rem" />
            <div class="skeleton sk-hint" />
            <div class="skeleton sk-submit" />
          </div>
        </section>
      </LoadingRegion>

      <ErrorState v-else-if="error" :message="error" />

      <template v-else>
        <section class="balance-card">
          <div class="balance-copy">
            <p class="balance-label">Available balance</p>
            <p class="balance-amount">{{ balanceDisplay }}</p>
          </div>
          <div class="balance-icon">
            <BanknotesIcon aria-hidden="true" />
          </div>
        </section>

        <section v-if="!canWithdraw" class="restricted">
          <div class="restricted-icon">
            <ExclamationTriangleIcon aria-hidden="true" />
          </div>
          <h3 class="restricted-title">Withdrawals paused</h3>
          <p class="restricted-body">
            Your account can't withdraw right now.
            Contact support to lift the restriction.
          </p>
          <button type="button" class="restricted-btn" @click="router.push('/tickets')">
            Contact support
          </button>
        </section>

        <template v-else>
          <div class="tabs" role="tablist">
            <button
              v-for="tab in TABS"
              :key="tab"
              type="button"
              role="tab"
              :aria-selected="activeTab === tab"
              class="tab"
              :class="{ 'tab--active': activeTab === tab }"
              @click="activeTab = tab"
            >
              <BuildingLibraryIcon v-if="tab === 'Bank'" class="tab-icon" aria-hidden="true" />
              <WalletIcon v-else class="tab-icon" aria-hidden="true" />
              {{ tab }}
            </button>
          </div>

          <section v-if="activeTab === 'Bank'" class="form-card" aria-label="Bank withdrawal">
            <form @submit.prevent="submitBank" class="form">
              <div class="field">
                <label class="label" for="bw-amount">Amount</label>
                <div class="amount-input">
                  <span class="amount-currency">{{ currencyText }}</span>
                  <input id="bw-amount" v-model="bankAmount" type="number" step="0.01" inputmode="decimal" placeholder="0.00" class="input input--amount" :disabled="submittingBank" />
                </div>
              </div>

              <div class="field">
                <label class="label" for="bw-bank">Bank name</label>
                <div class="input-wrap">
                  <BuildingLibraryIcon class="input-icon" aria-hidden="true" />
                  <input id="bw-bank" v-model="bankName" type="text" placeholder="Your bank's name" class="input input--with-icon" :disabled="submittingBank" />
                </div>
              </div>

              <div class="field">
                <label class="label" for="bw-acct">Account number</label>
                <div class="input-wrap">
                  <HashtagIcon class="input-icon" aria-hidden="true" />
                  <input id="bw-acct" v-model="bankAccountNumber" type="text" inputmode="numeric" placeholder="Beneficiary account number" class="input input--with-icon input--mono" :disabled="submittingBank" />
                </div>
              </div>

              <div class="field">
                <label class="label" for="bw-routing">Routing / ABA</label>
                <div class="input-wrap">
                  <HashtagIcon class="input-icon" aria-hidden="true" />
                  <input id="bw-routing" v-model="bankRoutingNo" type="text" inputmode="numeric" placeholder="9-digit routing number" class="input input--with-icon input--mono" :disabled="submittingBank" />
                </div>
              </div>

              <div v-if="holderName" class="holder-hint">
                <UserIcon class="holder-icon" aria-hidden="true" />
                <span>Withdrawn to <strong>{{ holderName }}</strong> on file</span>
              </div>

              <button type="submit" class="submit" :disabled="submittingBank">
                {{ submittingBank ? 'Submitting…' : 'Submit bank withdrawal' }}
              </button>
            </form>
          </section>

          <section v-else class="form-card" aria-label="Crypto withdrawal">
            <form @submit.prevent="submitCrypto" class="form">
              <div class="field">
                <label class="label" for="cw-amount">Amount</label>
                <div class="amount-input">
                  <span class="amount-currency">{{ currencyText }}</span>
                  <input id="cw-amount" v-model="cryptoAmount" type="number" step="0.01" inputmode="decimal" placeholder="0.00" class="input input--amount" :disabled="submittingCrypto" />
                </div>
              </div>

              <div class="field">
                <label class="label" for="cw-crypto">Cryptocurrency</label>
                <div class="input-wrap">
                  <CurrencyDollarIcon class="input-icon" aria-hidden="true" />
                  <select id="cw-crypto" v-model="cryptoId" @change="onCryptoChange" class="input input--with-icon" :disabled="submittingCrypto || !cryptoList.length">
                    <option value="" disabled>Select a currency</option>
                    <option v-for="c in cryptoList" :key="c.id" :value="c.id">{{ c.crypto_name }}</option>
                  </select>
                </div>
              </div>

              <div class="field">
                <label class="label" for="cw-wallet">Wallet address</label>
                <div class="copy-row">
                  <div class="input-wrap">
                    <WalletIcon class="input-icon" aria-hidden="true" />
                    <input id="cw-wallet" v-model="cryptoWallet" type="text" placeholder="Paste destination wallet address" class="input input--with-icon input--mono" :disabled="submittingCrypto" />
                  </div>
                  <button type="button" class="copy-btn" :disabled="!cryptoWallet" aria-label="Copy wallet address" @click="copyWallet">
                    <ClipboardDocumentIcon class="copy-icon" aria-hidden="true" />
                  </button>
                </div>
              </div>

              <p class="note">
                Double-check the wallet address — crypto transfers are irreversible.
              </p>

              <button type="submit" class="submit" :disabled="submittingCrypto">
                {{ submittingCrypto ? 'Submitting…' : 'Submit crypto withdrawal' }}
              </button>
            </form>
          </section>
        </template>
      </template>
    </div>
  </div>
</template>

<style scoped>
.page { min-height: 100vh; background: var(--bg-gradient); padding: var(--space-5) var(--space-4) 7rem; }
.content { max-width: 30rem; margin: 0 auto; display: flex; flex-direction: column; gap: var(--space-4); }
.header { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) 0 var(--space-1); }
.back { width: 2.5rem; height: 2.5rem; border-radius: 0.7rem; border: 1px solid var(--border); background: transparent; color: var(--text-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.back:hover { background: color-mix(in srgb, var(--text-primary) 6%, transparent); }
.back:active { transform: scale(0.95); }
.back-icon { width: 1.15rem; height: 1.15rem; }
.title { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; line-height: 1.1; }
.subtitle { font-size: 0.8rem; color: var(--text-secondary); margin: 0.15rem 0 0; }

.balance-card { display: flex; align-items: center; justify-content: space-between; gap: var(--space-3); padding: var(--space-4); background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-card); }
.balance-label { font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.06em; margin: 0 0 0.25rem; }
.balance-amount { font-size: 1.6rem; font-weight: 800; color: var(--text-primary); margin: 0; letter-spacing: -0.01em; }
.balance-icon { width: 2.75rem; height: 2.75rem; border-radius: var(--radius-pill); background: var(--accent-tint); color: var(--accent-strong); display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; }
.balance-icon > svg { width: 1.35rem; height: 1.35rem; }

.restricted { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-6); box-shadow: var(--shadow-card); text-align: center; display: flex; flex-direction: column; align-items: center; gap: var(--space-3); }
.restricted-icon { width: 3rem; height: 3rem; border-radius: var(--radius-pill); background: var(--danger-bg); color: var(--danger-fg); display: inline-flex; align-items: center; justify-content: center; }
.restricted-icon > svg { width: 1.5rem; height: 1.5rem; }
.restricted-title { font-size: 1.05rem; font-weight: 700; color: var(--text-primary); margin: 0; }
.restricted-body { font-size: 0.9rem; color: var(--text-secondary); margin: 0; max-width: 20rem; line-height: 1.5; }
.restricted-btn { margin-top: var(--space-2); padding: 0.75rem 1.5rem; border-radius: var(--radius-pill); border: 1px solid var(--btn-outline-border); background: var(--surface); color: var(--text-primary); font-weight: 600; cursor: pointer; }

.tabs { display: flex; gap: var(--space-2); }
.tab { flex: 1; padding: 0.7rem 1rem; border-radius: var(--radius-pill); border: 1px solid var(--border); background: var(--surface); color: var(--text-secondary); font-size: 0.85rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; transition: background-color 0.15s ease, color 0.15s ease, border-color 0.15s ease; }
.tab-icon { width: 1rem; height: 1rem; }
.tab--active { background: var(--btn-primary-bg); color: var(--btn-primary-fg); border-color: var(--btn-primary-bg); }

.form-card { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-5) var(--space-4); box-shadow: var(--shadow-card); }
.form { display: flex; flex-direction: column; gap: var(--space-4); }
.field { display: flex; flex-direction: column; gap: 0.4rem; }
.label { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); }

.amount-input { display: flex; align-items: stretch; border-radius: var(--radius-md); background: var(--surface-muted); border: 1px solid transparent; overflow: hidden; transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease; }
.amount-input:focus-within { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent); }
.amount-currency { padding: 0.85rem 0.95rem; color: var(--text-secondary); font-weight: 700; background: transparent; border-right: 1px solid var(--border); display: inline-flex; align-items: center; }
.input--amount { border: none; background: transparent; flex: 1; padding-left: 0.85rem; font-size: 1.15rem; font-weight: 700; color: var(--text-primary); }

.input-wrap { position: relative; display: flex; align-items: center; }
.input { width: 100%; padding: 0.85rem 1rem; border-radius: var(--radius-md); border: 1px solid transparent; background: var(--surface-muted); color: var(--text-primary); font-size: 0.95rem; font-family: inherit; outline: none; transition: border-color 0.15s ease, background-color 0.15s ease, box-shadow 0.15s ease; }
.input:focus { border-color: var(--accent); background: var(--surface); box-shadow: 0 0 0 4px color-mix(in srgb, var(--accent) 15%, transparent); }
.input--mono { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; letter-spacing: 0.02em; font-size: 0.9rem; }
.input:disabled { opacity: 0.6; cursor: not-allowed; }
.input-icon { position: absolute; left: 0.95rem; top: 50%; transform: translateY(-50%); width: 1.25rem; height: 1.25rem; color: var(--text-primary); pointer-events: none; }
.input--with-icon { padding-left: 2.75rem; }

.copy-row { display: flex; gap: var(--space-2); align-items: stretch; }
.copy-row .input-wrap { flex: 1; }
.copy-btn { width: 2.75rem; border-radius: var(--radius-md); border: 1px solid var(--border); background: var(--surface-muted); color: var(--text-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; transition: background-color 0.15s ease; flex-shrink: 0; }
.copy-btn:hover { background: color-mix(in srgb, var(--accent) 10%, var(--surface-muted)); }
.copy-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.copy-icon { width: 1.1rem; height: 1.1rem; }

.holder-hint {
  display: flex; align-items: center; gap: 0.5rem;
  padding: 0.65rem 0.85rem;
  border-radius: var(--radius-md);
  background: var(--surface-muted);
  color: var(--text-secondary);
  font-size: 0.8rem;
}
.holder-hint strong { color: var(--text-primary); font-weight: 700; }
.holder-icon { width: 1rem; height: 1rem; color: var(--text-primary); }

.note {
  font-size: 0.8rem;
  color: var(--warning-fg);
  background: color-mix(in srgb, var(--warning-fg) 10%, transparent);
  padding: 0.65rem 0.85rem;
  border-radius: var(--radius-md);
  margin: 0;
  line-height: 1.45;
}

.submit { width: 100%; height: 3.1rem; padding: 0 var(--space-4); border-radius: var(--radius-pill); border: 1px solid transparent; background: var(--btn-primary-bg); color: var(--btn-primary-fg); font-weight: 700; font-size: 0.95rem; font-family: inherit; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; transition: transform 0.08s ease, opacity 0.15s ease; margin-top: var(--space-2); }
.submit:hover:not(:disabled) { opacity: 0.92; }
.submit:active:not(:disabled) { transform: scale(0.98); }
.submit:disabled { opacity: 0.5; cursor: not-allowed; }

/* Skeleton — .tabs, .form-card and .form are reused so the gaps and padding
   come from the same rules as the live form. */
.sk-tab { flex: 1; height: 2.8rem; border-radius: var(--radius-pill); }
.sk-hint { height: 2.5rem; border-radius: var(--radius-md); }
.sk-submit { height: 3.1rem; border-radius: var(--radius-pill); margin-top: var(--space-2); }
</style>
