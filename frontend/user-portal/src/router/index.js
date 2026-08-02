import { createRouter, createWebHistory } from 'vue-router'
import DashboardView from '../views/DashboardView.vue'
import WireTransferView from '../views/WireTransferView.vue'
import TransactionsView from '../views/TransactionsView.vue'
import DomesticTransferView from '../views/DomesticTransferView.vue'
import WithdrawalsView from '../views/WithdrawalsView.vue'
import TicketsView from '../views/TicketsView.vue'
import ProfileView from '../views/ProfileView.vue'
import CardsView from '../views/CardsView.vue'
import LoansView from '../views/LoansView.vue'
import LoginView from '../views/auth/LoginView.vue'
import PinView from '../views/auth/PinView.vue'
import ForgotPasswordView from '../views/auth/ForgotPasswordView.vue'
import UpdatePasswordView from '../views/auth/UpdatePasswordView.vue'
import TransferVerifyView from '../views/TransferVerifyView.vue'
import TransferCotView from '../views/TransferCotView.vue'
import TransferTaxView from '../views/TransferTaxView.vue'
import TransferImfView from '../views/TransferImfView.vue'
import TransferSuccessView from '../views/TransferSuccessView.vue'
import { authApi } from '../api/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/dashboard' },
    { path: '/login', alias: '/login.php', name: 'login', component: LoginView, meta: { authPage: true, guestOnly: true } },
    { path: '/pin', alias: '/pin.php', name: 'pin', component: PinView, meta: { authPage: true, requiresPendingPin: true } },
    { path: '/reset-password', alias: '/reset-password.php', name: 'reset-password', component: ForgotPasswordView, meta: { authPage: true, guestOnly: true } },
    { path: '/update-password', alias: '/updatePassword.php', name: 'update-password', component: UpdatePasswordView, meta: { authPage: true, guestOnly: true } },
    { path: '/dashboard', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true, title: 'Dashboard' } },
    { path: '/transactions', name: 'transactions', component: TransactionsView, meta: { requiresAuth: true, title: 'Transactions' } },
    { path: '/wire-transfer', name: 'wire-transfer', component: WireTransferView, meta: { requiresAuth: true, title: 'Wire Transfer' } },
    { path: '/domestic-transfer', name: 'domestic-transfer', component: DomesticTransferView, meta: { requiresAuth: true, title: 'Domestic Transfer' } },
    { path: '/withdrawals', name: 'withdrawals', component: WithdrawalsView, meta: { requiresAuth: true, title: 'Withdrawals' } },
    { path: '/cards', name: 'cards', component: CardsView, meta: { requiresAuth: true, title: 'Cards' } },
    { path: '/loans', name: 'loans', component: LoansView, meta: { requiresAuth: true, title: 'Loans' } },
    { path: '/tickets', name: 'tickets', component: TicketsView, meta: { requiresAuth: true, title: 'Tickets' } },
    { path: '/profile-center', name: 'profile', component: ProfileView, meta: { requiresAuth: true, title: 'Profile' } },
    { path: '/transfer-verify', name: 'transfer-verify', component: TransferVerifyView, meta: { requiresAuth: true, title: 'Verify Transfer OTP' } },
    { path: '/transfer-cot', name: 'transfer-cot', component: TransferCotView, meta: { requiresAuth: true, title: 'COT Verification' } },
    { path: '/transfer-tax', name: 'transfer-tax', component: TransferTaxView, meta: { requiresAuth: true, title: 'TAX Verification' } },
    { path: '/transfer-imf', name: 'transfer-imf', component: TransferImfView, meta: { requiresAuth: true, title: 'IMF Verification' } },
    { path: '/transfer-success', name: 'transfer-success', component: TransferSuccessView, meta: { requiresAuth: true, title: 'Transfer Complete' } }
  ]
})

router.beforeEach(async (to) => {
  let state = 'guest'
  try {
    const { data } = await authApi.status()
    state = data?.data?.state || 'guest'
  } catch {
    state = 'guest'
  }

  if (to.meta.requiresAuth && state !== 'authenticated') {
    return state === 'pending_pin' ? '/pin' : '/login'
  }

  if (to.meta.requiresPendingPin && state !== 'pending_pin') {
    return state === 'authenticated' ? '/dashboard' : '/login'
  }

  if (to.meta.guestOnly && state === 'authenticated') {
    return '/dashboard'
  }

  if (to.meta.guestOnly && state === 'pending_pin') {
    return '/pin'
  }

  return true
})

export default router
