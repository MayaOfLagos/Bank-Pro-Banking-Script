import { createApp } from 'vue'
import { createPinia } from 'pinia'
import Toast, { useToast } from 'vue-toastification'
import App from './App.vue'
import router from './router'
import { registerAuthHandlers, setCsrfToken } from './api/client'
import { useAuthStore } from './stores/auth'
import { useProfileStore } from './stores/profile'
import { useSettingsStore } from './stores/settings'
import { useAccountStatusStore } from './stores/accountStatus'
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

// Wire global 401/403/419/429/500 handling once the app is ready. The
// handlers live here rather than in api/client.js so they can lean on
// Pinia + the router + toast without pulling those into the axios module.
const toast = useToast()
const authStore = useAuthStore()
const profileStore = useProfileStore()
const settingsStore = useSettingsStore()
const accountStatusStore = useAccountStatusStore()
settingsStore.hydrateFromDocument()

registerAuthHandlers({
	unauthorized: (message) => {
		authStore.reset()
		profileStore.reset()
		accountStatusStore.reset()
		toast.error(message || 'Session expired. Sign in again.')
		if (router.currentRoute.value.path !== '/login') {
			router.push('/login')
		}
	},
	accountHold: () => {
		// The hold landed after this page loaded, so re-read the status to
		// raise the banner instead of leaving the customer with a lone
		// error on a screen that still looks fully operational.
		//
		// Deliberately silent: the 403 still rejects, and every caller
		// already surfaces the message itself — views toast it, composables
		// expose it as an inline error. Toasting here too showed it twice.
		accountStatusStore.refresh()
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
	// Kicked, not awaited: this used to run before the auth check in the
	// router guard, so boot cost two serial round trips with an empty
	// document on screen the whole time. Now the two overlap, and AuthShell
	// holds its brand block until this settles. Failure is non-fatal — the
	// store keeps sensible defaults.
	siteStore.load().catch(() => {})
	// Kept blocking: App.vue picks the shell off route.meta, so mounting
	// before the initial navigation resolves would wrap a login page in the
	// authenticated chrome for a frame. A guard rejection must not leave the
	// customer on the boot placeholder forever, hence the catch.
	await router.isReady().catch(() => {})
	app.mount('#app')
}

bootstrap()
