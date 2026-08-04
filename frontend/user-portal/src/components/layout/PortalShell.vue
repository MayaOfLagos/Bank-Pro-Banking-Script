<script setup>
import { onMounted } from 'vue'
import { ExclamationTriangleIcon } from '@heroicons/vue/24/solid'
import FloatingActionBar from '../dashboard/FloatingActionBar.vue'
import { useAccountStatusStore } from '../../stores/accountStatus'

const accountStatus = useAccountStatusStore()

// This shell unmounts whenever the app drops to an auth page, so mounting
// marks entry into an authenticated session. Clearing first means a signed-
// out account's banner can never bleed into the next sign-in on this tab —
// the logout paths don't all reset stores.
onMounted(() => {
  accountStatus.reset()
  accountStatus.refresh()
})
</script>

<template>
  <div class="shell">
    <!-- Not dismissible: legacy user/error.php blocked the page outright, so
         a banner the customer can wave away would be weaker than parity. -->
    <div v-if="accountStatus.hold" class="hold-bar no-print" role="alert">
      <ExclamationTriangleIcon class="hold-icon" aria-hidden="true" />
      <div class="hold-copy">
        <p class="hold-title">Account on hold</p>
        <p class="hold-body">{{ accountStatus.message || 'Contact support to restore access.' }}</p>
        <div class="hold-actions">
          <a v-if="accountStatus.supportMailto" class="hold-link" :href="accountStatus.supportMailto">
            {{ accountStatus.supportEmail }}
          </a>
          <a v-if="accountStatus.supportTel" class="hold-link" :href="accountStatus.supportTel">
            {{ accountStatus.supportPhone }}
          </a>
        </div>
      </div>
    </div>

    <main class="content">
      <slot />
    </main>
    <FloatingActionBar />
  </div>
</template>

<style scoped>
.shell {
  min-height: 100vh;
  background: var(--bg-gradient);
  color: var(--text-primary);
  position: relative;
}
.content {
  min-height: 100vh;
}
.hold-bar {
  display: flex;
  gap: var(--space-3);
  align-items: flex-start;
  padding: var(--space-4);
  margin: var(--space-4) var(--space-4) 0;
  background: var(--danger-bg);
  border: 1px solid var(--danger-border);
  border-radius: var(--radius-lg);
  color: var(--danger-fg);
}
.hold-icon {
  width: 1.4rem;
  height: 1.4rem;
  flex-shrink: 0;
  margin-top: 0.1rem;
}
.hold-copy { min-width: 0; }
.hold-title {
  font-weight: 700;
  margin: 0;
  font-size: 0.9rem;
}
.hold-body {
  font-size: 0.8rem;
  margin: 0.2rem 0 0;
  color: color-mix(in srgb, var(--danger-fg) 80%, transparent);
}
.hold-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-3);
  margin-top: var(--space-2);
}
.hold-link {
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--danger-fg);
  text-decoration: underline;
  word-break: break-all;
}
</style>
