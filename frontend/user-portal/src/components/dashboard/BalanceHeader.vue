<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { BellIcon, SunIcon, MoonIcon } from '@heroicons/vue/24/solid'
import { merchantInitials } from '../../utils/format'
import { useTheme } from '../../composables/useTheme'
import NotificationDrawer from '../layout/NotificationDrawer.vue'
import { useNotificationsStore } from '../../stores/notifications'

const props = defineProps({
  firstName: { type: String, default: '' },
  subtitle: { type: String, default: 'Welcome back!' },
  avatarUrl: { type: String, default: '' },
  // Retained so existing call sites keep compiling; the real signal is the
  // store's live unread count, and either source lights the dot.
  hasUnread: { type: Boolean, default: false },
  profileHref: { type: String, default: '/profile' },
})

const initials = computed(() => merchantInitials(props.firstName || 'A'))
const { isDark, toggleTheme } = useTheme()

/**
 * The header owns the drawer rather than emitting upward. Its two call sites
 * (DashboardView, CardsView) are plain content screens that hold no layout
 * state, so lifting an `open` flag into each of them would duplicate the same
 * boilerplate twice and leave any future third screen with a dead bell. This
 * also mirrors FloatingActionBar, which renders TransferDrawer itself
 * for the same reason. The sheet teleports to <body>, so nesting it
 * here costs nothing in stacking terms.
 */
const notifications = useNotificationsStore()
const showDot = computed(() => props.hasUnread || notifications.hasUnread)

// Polling is reference-counted in the store; the bell is only ever mounted on
// authenticated screens, so subscribing here keeps auth pages request-free.
// subscribe() also triggers the store's own stale-check fetch, so there is
// deliberately no extra loadFeed() call here — that would double-request on
// every mount.
onMounted(() => notifications.subscribe())
onBeforeUnmount(() => notifications.unsubscribe())
</script>

<template>
  <header class="header">
    <RouterLink :to="profileHref" class="identity" :aria-label="`Open profile for ${firstName || 'you'}`">
      <div class="avatar">
        <img v-if="avatarUrl" :src="avatarUrl" :alt="firstName" />
        <span v-else>{{ initials }}</span>
      </div>
      <div class="greeting">
        <p class="hello">{{ firstName ? `Hi, ${firstName}` : 'Hi there' }}</p>
        <p class="subtitle">{{ subtitle }}</p>
      </div>
    </RouterLink>

    <div class="actions">
      <button
        type="button"
        class="icon-plain"
        aria-label="Notifications"
        aria-haspopup="dialog"
        :aria-expanded="notifications.drawerOpen"
        @click="notifications.openDrawer()"
      >
        <BellIcon class="icon" aria-hidden="true" />
        <span v-if="showDot" class="dot" aria-hidden="true"></span>
      </button>
      <button
        type="button"
        class="icon-bordered"
        :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
        :aria-pressed="isDark"
        @click="toggleTheme"
      >
        <SunIcon v-if="isDark" class="icon" aria-hidden="true" />
        <MoonIcon v-else class="icon" aria-hidden="true" />
      </button>
    </div>
  </header>

  <NotificationDrawer />
</template>

<style scoped>
.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-3);
  padding: var(--space-3) 0;
}
.identity {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  text-decoration: none;
  color: inherit;
  min-width: 0;
}
.avatar {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: var(--radius-pill);
  background: var(--text-primary);
  color: var(--surface);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  font-size: 0.95rem;
  overflow: hidden;
  flex-shrink: 0;
}
.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.greeting {
  min-width: 0;
}
.hello {
  font-weight: 700;
  font-size: 1rem;
  color: var(--text-primary);
  line-height: 1.15;
  margin: 0;
}
.subtitle {
  font-size: 0.8rem;
  color: var(--text-secondary);
  margin: 0.15rem 0 0;
}
.actions {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  flex-shrink: 0;
}
/* Both header actions are the same circular chip so the bell and the theme
   toggle read as a matched pair. */
.icon-plain,
.icon-bordered {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--border);
  background: transparent;
  color: var(--text-primary);
  text-decoration: none;
  cursor: pointer;
  transition: transform 0.1s ease, background-color 0.15s ease, color 0.15s ease;
}
.icon-plain:hover,
.icon-bordered:hover {
  background: color-mix(in srgb, var(--text-primary) 6%, transparent);
}
.icon-plain:active,
.icon-bordered:active {
  transform: scale(0.94);
}
.icon {
  width: 1.4rem;
  height: 1.4rem;
}
/* Sits on the circle's edge rather than the corner of its bounding box, which
   on a round chip would float in empty space. */
.dot {
  position: absolute;
  top: 0.3rem;
  right: 0.35rem;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-pill);
  background: var(--danger-fg);
}
</style>
