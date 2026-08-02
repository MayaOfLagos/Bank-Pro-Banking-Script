<script setup>
import { BuildingLibraryIcon } from '@heroicons/vue/24/outline'
</script>

<template>
  <div class="shell">
    <div class="brand">
      <div class="logo">
        <BuildingLibraryIcon class="logo-icon" aria-hidden="true" />
      </div>
      <h1 class="brand-name">Bank Pro</h1>
    </div>

    <div class="card">
      <slot />
    </div>

    <div class="footer">
      <slot name="footer" />
    </div>
  </div>
</template>

<style scoped>
.shell {
  min-height: 100vh;
  background: var(--bg-gradient);
  color: var(--text-primary);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: var(--space-6) var(--space-5);
}
.brand {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--space-3);
  margin-bottom: var(--space-6);
}
.logo {
  width: 3.5rem;
  height: 3.5rem;
  border-radius: var(--radius-md);
  background: var(--accent);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}
.logo-icon {
  width: 2rem;
  height: 2rem;
  color: var(--text-on-accent);
}
.brand-name {
  color: var(--text-primary);
  font-weight: 700;
  font-size: 1.25rem;
  margin: 0;
}
.card {
  width: 100%;
  max-width: 24rem;
  background: var(--surface);
  border-radius: var(--radius-lg);
  padding: var(--space-6);
  border: 1px solid var(--border);
  box-shadow: var(--shadow-card);
}
.footer {
  margin-top: var(--space-4);
}
</style>
