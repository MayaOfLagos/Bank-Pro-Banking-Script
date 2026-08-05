<script setup>
import { computed } from 'vue'
import { BuildingLibraryIcon, ShieldCheckIcon } from '@heroicons/vue/24/solid'
import { useSiteStore } from '../../stores/site'

defineProps({
  showBrand: { type: Boolean, default: true },
  showTrust: { type: Boolean, default: true },
})

const site = useSiteStore()

const brandName = computed(() => site.brandName || 'Bank Pro')
const logoUrl = computed(() => site.logoUrl)
// Site config no longer blocks the mount, so the logo can arrive a beat
// after this renders. Hold the slot (its height is reserved in CSS) rather
// than show the fallback tile and swap it out. `settled`, not `loaded`, so
// a failed config request still falls through to the fallback.
const brandReady = computed(() => site.settled)
</script>

<template>
  <div class="auth-shell">
    <div class="auth-shell__bg" aria-hidden="true"></div>

    <div class="auth-shell__frame">
      <div v-if="showBrand" class="auth-shell__brand">
        <!-- When an admin logo is configured, let it breathe: no tile, no
             border, no background. Icon fallback keeps the tile only so
             something identifiable still shows for accounts without a
             logo uploaded yet. -->
        <template v-if="brandReady">
          <img
            v-if="logoUrl"
            :src="logoUrl"
            :alt="`${brandName} logo`"
            class="auth-shell__logo-free"
          />
          <div v-else class="auth-shell__logo">
            <BuildingLibraryIcon class="auth-shell__logo-icon" aria-hidden="true" />
          </div>
        </template>
      </div>

      <div class="auth-shell__card">
        <slot />
      </div>

      <div class="auth-shell__footer">
        <slot name="footer" />
      </div>

      <p v-if="showTrust" class="auth-shell__trust">
        <ShieldCheckIcon class="auth-shell__trust-icon" aria-hidden="true" />
        Secured with 256-bit SSL encryption
      </p>
    </div>
  </div>
</template>
