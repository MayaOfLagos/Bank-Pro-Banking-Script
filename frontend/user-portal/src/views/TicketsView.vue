<script setup>
import { computed, onMounted, ref } from 'vue'
import client from '../api/client'

const loading = ref(true)
const error = ref('')
const manager = ref({})
const support = ref({})

const supportEmail = computed(() => manager.value.mgr_email || support.value.support_email || '')
const supportPhone = computed(() => manager.value.mgr_no || support.value.support_phone || '')
const managerImage = computed(() => manager.value.mgr_image ? `/assets/profile/${manager.value.mgr_image}` : '')
const managerInitials = computed(() => {
  return String(manager.value.mgr_name || 'Support')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part.charAt(0).toUpperCase())
    .join('')
})

function formatLimit(value) {
  return Number(value || 0).toLocaleString(undefined, {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

async function loadSupport() {
  loading.value = true
  error.value = ''
  try {
    const [managerResponse, statusResponse] = await Promise.all([
      client.get('/api/user/account-manager.php'),
      client.get('/api/user/account-status.php')
    ])
    manager.value = managerResponse.data?.data || {}
    support.value = statusResponse.data?.data || {}
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'Unable to load support details'
  } finally {
    loading.value = false
  }
}

onMounted(loadSupport)
</script>

<template>
  <div class="min-h-screen bg-[#080d18] pb-28">
    <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-6">
      <p class="text-slate-400 text-sm">Help when you need it</p>
      <h1 class="text-2xl font-bold text-white mt-1">Support</h1>
    </div>

    <template v-if="loading">
      <div class="mx-4 mt-4 h-52 rounded-2xl bg-[#111827] animate-pulse"></div>
      <div class="grid grid-cols-2 gap-3 mx-4 mt-4">
        <div class="h-24 rounded-2xl bg-[#111827] animate-pulse"></div>
        <div class="h-24 rounded-2xl bg-[#111827] animate-pulse"></div>
      </div>
    </template>

    <div v-else-if="error" class="mx-4 mt-4 bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-2xl px-5 py-4">
      {{ error }}
    </div>

    <template v-else>
      <section class="mx-4 mt-4 rounded-2xl border border-[#1e2d44] bg-[#111827] p-5">
        <div class="flex items-center gap-4">
          <div class="h-16 w-16 overflow-hidden rounded-2xl bg-blue-600 flex items-center justify-center text-white font-bold text-lg">
            <img v-if="managerImage" :src="managerImage" alt="Account manager" class="h-full w-full object-cover" />
            <span v-else>{{ managerInitials }}</span>
          </div>
          <div class="min-w-0">
            <p class="text-xs uppercase tracking-wider text-blue-400">Your account manager</p>
            <h2 class="mt-1 truncate text-lg font-semibold text-white">{{ manager.mgr_name || 'Customer Support' }}</h2>
            <p class="text-sm text-slate-400">Available for account and transaction assistance</p>
          </div>
        </div>
      </section>

      <div class="grid grid-cols-2 gap-3 mx-4 mt-4">
        <a
          v-if="supportEmail"
          :href="`mailto:${supportEmail}`"
          class="rounded-2xl border border-[#1e2d44] bg-[#111827] p-4 transition hover:border-blue-500/60"
        >
          <p class="text-xs text-slate-500">Email</p>
          <p class="mt-2 truncate text-sm font-medium text-blue-400">{{ supportEmail }}</p>
        </a>
        <a
          v-if="supportPhone"
          :href="`tel:${supportPhone}`"
          class="rounded-2xl border border-[#1e2d44] bg-[#111827] p-4 transition hover:border-blue-500/60"
        >
          <p class="text-xs text-slate-500">Phone</p>
          <p class="mt-2 truncate text-sm font-medium text-blue-400">{{ supportPhone }}</p>
        </a>
      </div>

      <section class="mx-4 mt-4 rounded-2xl bg-[#111827] divide-y divide-[#1e2d44]">
        <div class="flex items-center justify-between px-4 py-3.5">
          <span class="text-sm text-slate-400">Account type</span>
          <span class="text-sm font-medium text-white">{{ manager.acct_type || '—' }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3.5">
          <span class="text-sm text-slate-400">Account limit</span>
          <span class="text-sm font-medium text-white">{{ manager.currency }}{{ formatLimit(manager.acct_limit) }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3.5">
          <span class="text-sm text-slate-400">Account status</span>
          <span class="text-sm font-medium capitalize" :class="support.hold ? 'text-amber-400' : 'text-emerald-400'">
            {{ support.acct_status || 'Unknown' }}
          </span>
        </div>
      </section>

      <p class="mx-8 mt-5 text-center text-xs leading-relaxed text-slate-500">
        Never share your password, PIN, verification codes, or full card details with anyone, including support staff.
      </p>
    </template>
  </div>
</template>
