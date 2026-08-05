<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import { BellIcon, SunIcon, MoonIcon } from '@heroicons/vue/24/solid'
import { merchantInitials } from '../../utils/format'
import { useTheme } from '../../composables/useTheme'

const props = defineProps({
  firstName: { type: String, default: '' },
  subtitle: { type: String, default: 'Welcome back!' },
  avatarUrl: { type: String, default: '' },
  hasUnread: { type: Boolean, default: false },
  profileHref: { type: String, default: '/profile' },
  notificationsHref: { type: String, default: '/tickets' },
})

const initials = computed(() => merchantInitials(props.firstName || 'A'))
const { isDark, toggleTheme } = useTheme()
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
      <RouterLink :to="notificationsHref" class="icon-plain" aria-label="Notifications">
        <BellIcon class="icon" aria-hidden="true" />
        <span v-if="hasUnread" class="dot" aria-hidden="true"></span>
      </RouterLink>
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
.icon-plain {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 0.4rem;
  color: var(--text-primary);
  text-decoration: none;
  cursor: pointer;
  transition: transform 0.1s ease, color 0.15s ease;
}
.icon-plain:hover {
  color: var(--accent-strong);
}
.icon-plain:active {
  transform: scale(0.94);
}
.icon-bordered {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 0.7rem;
  border: 1px solid var(--border);
  background: transparent;
  color: var(--text-primary);
  cursor: pointer;
  transition: transform 0.1s ease, background-color 0.15s ease;
}
.icon-bordered:hover {
  background: color-mix(in srgb, var(--text-primary) 6%, transparent);
}
.icon-bordered:active {
  transform: scale(0.94);
}
.icon {
  width: 1.4rem;
  height: 1.4rem;
}
.dot {
  position: absolute;
  top: 0.15rem;
  right: 0.2rem;
  width: 0.5rem;
  height: 0.5rem;
  border-radius: var(--radius-pill);
  background: var(--danger-fg);
  border: 2px solid transparent;
}
</style>
