<template>
  <div class="min-h-screen bg-[#080d18] text-white">
    <!-- Gradient header -->
    <div class="bg-gradient-to-b from-[#0d1b38] to-[#080d18] px-5 pt-12 pb-6">
      <button
        @click="router.back()"
        class="flex items-center gap-2 text-slate-400 mb-4 hover:text-slate-200 transition"
      >
        <ArrowLeftIcon class="h-5 w-5" />
        <span class="text-sm">Back</span>
      </button>
      <p class="text-slate-500 text-xs mb-1">Final step</p>
      <h1 class="text-2xl font-bold text-white">Enter OTP</h1>
      <p class="text-slate-400 text-sm mt-1">One-time password sent to your email</p>
    </div>

    <!-- Form card -->
    <div class="bg-[#111827] rounded-2xl mx-4 mt-4 p-5 border border-[#1e2d44]">
      <p class="text-slate-400 text-sm mb-5">
        An OTP has been sent to your registered email address. Enter it below to complete your transfer.
      </p>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs text-slate-400 mb-1.5">OTP Code</label>
          <input
            v-model="otp"
            type="password"
            inputmode="numeric"
            maxlength="6"
            required
            placeholder="••••••"
            class="w-full bg-[#1a2436] border border-[#1e2d44] rounded-xl px-4 py-3.5 text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 text-center text-2xl tracking-[0.5em] font-bold"
          />
        </div>

        <div v-if="error" class="bg-red-500/10 border border-red-500/20 text-red-400 text-sm rounded-xl px-4 py-3">
          {{ error }}
        </div>

        <button
          type="submit"
          :disabled="saving"
          class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-2xl py-4 transition disabled:opacity-50"
        >
          {{ saving ? 'Verifying...' : 'Verify OTP' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import client from '../api/client'

const router = useRouter()
const otp = ref('')
const saving = ref(false)
const error = ref('')

const submit = async () => {
  error.value = ''
  saving.value = true
  try {
    const { data } = await client.post('/api/user/transfer-verify-pin.php', { pin: otp.value })
    if (!data?.ok) throw new Error(data?.message || 'Verification failed')
    router.push(data.data.next_route)
  } catch (err) {
    error.value = err?.response?.data?.message || err.message
    otp.value = ''
  } finally {
    saving.value = false
  }
}
</script>
