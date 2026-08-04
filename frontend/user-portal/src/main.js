import { createApp } from 'vue'
import { createPinia } from 'pinia'
import Toast, { useToast } from 'vue-toastification'
import App from './App.vue'
import router from './router'
import { registerAuthHandlers, setCsrfToken } from './api/client'
import { useAuthStore } from './stores/auth'
import { useProfileStore } from './stores/profile'
import { useSettingsStore } from './stores/settings'
import { useSiteStore } from './stores/site'
import { applyThemeAtBoot } from './composables/useTheme'
import 'vue-toastification/dist/index.css'
import './style.css'

// Resolve stored override or prefers-color-scheme before Vue mounts to
// avoid a light→dark flash.
applyThemeAtBoot()

const app = createApp(App)
const pinia = createPinia()
app.use(pinia)
app.use(router)
app.use(Toast, {
	timeout: 3000,
	closeOnClick: true,
	pauseOnFocusLoss: true,
	pauseOnHover: true,
	hideProgressBar: false,
	maxToasts: 5,
	newestOnTop: true
})

// Wire global 401/419/429/500 handling once the app is ready. The handlers
// live here rather than in api/client.js so they can lean on Pinia + the
// router + toast without pulling those into the axios module.
const toast = useToast()
const authStore = useAuthStore()
const profileStore = useProfileStore()
const settingsStore = useSettingsStore()
settingsStore.hydrateFromDocument()

registerAuthHandlers({
	unauthorized: (message) => {
		authStore.reset()
		profileStore.reset()
		toast.error(message || 'Session expired. Sign in again.')
		if (router.currentRoute.value.path !== '/login') {
			router.push('/login')
		}
	},
	securityTokenExpired: () => {
		toast.error('Security token expired. Refresh the page and try again.')
	},
	rateLimited: (message) => {
		toast.error(message || 'Too many attempts. Try again later.')
	},
	serverError: () => {
		toast.error('Something went wrong on our end. Try again in a moment.')
	},
})

// Keep the axios csrf token synced with the auth store so mutations always
// carry the current value.
authStore.$subscribe((_, state) => {
	if (state.csrfToken) setCsrfToken(state.csrfToken)
})

const siteStore = useSiteStore()

const bootstrap = async () => {
	// Fetch admin-configured branding before mount so the first paint of
	// pre-auth screens already shows the correct logo / brand name.
	// Failure is non-fatal — the store keeps sensible defaults.
	await siteStore.load().catch(() => {})
	await router.isReady()
	app.mount('#app')
}

bootstrap()
