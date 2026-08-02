import { defineStore } from 'pinia'
import { ref } from 'vue'

// App-wide settings surfaced via /api/auth/status.php or a dedicated
// endpoint. Kept minimal until a real /api/user/settings.php lands.
export const useSettingsStore = defineStore('settings', () => {
  const appName = ref('')
  const initialized = ref(false)

  function hydrateFromDocument() {
    if (initialized.value) return
    const name = document.documentElement.dataset.appName || ''
    if (name) appName.value = name
    initialized.value = true
  }

  return {
    appName,
    initialized,
    hydrateFromDocument,
  }
})
