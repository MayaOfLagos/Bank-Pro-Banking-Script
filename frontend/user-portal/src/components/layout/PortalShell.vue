<script setup>
import FloatingActionBar from '../dashboard/FloatingActionBar.vue'
</script>

<template>
  <div class="shell">
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
</style>
