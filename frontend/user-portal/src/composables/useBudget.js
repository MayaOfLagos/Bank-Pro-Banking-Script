import { ref } from 'vue'
import client from '../api/client'

/**
 * Loads /api/user/budget.php. See BudgetWidget for the shape it consumes.
 * Null limit means the user has not set a weekly budget; the widget can
 * decide whether to render a CTA or hide.
 */
export function useBudget() {
  const spent = ref(0)
  const limit = ref(null)
  const currency = ref('$')
  const weekStart = ref('')
  const weekEnd = ref('')
  const loading = ref(false)
  const error = ref('')

  async function load() {
    loading.value = true
    error.value = ''
    try {
      const { data } = await client.get('/api/user/budget.php')
      if (!data?.ok) throw new Error(data?.message || 'Unable to load budget')
      spent.value = Number(data.data?.spent) || 0
      limit.value = data.data?.limit ?? null
      currency.value = data.data?.currency || '$'
      weekStart.value = data.data?.week_start || ''
      weekEnd.value = data.data?.week_end || ''
    } catch (err) {
      error.value = err?.response?.data?.message || err.message || 'Unable to load budget'
    } finally {
      loading.value = false
    }
  }

  return { spent, limit, currency, weekStart, weekEnd, loading, error, load }
}
