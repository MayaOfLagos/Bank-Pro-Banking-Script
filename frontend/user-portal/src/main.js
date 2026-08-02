import { createApp } from 'vue'
import { createPinia } from 'pinia'
import Toast, { useToast } from 'vue-toastification'
import App from './App.vue'
import router from './router'
import { registerAuthHandlers, setCsrfToken } from './api/client'
import { useAuthStore } from './stores/auth'
import { useProfileStore } from './stores/profile'
import { useSettingsStore } from './stores/settings'
import 'vue-toastification/dist/index.css'
import './style.css'

// Force dark mode permanently — no theme toggle, no localStorage read
document.documentElement.classList.add('dark')

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

const bootstrap = async () => {
	await router.isReady()
	app.mount('#app')
}

bootstrap()
