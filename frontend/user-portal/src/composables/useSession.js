import { useAuthStore } from '../stores/auth'
import { useProfileStore } from '../stores/profile'
import { useAccountStatusStore } from '../stores/accountStatus'
import { useNotificationsStore } from '../stores/notifications'

/**
 * Drop every scrap of the signed-in account from memory.
 *
 * This exists because the four ways out of a session had each grown their own
 * partial teardown: the 401 handler cleared three stores, the profile screen
 * and the More drawer cleared none (so `isAuthenticated` stayed true and the
 * notification poller kept firing until a 401 bounced the user), and the PIN
 * screen cleared two. Nothing anywhere called the notifications store's reset,
 * so signing out and back in as a different customer on the same tab showed
 * the previous account's unread badge and notification titles.
 *
 * Signing out is the one moment where "clear a bit less than everything" is
 * always a bug, so every exit must come through here rather than pick a
 * subset. Add new per-account stores to this list when they are introduced.
 */
export function clearSession() {
  useAuthStore().reset()
  useProfileStore().reset()
  useAccountStatusStore().reset()
  useNotificationsStore().reset()
}
