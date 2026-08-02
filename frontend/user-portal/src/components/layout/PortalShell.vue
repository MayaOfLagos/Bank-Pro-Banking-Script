<template>
  <div class="min-h-screen bg-[#080d18] text-white">

    <!-- Page content -->
    <main class="pb-24">
      <slot />
    </main>

    <!-- Transfer bottom sheet -->
    <Transition name="sheet">
      <div
        v-if="sheetOpen"
        class="fixed inset-0 z-50 flex items-end bg-black/60 backdrop-blur-sm"
        @click.self="sheetOpen = false"
      >
        <div class="sheet-panel w-full rounded-t-3xl bg-[#111827] p-6 pb-10">
          <!-- drag handle -->
          <div class="mx-auto mb-6 h-1 w-12 rounded-full bg-[#1e2d44]"></div>

          <h3 class="mb-5 text-lg font-semibold text-white">Move money</h3>

          <div class="space-y-3">
            <RouterLink
              v-for="action in transferSheetActions"
              :key="action.to"
              :to="action.to"
              class="flex items-center gap-4 rounded-2xl border border-[#1e2d44] bg-[#141f30] p-4 transition-colors hover:border-blue-500/40 hover:bg-[#1a2436] active:scale-[0.98]"
              @click="sheetOpen = false"
            >
              <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#1a2436]">
                <component :is="action.icon" class="h-5 w-5 text-blue-400" />
              </div>
              <div class="min-w-0">
                <p class="text-sm font-semibold text-white">{{ action.label }}</p>
                <p class="text-xs text-slate-400">{{ action.description }}</p>
              </div>
            </RouterLink>
          </div>
        </div>
      </div>
    </Transition>

    <!-- Bottom tab bar -->
    <nav class="fixed inset-x-0 bottom-0 z-40 bg-[#0c1220] border-t border-[#1e2d44]">
      <div class="flex items-center justify-around px-2 py-3 max-w-[480px] mx-auto">
        <template v-for="tab in bottomTabs" :key="tab.label">

          <!-- Centre action tab (Transfer) -->
          <button
            v-if="tab.isAction"
            type="button"
            class="flex flex-col items-center gap-1"
            @click="sheetOpen = true"
          >
            <div class="h-12 w-12 rounded-full bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-600/30">
              <component :is="tab.icon" class="h-5 w-5 text-white" />
            </div>
            <span class="text-[10px] text-slate-500">{{ tab.label }}</span>
          </button>

          <!-- Regular tab -->
          <RouterLink
            v-else
            :to="tab.to"
            class="flex flex-col items-center gap-1 px-3 py-1 transition-colors"
            :class="isActive(tab.to) ? 'text-blue-400' : 'text-slate-500'"
          >
            <component :is="tab.icon" class="h-6 w-6" />
            <span class="text-[10px]">{{ tab.label }}</span>
          </RouterLink>

        </template>
      </div>
    </nav>

  </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute, useRouter } from 'vue-router'
import { useToast } from 'vue-toastification'
import { authApi } from '../../api/auth'
import client from '../../api/client'
import { bottomTabs, transferSheetActions } from '../../data/portalNav'

const route  = useRoute()
const router = useRouter()
const toast  = useToast()

// ─── state ───────────────────────────────────────────────────────────────────
const sheetOpen = ref(false)
const profile   = ref({})

// ─── helpers ─────────────────────────────────────────────────────────────────
/**
 * Returns true when the tab's route is the current page (exact or nested).
 */
const isActive = (path) =>
  route.path === path || route.path.startsWith(`${path}/`)

// ─── profile ─────────────────────────────────────────────────────────────────
const loadProfile = async () => {
  try {
    const { data } = await client.get('/api/user/profile.php')
    profile.value = data?.data || {}
  } catch {
    // Non-critical — shell still renders without profile data
  }
}

// ─── logout ──────────────────────────────────────────────────────────────────
const logout = async () => {
  try {
    await authApi.logout()
    toast.success('Logged out successfully')
  } catch (err) {
    toast.error(err?.response?.data?.message || 'Unable to log out')
  } finally {
    await router.push('/login')
  }
}

// ─── route change — close sheet ───────────────────────────────────────────────
watch(
  () => route.fullPath,
  () => { sheetOpen.value = false }
)

onMounted(loadProfile)

// Expose logout so child pages can call $parent.logout() if needed
defineExpose({ logout })
</script>

<style scoped>
/* Sheet slides up from the bottom; overlay fades in */
.sheet-enter-active,
.sheet-leave-active {
  transition: opacity 0.25s ease;
}

.sheet-enter-active .sheet-panel,
.sheet-leave-active .sheet-panel {
  transition: transform 0.25s ease;
}

.sheet-enter-from,
.sheet-leave-to {
  opacity: 0;
}

.sheet-enter-from .sheet-panel,
.sheet-leave-to .sheet-panel {
  transform: translateY(100%);
}
</style>
