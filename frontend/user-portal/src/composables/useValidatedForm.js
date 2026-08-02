import { useForm } from 'vee-validate'
import { toTypedSchema } from '@vee-validate/zod'

// Thin wrapper: pass a Zod schema, get a vee-validate form back. Keeps
// per-view forms boilerplate-free.
//
// Usage:
//   const { defineField, handleSubmit, errors, meta } = useValidatedForm(schema)
//   const [email, emailAttrs] = defineField('email')
//   const onSubmit = handleSubmit(async (values) => { ... })
export function useValidatedForm(schema, options = {}) {
  return useForm({
    validationSchema: toTypedSchema(schema),
    ...options,
  })
}
