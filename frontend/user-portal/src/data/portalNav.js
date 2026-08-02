import {
  HomeIcon,
  CreditCardIcon,
  ArrowsRightLeftIcon,
  ClockIcon,
  UserCircleIcon,
  BuildingLibraryIcon,
  BanknotesIcon,
  ChatBubbleLeftRightIcon,
  WalletIcon
} from '@heroicons/vue/24/outline'

export const bottomTabs = [
  { label: 'Home',     to: '/dashboard',       icon: HomeIcon },
  { label: 'Cards',    to: '/cards',            icon: CreditCardIcon },
  { label: 'Transfer', to: null,                icon: ArrowsRightLeftIcon, isAction: true },
  { label: 'Activity', to: '/transactions',     icon: ClockIcon },
  { label: 'Profile',  to: '/profile',          icon: UserCircleIcon }
]

export const transferSheetActions = [
  {
    label: 'Wire Transfer',
    to: '/wire-transfer',
    icon: ArrowsRightLeftIcon,
    description: 'International bank transfer'
  },
  {
    label: 'Domestic Transfer',
    to: '/domestic-transfer',
    icon: BuildingLibraryIcon,
    description: 'Local bank transfer'
  },
  {
    label: 'Withdrawal',
    to: '/withdrawals',
    icon: BanknotesIcon,
    description: 'Withdraw funds'
  }
]

export const sidebarNav = [
  {
    section: 'Overview',
    items: [
      { label: 'Dashboard',    to: '/dashboard',      icon: HomeIcon },
      { label: 'Transactions', to: '/transactions',   icon: ClockIcon },
      { label: 'Profile',      to: '/profile', icon: UserCircleIcon }
    ]
  },
  {
    section: 'Banking',
    items: [
      { label: 'Wire Transfer',     to: '/wire-transfer',     icon: ArrowsRightLeftIcon },
      { label: 'Domestic Transfer', to: '/domestic-transfer', icon: BuildingLibraryIcon },
      { label: 'Withdrawals',       to: '/withdrawals',       icon: BanknotesIcon },
      { label: 'Cards',             to: '/cards',             icon: CreditCardIcon },
      { label: 'Loans',             to: '/loans',             icon: WalletIcon }
    ]
  },
  {
    section: 'Support',
    items: [
      { label: 'Support', to: '/tickets', icon: ChatBubbleLeftRightIcon }
    ]
  }
]

// Aliases kept for any legacy imports that reference these names
export const mobileNav = bottomTabs
export const transferActions = transferSheetActions
