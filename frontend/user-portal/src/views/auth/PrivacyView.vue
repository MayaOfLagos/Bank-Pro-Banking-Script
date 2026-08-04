<template>
  <AuthShell :show-trust="false">
    <h2 class="auth-heading">Privacy Policy</h2>
    <p class="auth-subheading">Last updated by the operator via the admin panel.</p>

    <!-- Admin-authored copy. Save handler in admin/settings.php whitelists
         a fixed vocabulary of tags via strip_tags(), so v-html here is
         trust-scoped to the admin, not to arbitrary user input. -->
    <div v-if="html" class="legal-body" v-html="html"></div>
    <div v-else class="legal-body legal-body--empty">
      <p>No Privacy Policy has been published yet. Please check back soon.</p>
    </div>

    <template #footer>
      <p class="auth-footer-note">
        <RouterLink to="/register">Back to sign up</RouterLink>
        &nbsp;·&nbsp;
        <RouterLink to="/terms">Terms of Service</RouterLink>
      </p>
    </template>
  </AuthShell>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import AuthShell from '../../components/auth/AuthShell.vue'
import { useSiteStore } from '../../stores/site'

const site = useSiteStore()
const html = computed(() => site.privacyPolicyHtml || '')

onMounted(() => {
  // Guarantee we have the freshest admin copy when a visitor deep-links
  // straight to /privacy without hitting /login first. load() is a no-op
  // when the store is already hydrated.
  site.load().catch(() => {})
})
</script>

<style scoped>
.legal-body {
  max-height: 60vh;
  overflow-y: auto;
  padding-right: 0.25rem;
  color: var(--text-primary);
  font-size: 0.95rem;
  line-height: 1.55;
}
.legal-body :deep(h2) {
  font-size: 1.1rem;
  margin: 1.25rem 0 0.5rem;
}
.legal-body :deep(h3) {
  font-size: 1rem;
  margin: 1rem 0 0.4rem;
}
.legal-body :deep(p) { margin: 0 0 0.75rem; }
.legal-body :deep(ul),
.legal-body :deep(ol) { margin: 0 0 0.75rem 1.25rem; }
.legal-body :deep(a) { color: var(--accent); text-decoration: underline; }
.legal-body--empty {
  color: var(--text-muted);
  font-style: italic;
}
</style>
