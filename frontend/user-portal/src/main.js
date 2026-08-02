import { createApp } from 'vue'
import { createPinia } from 'pinia'
import Toast from 'vue-toastification'
import App from './App.vue'
import router from './router'
import 'vue-toastification/dist/index.css'
import './style.css'

// Force dark mode permanently — no theme toggle, no localStorage read
document.documentElement.classList.add('dark')

const app = createApp(App)
app.use(createPinia())
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

const bootstrap = async () => {
	await router.isReady()
	app.mount('#app')
}

bootstrap()
