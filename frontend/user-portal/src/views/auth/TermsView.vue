<template>
  <AuthShell :show-trust="false">
    <h2 class="auth-heading">Terms of Service</h2>
    <p class="auth-subheading">Last updated by the operator via the admin panel.</p>

    <!-- Admin-authored copy. Save handler in admin/settings.php whitelists
         a fixed vocabulary of tags via strip_tags(), so v-html here is
         trust-scoped to the admin, not to arbitrary user input. -->
    <!-- Hold the placeholder until the store has *settled*, not until it has
         loaded: `loaded` never flips on a failed fetch, and without this gate
         the "not published yet" notice flashes on every visit before the copy
         arrives. -->
    <LoadingRegion v-if="!site.settled" label="the terms of service">
      <SkeletonLegalBody />
    </LoadingRegion>
    <div v-else-if="html" class="legal-body" v-html="html"></div>
    <div v-else class="legal-body legal-body--empty">
      <p>No Terms of Service have been published yet. Please check back soon.</p>
    </div>

    <template #footer>
      <p class="auth-footer-note">
        <RouterLink to="/register">Back to sign up</RouterLink>
        &nbsp;·&nbsp;
        <RouterLink to="/privacy">Privacy Policy</RouterLink>
      </p>
    </template>
  </AuthShell>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import AuthShell from '../../components/auth/AuthShell.vue'
import LoadingRegion from '../../components/skeletons/LoadingRegion.vue'
import SkeletonLegalBody from '../../components/skeletons/SkeletonLegalBody.vue'
import { useSiteStore } from '../../stores/site'

const site = useSiteStore()
const html = computed(() => site.termsOfServiceHtml || '')

onMounted(() => {
  // Guarantee we have the freshest admin copy when a visitor deep-links
  // straight to /terms without hitting /login first. load() is a no-op
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
