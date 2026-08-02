<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import client from '../api/client'
import { authApi } from '../api/auth'

const router = useRouter()

const loading = ref(true)
const error = ref('')

const profile = ref({
  full_name: '',
  firstname: '',
  lastname: '',
  email: '',
  phone: '',
  acct_no: '',
  acct_type: '',
  acct_status: '',
  acct_currency: '',
  image: ''
})

// Edit form state
const editFirst = ref('')
const editLast = ref('')
const editPhone = ref('')
const saving = ref(false)
const saveError = ref('')
const saveSuccess = ref(false)

// Logout state
const loggingOut = ref(false)

const avatarSrc = computed(() => {
  if (profile.value.image) return `/assets/profile/${profile.value.image}`
  return null
})

const initials = computed(() => {
  const f = String(profile.value.firstname || '').charAt(0).toUpperCase()
  const l = String(profile.value.lastname || '').charAt(0).toUpperCase()
  return f + l || '?'
})

const statusBadgeClass = computed(() => {
  const s = String(profile.value.acct_status ?? '').toLowerCase()
  if (s === 'active') return 'bg-emerald-500/20 text-emerald-400'
  if (s === 'suspended' || s === 'blocked') return 'bg-red-500/20 text-red-400'
  return 'bg-slate-500/20 text-slate-400'
})

async function loadProfile() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/api/user/profile.php')
    if (!data?.ok) throw new Error(data?.message || 'Unable to load profile')
    profile.value = { ...profile.value, ...data.data }
    editFirst.value = data.data.firstname ?? ''
    editLast.value = data.data.lastname ?? ''
    editPhone.value = data.data.phone ?? ''
  } catch (err) {
    error.value = err?.response?.data?.message || err.message
  } finally {
    loading.value = false
  }
}

async function handleSave() {
  saving.value = true
  saveError.value = ''
  saveSuccess.value = false
  try {
    const { data } = await client.post('/api/user/profile.php', {
      action: 'update',
      firstname: editFirst.value,
      lastname: editLast.value,
      phone: editPhone.value
    })
    if (!data?.ok) throw new Error(data?.message || 'Update failed.')
    saveSuccess.value = true
    await loadProfile()
    setTimeout(() => { saveSuccess.value = false }, 3000)
  } catch (err) {
    saveError.value = err?.response?.data?.message || err.message
  } finally {
    saving.value = false
  }
}

async function handleLogout() {
  loggingOut.value = true
  try {
    await authApi.logout()
  } finally {
    router.push('/login')
  }
}

onMounted(loadProfile)
</script>

<template>
  <div class="min-h-screen bg-[#080d18] pb-28">
    <!-- Skeleton -->
    <template v-if="loading">
      <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-8">
        <div class="flex flex-col items-center">
          <div class="h-20 w-20 rounded-full bg-[#1a2436] animate-pulse mb-3"></div>
          <div class="h-5 w-36 bg-[#1a2436] animate-pulse rounded-lg mb-2"></div>
          <div class="h-4 w-24 bg-[#1a2436] animate-pulse rounded-lg"></div>
        </div>
      </div>
      <div class="mx-4 mt-4 space-y-3">
        <div v-for="i in 4" :key="i" class="h-14 rounded-xl bg-[#111827] animate-pulse"></div>
      </div>
    </template>

    <!-- Error -->
    <div v-else-if="error" class="mx-4 mt-20 bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-2xl px-5 py-4">
      {{ error }}
    </div>

    <template v-else>
      <!-- Gradient hero with avatar -->
      <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-8">
        <div class="flex flex-col items-center">
          <!-- Avatar -->
          <div class="h-20 w-20 rounded-full bg-blue-600 flex items-center justify-center text-2xl font-bold text-white mb-3 overflow-hidden ring-4 ring-blue-600/30">
            <img v-if="avatarSrc" :src="avatarSrc" class="w-full h-full object-cover" alt="Profile avatar" />
            <span v-else>{{ initials }}</span>
          </div>
          <h1 class="text-xl font-bold text-white">{{ profile.full_name || '—' }}</h1>
          <p class="text-slate-400 text-sm mt-1">{{ profile.acct_type || 'Banking Client' }}</p>
          <!-- Status badge -->
          <span class="mt-2 rounded-full px-3 py-1 text-xs font-medium capitalize" :class="statusBadgeClass">
            {{ profile.acct_status || 'unknown' }}
          </span>
        </div>
      </div>

      <!-- Account info (read-only) -->
      <div class="bg-[#111827] rounded-2xl mx-4 mt-4 divide-y divide-[#1e2d44]">
        <div class="px-4 py-3.5 flex justify-between items-center">
          <span class="text-slate-400 text-sm">Account No</span>
          <span class="text-white text-sm font-mono">{{ profile.acct_no || '—' }}</span>
        </div>
        <div class="px-4 py-3.5 flex justify-between items-center">
          <span class="text-slate-400 text-sm">Account Type</span>
          <span class="text-white text-sm">{{ profile.acct_type || '—' }}</span>
        </div>
        <div class="px-4 py-3.5 flex justify-between items-center">
          <span class="text-slate-400 text-sm">Currency</span>
          <span class="text-white text-sm">{{ profile.acct_currency || '—' }}</span>
        </div>
        <div class="px-4 py-3.5 flex justify-between items-center">
          <span class="text-slate-400 text-sm">Email</span>
          <span class="text-white text-sm truncate max-w-[55%] text-right">{{ profile.email || '—' }}</span>
        </div>
      </div>

      <!-- Edit section -->
      <div class="px-4 mt-6">
        <h2 class="text-white font-semibold mb-4">Edit details</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-xs text-slate-400 mb-1.5">First Name</label>
            <input
              v-model="editFirst"
              type="text"
              placeholder="First name"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
            />
          </div>

          <div>
            <label class="block text-xs text-slate-400 mb-1.5">Last Name</label>
            <input
              v-model="editLast"
              type="text"
              placeholder="Last name"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
            />
          </div>

          <div>
            <label class="block text-xs text-slate-400 mb-1.5">Phone Number</label>
            <input
              v-model="editPhone"
              type="tel"
              placeholder="+1 555 000 0000"
              class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-sm"
            />
          </div>

          <!-- Save feedback -->
          <div v-if="saveSuccess" class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm rounded-xl px-4 py-3">
            Profile updated successfully.
          </div>
          <div v-if="saveError" class="bg-red-500/10 border border-red-500/30 text-red-400 text-sm rounded-xl px-4 py-3">
            {{ saveError }}
          </div>

          <button
            @click="handleSave"
            :disabled="saving"
            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-4 transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            {{ saving ? 'Saving...' : 'Save Changes' }}
          </button>
        </div>
      </div>

      <!-- Logout -->
      <div class="px-4 mt-4">
        <button
          @click="handleLogout"
          :disabled="loggingOut"
          class="mx-0 bg-red-500/10 border border-red-500/20 text-red-400 rounded-2xl py-4 text-center font-semibold w-full transition hover:bg-red-500/20 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          {{ loggingOut ? 'Signing out...' : 'Sign Out' }}
        </button>
      </div>
    </template>
  </div>
</template>
