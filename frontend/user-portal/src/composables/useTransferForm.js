import { ref, reactive, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import client from '../api/client'
import { useValidatedForm } from './useValidatedForm'

/**
 * Shared form state for wire + domestic transfer views.
 *
 * @param {object} opts
 * @param {string} opts.metaEndpoint     GET endpoint returning acct_balance / currency / can_transfer.
 * @param {string} opts.submitEndpoint   POST endpoint that starts the transfer flow.
 * @param {import('zod').ZodTypeAny} opts.schema  Zod schema covering every field the form binds.
 * @param {object} opts.initialValues    Initial form values matching `schema`.
 */
export function useTransferForm({ metaEndpoint, submitEndpoint, schema, initialValues }) {
  const router = useRouter()

  const loading = ref(true)
  const canTransfer = ref(false)
  const meta = reactive({ acct_balance: 0, limit_remain: 0, currency: '$' })
  const serverError = ref('')

  const { defineField, handleSubmit, errors, isSubmitting, values } = useValidatedForm(schema, {
    initialValues,
  })

  onMounted(async () => {
    try {
      const { data } = await client.get(metaEndpoint)
      if (data?.ok) {
        canTransfer.value = Boolean(data.data.can_transfer)
        meta.acct_balance = Number(data.data.acct_balance) || 0
        // Some deployments never set limit_remain (defaults null) — treat
        // missing as "no cap enforced" so the form doesn't wrongly block.
        meta.limit_remain = data.data.limit_remain != null ? Number(data.data.limit_remain) : Infinity
        meta.currency = data.data.currency ?? '$'
      }
    } catch {
      // leave defaults; view can still render the restricted state
    } finally {
      loading.value = false
    }
  })

  const submit = handleSubmit(async (formValues) => {
    serverError.value = ''
    try {
      const { data } = await client.post(submitEndpoint, formValues)
      if (!data?.ok) throw new Error(data?.message || 'Transfer failed. Please try again.')
      if (data.data?.next_route) await router.push(data.data.next_route)
    } catch (err) {
      serverError.value = err?.response?.data?.message || err.message || 'Transfer failed. Please try again.'
    }
  })

  return {
    loading,
    canTransfer,
    meta,
    serverError,
    defineField,
    errors,
    isSubmitting,
    values,
    submit,
  }
}
