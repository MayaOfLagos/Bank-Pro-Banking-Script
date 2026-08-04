import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useSiteStore } from '../stores/site'

const DashboardView = () => import('../views/DashboardView.vue')
const WireTransferView = () => import('../views/WireTransferView.vue')
const TransactionsView = () => import('../views/TransactionsView.vue')
const TransactionDetailView = () => import('../views/TransactionDetailView.vue')
const DepositView = () => import('../views/DepositView.vue')
const DomesticTransferView = () => import('../views/DomesticTransferView.vue')
const WithdrawalsView = () => import('../views/WithdrawalsView.vue')
const TicketsView = () => import('../views/TicketsView.vue')
const ProfileView = () => import('../views/ProfileView.vue')
const ProfileEditView = () => import('../views/ProfileEditView.vue')
const SecurityView = () => import('../views/SecurityView.vue')
const CardsView = () => import('../views/CardsView.vue')
const LoansView = () => import('../views/LoansView.vue')
const LoginView = () => import('../views/auth/LoginView.vue')
const RegisterView = () => import('../views/auth/RegisterView.vue')
const PinView = () => import('../views/auth/PinView.vue')
const ForgotPasswordView = () => import('../views/auth/ForgotPasswordView.vue')
const UpdatePasswordView = () => import('../views/auth/UpdatePasswordView.vue')
const TransferVerifyView = () => import('../views/TransferVerifyView.vue')
const TransferCotView = () => import('../views/TransferCotView.vue')
const TransferTaxView = () => import('../views/TransferTaxView.vue')
const TransferImfView = () => import('../views/TransferImfView.vue')
const TransferSuccessView = () => import('../views/TransferSuccessView.vue')

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/dashboard' },
    { path: '/login', name: 'login', component: LoginView, meta: { authPage: true, guestOnly: true } },
    { path: '/register', name: 'register', component: RegisterView, meta: { authPage: true, guestOnly: true, title: 'Create Account' } },
    { path: '/pin', name: 'pin', component: PinView, meta: { authPage: true, requiresPendingPin: true } },
    { path: '/reset-password', name: 'reset-password', component: ForgotPasswordView, meta: { authPage: true, guestOnly: true } },
    { path: '/update-password', name: 'update-password', component: UpdatePasswordView, meta: { authPage: true, guestOnly: true } },
    { path: '/dashboard', name: 'dashboard', component: DashboardView, meta: { requiresAuth: true, title: 'Dashboard' } },
    { path: '/transactions', name: 'transactions', component: TransactionsView, meta: { requiresAuth: true, title: 'Transactions' } },
    { path: '/transactions/:id(\\d+)', name: 'transaction-detail', component: TransactionDetailView, meta: { requiresAuth: true, title: 'Transaction' } },
    { path: '/deposits', name: 'deposits', component: DepositView, meta: { requiresAuth: true, title: 'Deposit' } },
    { path: '/wire-transfer', name: 'wire-transfer', component: WireTransferView, meta: { requiresAuth: true, title: 'Wire Transfer' } },
    { path: '/domestic-transfer', name: 'domestic-transfer', component: DomesticTransferView, meta: { requiresAuth: true, title: 'Domestic Transfer' } },
    { path: '/withdrawals', name: 'withdrawals', component: WithdrawalsView, meta: { requiresAuth: true, title: 'Withdrawals' } },
    { path: '/cards', name: 'cards', component: CardsView, meta: { requiresAuth: true, title: 'Cards' } },
    { path: '/loans', name: 'loans', component: LoansView, meta: { requiresAuth: true, title: 'Loans' } },
    { path: '/tickets', name: 'tickets', component: TicketsView, meta: { requiresAuth: true, title: 'Support' } },
    { path: '/profile', name: 'profile', component: ProfileView, meta: { requiresAuth: true, title: 'Profile' } },
    { path: '/profile/edit', name: 'profile-edit', component: ProfileEditView, meta: { requiresAuth: true, title: 'Edit Personal Details' } },
    { path: '/profile/security', name: 'profile-security', component: SecurityView, meta: { requiresAuth: true, title: 'Security' } },
    { path: '/transfer-verify', name: 'transfer-verify', component: TransferVerifyView, meta: { requiresAuth: true, title: 'Verify Transfer OTP' } },
    { path: '/transfer-cot', name: 'transfer-cot', component: TransferCotView, meta: { requiresAuth: true, title: 'COT Verification' } },
    { path: '/transfer-tax', name: 'transfer-tax', component: TransferTaxView, meta: { requiresAuth: true, title: 'TAX Verification' } },
    { path: '/transfer-imf', name: 'transfer-imf', component: TransferImfView, meta: { requiresAuth: true, title: 'IMF Verification' } },
    { path: '/transfer-success', name: 'transfer-success', component: TransferSuccessView, meta: { requiresAuth: true, title: 'Transfer Complete' } },
    { path: '/:pathMatch(.*)*', redirect: '/dashboard' }
  ]
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()
  const site = useSiteStore()

  // Admin killed registration — bounce /register visitors to /login.
  // Force a live re-fetch of site-config so an admin flip is reflected
  // on the very next navigation (the store's boot-time value would
  // otherwise be stale until a full reload).
  if (to.name === 'register') {
    await site.load(true).catch(() => {})
    if (!site.registrationEnabled) {
      return '/login'
    }
  }

  // First navigation refreshes from the server; subsequent ones trust cached
  // state. The 401 interceptor resets the store, so a stale-cached
  // "authenticated" flag gets corrected on the next API call.
  if (!auth.initialized) {
    await auth.refresh()
  }

  const state = auth.state

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

router.afterEach((to) => {
  const appName = document.documentElement.dataset.appName || 'BankPro'
  document.title = `${appName} - ${to.meta.title || 'Customer Portal'}`
})

export default router
