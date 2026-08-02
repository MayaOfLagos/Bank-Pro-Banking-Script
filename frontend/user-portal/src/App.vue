<template>
  <router-view v-if="route.meta.authPage" />

  <PortalShell v-else>
      <section>
        <router-view />
      </section>
  </PortalShell>
</template>

<script setup>
import { useRoute } from 'vue-router'
import PortalShell from './components/layout/PortalShell.vue'

const route = useRoute()
</script>
