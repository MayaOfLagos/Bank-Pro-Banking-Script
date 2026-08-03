import { ref, computed } from 'vue'
import client from '../api/client'

/**
 * Loads /api/user/deposits-meta.php (crypto list, bank details, limits) and
 * exposes a submit() for the crypto deposit form. Bank deposit is manual
 * (user copies the account details out); no submit needed for that path.
 */
export function useDeposit() {
  const loading = ref(false)
  const submitting = ref(false)
  const error = ref('')

  const currency = ref('$')
  const limitMin = ref(0)
  const limitMax = ref(0)
  const bankDepositEnabled = ref(false)
  const virtualBank = ref(null)
  const cryptoList = ref([])
  const acctStatus = ref('')

  const canSubmit = computed(() => acctStatus.value === 'active')

  async function loadMeta() {
    loading.value = true
    error.value = ''
    try {
      const { data } = await client.get('/api/user/deposits-meta.php')
      if (!data?.ok) throw new Error(data?.message || 'Unable to load deposit options')
      const d = data.data || {}
      currency.value = d.currency || '$'
      limitMin.value = Number(d.trans_limit_min) || 0
      limitMax.value = Number(d.trans_limit_max) || 0
      bankDepositEnabled.value = Boolean(d.bank_deposit_enabled)
      virtualBank.value = d.virtual_bank && Object.keys(d.virtual_bank).length ? d.virtual_bank : null
      cryptoList.value = Array.isArray(d.crypto_currency) ? d.crypto_currency : []
      acctStatus.value = String(d.acct_status || '')
    } catch (err) {
      error.value = err?.response?.data?.message || err.message || 'Unable to load deposit options'
    } finally {
      loading.value = false
    }
  }

  async function submit({ amount, cryptoId, walletAddress, image }) {
    submitting.value = true
    try {
      const form = new FormData()
      form.append('amount', String(amount))
      form.append('crypto_id', String(cryptoId))
      form.append('wallet_address', String(walletAddress))
      if (image) form.append('image', image)
      const { data } = await client.post('/api/user/deposits.php', form, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
      if (!data?.ok) throw new Error(data?.message || 'Deposit failed')
      return data
    } finally {
      submitting.value = false
    }
  }

  return {
    loading,
    submitting,
    error,
    currency,
    limitMin,
    limitMax,
    bankDepositEnabled,
    virtualBank,
    cryptoList,
    acctStatus,
    canSubmit,
    loadMeta,
    submit,
  }
}
