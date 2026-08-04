import { defineStore } from 'pinia'
import { ref } from 'vue'
import client, { setCsrfToken } from '../api/client'

/**
 * Public site branding + support contact. Loaded once at boot from
 * /api/site-config.php so pre-auth screens (login, PIN, reset) can show
 * the admin-configured brand name, logo, and support links.
 */
export const useSiteStore = defineStore('site', () => {
  const brandName = ref('Bank Pro')
  const tagline = ref('')
  const logoUrl = ref(null)
  const faviconUrl = ref('/assets/img/favicon.ico')
  const supportEmail = ref('')
  const supportPhone = ref('')
  const detectedCountryCode = ref('')
  const loaded = ref(false)
  const loading = ref(false)
  const error = ref('')

  async function load(force = false) {
    if (loaded.value && !force) return
    if (loading.value) return
    loading.value = true
    error.value = ''
    try {
      const { data } = await client.get('/api/site-config.php')
      if (!data?.ok) throw new Error(data?.message || 'Unable to load site config')
      const d = data.data || {}
      brandName.value = d.brand_name || brandName.value
      tagline.value = d.tagline || tagline.value
      logoUrl.value = d.logo_url || null
      faviconUrl.value = d.favicon_url || faviconUrl.value
      supportEmail.value = d.support_email || ''
      supportPhone.value = d.support_phone || ''
      detectedCountryCode.value = d.detected_country_code || ''
      if (d.csrf_token) setCsrfToken(d.csrf_token)
      applyFavicon(faviconUrl.value)
      applyDocumentTitle()
      loaded.value = true
    } catch (err) {
      error.value = err?.response?.data?.message || err.message || 'Unable to load site config'
    } finally {
      loading.value = false
    }
  }

  function applyFavicon(href) {
    if (typeof document === 'undefined' || !href) return
    let link = document.querySelector("link[rel~='icon']")
    if (!link) {
      link = document.createElement('link')
      link.rel = 'icon'
      document.head.appendChild(link)
    }
    link.href = href
  }

  function applyDocumentTitle() {
    if (typeof document === 'undefined') return
    // Preserve the current per-page title suffix if one was set. Prefix
    // becomes the fresh brand name.
    const current = document.title
    const dashIdx = current.indexOf(' - ')
    if (dashIdx !== -1) {
      document.title = `${brandName.value}${current.substring(dashIdx)}`
    } else if (current) {
      document.title = `${brandName.value} - ${current}`
    } else {
      document.title = brandName.value
    }
  }

  return {
    brandName,
    tagline,
    logoUrl,
    faviconUrl,
    supportEmail,
    supportPhone,
    detectedCountryCode,
    loaded,
    loading,
    error,
    load,
  }
})
