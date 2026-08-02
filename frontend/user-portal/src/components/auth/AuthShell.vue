<script setup>
import { BuildingLibraryIcon } from '@heroicons/vue/24/outline'
</script>

<template>
  <div class="auth-shell">
    <div class="auth-shell__brand">
      <div class="auth-shell__logo">
        <BuildingLibraryIcon class="auth-shell__logo-icon" aria-hidden="true" />
      </div>
      <h1 class="auth-shell__brand-name">Bank Pro</h1>
    </div>

    <div class="auth-shell__card">
      <slot />
    </div>

    <div class="auth-shell__footer">
      <slot name="footer" />
    </div>
  </div>
</template>
