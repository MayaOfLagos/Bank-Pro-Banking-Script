<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import {
  ChevronLeftIcon, EnvelopeIcon, PhoneIcon, LifebuoyIcon,
} from '@heroicons/vue/24/solid'
import client from '../api/client'
import ErrorState from '../components/ui/ErrorState.vue'
import EmptyState from '../components/ui/EmptyState.vue'
import LoadingRegion from '../components/skeletons/LoadingRegion.vue'
import SkeletonText from '../components/skeletons/SkeletonText.vue'
import SkeletonDetailRows from '../components/skeletons/SkeletonDetailRows.vue'
import { merchantInitials, formatMoneyInline } from '../utils/format'

const router = useRouter()

const loading = ref(true)
const error = ref('')
const manager = ref({})
const imageFailed = ref(false)

async function load() {
  loading.value = true
  error.value = ''
  try {
    const { data } = await client.get('/api/user/account-manager.php')
    if (!data?.ok) throw new Error(data?.message || 'Unable to load account manager')
    manager.value = data.data || {}
  } catch (err) {
    error.value = err?.response?.data?.message || err.message || 'Unable to load account manager'
  } finally {
    loading.value = false
  }
}
onMounted(load)

const hasManager = computed(() => Boolean(manager.value.has_manager))
const initials = computed(() =>
  manager.value.mgr_name ? merchantInitials(manager.value.mgr_name) : '?',
)

const limitDisplay = computed(() => {
  const limit = Number(manager.value.acct_limit ?? 0)
  if (!limit) return '—'
  return `${manager.value.currency || '$'}${formatMoneyInline(limit, { trimTrailingZero: true })}`
})

const openedDisplay = computed(() => {
  const raw = manager.value.created_at
  if (!raw) return '—'
  const d = new Date(String(raw).replace(' ', 'T'))
  if (Number.isNaN(d.getTime())) return String(raw)
  return d.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' })
})

const accountRows = computed(() => [
  { label: 'Account type', value: manager.value.acct_type || '—' },
  { label: 'Account limit', value: limitDisplay.value },
  { label: 'Opened', value: openedDisplay.value },
])
</script>

<template>
  <div class="page">
    <div class="content">
      <header class="header">
        <button type="button" class="back" aria-label="Go back" @click="router.back()">
          <ChevronLeftIcon class="back-icon" aria-hidden="true" />
        </button>
        <div class="titles">
          <h1 class="title">Account manager</h1>
          <p class="subtitle">Your dedicated point of contact</p>
        </div>
      </header>

      <LoadingRegion v-if="loading" label="your account manager">
        <section class="hero">
          <div class="skeleton sk-avatar" />
          <SkeletonText class="sk-name" size="1.15rem" :line-height="1.2" width="10rem" />
          <SkeletonText size="0.85rem" width="9rem" />
          <div class="contact-actions">
            <div class="skeleton sk-contact" />
            <div class="skeleton sk-contact" />
          </div>
        </section>

        <section class="card">
          <div class="rows">
            <SkeletonDetailRows :rows="2" :card="false" />
          </div>
        </section>

        <section class="card">
          <div class="rows">
            <SkeletonDetailRows :rows="3" :card="false" />
          </div>
        </section>
      </LoadingRegion>

      <ErrorState v-else-if="error" :message="error" />

      <template v-else>
        <section v-if="hasManager" class="hero">
          <div class="avatar">
            <img
              v-if="manager.mgr_image && !imageFailed"
              :src="manager.mgr_image"
              :alt="`${manager.mgr_name}'s photo`"
              @error="imageFailed = true"
            />
            <span v-else>{{ initials }}</span>
          </div>
          <h2 class="name">{{ manager.mgr_name || 'Your account manager' }}</h2>
          <p class="hero-sub">Relationship manager</p>

          <div class="contact-actions">
            <a v-if="manager.mgr_email" class="contact" :href="`mailto:${manager.mgr_email}`">
              <EnvelopeIcon class="contact-icon" aria-hidden="true" />
              Email
            </a>
            <a v-if="manager.mgr_no" class="contact" :href="`tel:${manager.mgr_no}`">
              <PhoneIcon class="contact-icon" aria-hidden="true" />
              Call
            </a>
          </div>
        </section>

        <EmptyState
          v-else
          message="No account manager has been assigned to your account yet."
        >
          <button type="button" class="support-btn" @click="router.push('/tickets')">
            <LifebuoyIcon class="support-icon" aria-hidden="true" />
            Contact support
          </button>
        </EmptyState>

        <section v-if="hasManager" class="card" aria-label="Manager contact details">
          <div class="rows">
            <div v-if="manager.mgr_email" class="row">
              <span class="row-label">Email</span>
              <span class="row-value">{{ manager.mgr_email }}</span>
            </div>
            <div v-if="manager.mgr_no" class="row">
              <span class="row-label">Phone</span>
              <span class="row-value row-value--mono">{{ manager.mgr_no }}</span>
            </div>
          </div>
        </section>

        <section class="card" aria-label="Account details">
          <div class="rows">
            <div v-for="row in accountRows" :key="row.label" class="row">
              <span class="row-label">{{ row.label }}</span>
              <span class="row-value">{{ row.value }}</span>
            </div>
          </div>
        </section>
      </template>
    </div>
  </div>
</template>

<style scoped>
.page { min-height: 100vh; background: var(--bg-gradient); padding: var(--space-5) var(--space-4) 7rem; }
.content { max-width: 30rem; margin: 0 auto; display: flex; flex-direction: column; gap: var(--space-4); }
.header { display: flex; align-items: center; gap: var(--space-3); padding: var(--space-2) 0 var(--space-1); }
.back { width: 2.5rem; height: 2.5rem; border-radius: 1.7rem; border: 1px solid var(--border); background: transparent; color: var(--text-primary); display: inline-flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; }
.back:hover { background: color-mix(in srgb, var(--text-primary) 6%, transparent); }
.back:active { transform: scale(0.95); }
.back-icon { width: 1.15rem; height: 1.15rem; }
.title { font-size: 1.5rem; font-weight: 800; color: var(--text-primary); margin: 0; line-height: 1.1; }
.subtitle { font-size: 0.8rem; color: var(--text-secondary); margin: 0.15rem 0 0; }

.hero { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-6) var(--space-4); box-shadow: var(--shadow-card); display: flex; flex-direction: column; align-items: center; gap: var(--space-2); text-align: center; }
.avatar { width: 5.5rem; height: 5.5rem; border-radius: var(--radius-pill); background: var(--card-gradient-debit); color: var(--text-on-accent); display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.4rem; overflow: hidden; box-shadow: 0 10px 24px -8px color-mix(in srgb, var(--accent-strong) 60%, transparent); }
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.name { font-size: 1.15rem; font-weight: 800; color: var(--text-primary); margin: 0.4rem 0 0; line-height: 1.2; }
.hero-sub { color: var(--text-secondary); font-size: 0.85rem; margin: 0; }

.contact-actions { display: flex; gap: var(--space-3); margin-top: var(--space-3); width: 100%; }
.contact {
  flex: 1 1 0; height: 2.75rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--btn-outline-border);
  background: var(--btn-outline-bg); color: var(--btn-outline-fg);
  font-weight: 600; font-size: 0.85rem;
  text-decoration: none;
  display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem;
  transition: background-color 0.15s ease, transform 0.08s ease;
}
.contact:hover { background: color-mix(in srgb, var(--text-primary) 5%, transparent); }
.contact:active { transform: scale(0.98); }
.contact-icon { width: 1.05rem; height: 1.05rem; }

.card { background: var(--surface); border-radius: var(--radius-lg); padding: var(--space-5) var(--space-4); box-shadow: var(--shadow-card); display: flex; flex-direction: column; gap: var(--space-3); }
.rows { display: flex; flex-direction: column; }
.row { display: flex; justify-content: space-between; align-items: baseline; gap: var(--space-4); padding: var(--space-3) 0; }
.row + .row { border-top: 1px solid var(--divider); }
.row-label { color: var(--text-secondary); font-size: 0.85rem; flex-shrink: 0; }
.row-value { color: var(--text-primary); font-size: 0.9rem; font-weight: 600; text-align: right; min-width: 0; word-break: break-word; }
.row-value--mono { font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; letter-spacing: 0.02em; }

.support-btn {
  margin-top: var(--space-3);
  padding: 0.75rem 1.5rem;
  border-radius: var(--radius-pill);
  border: 1px solid var(--btn-outline-border);
  background: var(--surface); color: var(--text-primary);
  font-weight: 600; font-family: inherit; font-size: 0.9rem;
  cursor: pointer;
  display: inline-flex; align-items: center; gap: 0.45rem;
}
.support-icon { width: 1.1rem; height: 1.1rem; }

/* Skeleton — .hero, .card, .rows and .contact-actions are reused as-is.
   Assumes a manager *is* assigned; the unassigned case swaps in an EmptyState
   of a different height, which no placeholder can predict. */
.sk-avatar { width: 5.5rem; height: 5.5rem; border-radius: var(--radius-pill); }
.sk-name { margin-top: 0.4rem; }
.sk-contact { flex: 1 1 0; height: 2.75rem; border-radius: var(--radius-pill); }
</style>
