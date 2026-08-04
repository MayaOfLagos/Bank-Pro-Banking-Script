import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import client from '../api/client'

// Mirrors /api/user/account-status.php. Login already refuses a non-active
// account, so this exists for the mid-session case: an admin puts the
// account on hold while the customer is sitting in the portal, and nothing
// would otherwise tell them until they walked into a feature.
export const useAccountStatusStore = defineStore('accountStatus', () => {
  const status = ref('')
  const hold = ref(false)
  const message = ref('')
  const supportEmail = ref('')
  const supportPhone = ref('')
  const loaded = ref(false)

  const supportMailto = computed(() => (supportEmail.value ? `mailto:${supportEmail.value}` : ''))
  const supportTel = computed(() => (supportPhone.value ? `tel:${supportPhone.value.replace(/[^\d+]/g, '')}` : ''))

  async function refresh() {
    try {
      const { data } = await client.get('/api/user/account-status.php')
      if (!data?.ok) return
      status.value = String(data.data?.acct_status || '')
      hold.value = Boolean(data.data?.hold)
      message.value = String(data.data?.message || '')
      supportEmail.value = String(data.data?.support_email || '')
      supportPhone.value = String(data.data?.support_phone || '')
      loaded.value = true
    } catch {
      // A failed status check must not blank an existing hold banner, so
      // leave the last known state alone.
    }
  }

  function reset() {
    status.value = ''
    hold.value = false
    message.value = ''
    supportEmail.value = ''
    supportPhone.value = ''
    loaded.value = false
  }

  return {
    status,
    hold,
    message,
    supportEmail,
    supportPhone,
    supportMailto,
    supportTel,
    loaded,
    refresh,
    reset,
  }
})
