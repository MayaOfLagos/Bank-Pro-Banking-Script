<template>
  <div class="min-h-screen bg-[#080d18] flex flex-col items-center justify-center px-5 py-12">
    <!-- Logo / brand mark -->
    <div class="mb-8 flex flex-col items-center gap-3">
      <div class="h-14 w-14 rounded-2xl bg-blue-600 flex items-center justify-center">
        <BuildingLibraryIcon class="h-8 w-8 text-white" />
      </div>
      <h1 class="text-white font-bold text-xl">Bank Pro</h1>
    </div>

    <!-- Card -->
    <div class="w-full max-w-sm bg-[#111827] rounded-3xl p-6 border border-[#1e2d44]">
      <slot />
    </div>

    <!-- Footer slot -->
    <div class="mt-6">
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import { BuildingLibraryIcon } from '@heroicons/vue/24/outline'
</script>
