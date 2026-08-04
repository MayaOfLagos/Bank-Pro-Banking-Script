import { z } from 'zod'
import { currencyList } from '../utils/currency'
import accountTypes from '../data/account-types.json'
import countries from '../data/countries.json'

// Reusable field schemas. Compose these into per-form schemas rather than
// duplicating regex / length rules across views.

export const email = z
  .string({ required_error: 'Email is required' })
  .trim()
  .toLowerCase()
  .min(1, 'Email is required')
  .email('Enter a valid email address')

export const phone = z
  .string({ required_error: 'Phone is required' })
  .trim()
  .min(7, 'Phone number is too short')
  .max(20, 'Phone number is too long')
  .regex(/^\+?[0-9\s()-]+$/, 'Phone can only contain digits, spaces, and + ( ) -')

export const password = z
  .string({ required_error: 'Password is required' })
  .min(8, 'Password must be at least 8 characters')
  .regex(/[A-Za-z]/, 'Password must contain a letter')
  .regex(/[0-9]/, 'Password must contain a number')

// Password rule for legacy screens where the backend still allows 6.
// New surfaces should use `password` above.
export const legacyPassword = z
  .string({ required_error: 'Password is required' })
  .min(6, 'Password must be at least 6 characters')

export const pin = z
  .string({ required_error: 'PIN is required' })
  .regex(/^\d{4}$/, 'PIN must be exactly 4 digits')

export const otp = z
  .string({ required_error: 'Code is required' })
  .regex(/^\d{6}$/, 'Code must be exactly 6 digits')

export const shortCode = z
  .string({ required_error: 'Code is required' })
  .trim()
  .min(3, 'Code is too short')
  .max(15, 'Code is too long')

export const accountNumber = z
  .string({ required_error: 'Account number is required' })
  .trim()
  .min(4, 'Account number is too short')
  .max(34, 'Account number is too long')
  .regex(/^[A-Za-z0-9-]+$/, 'Account number can only contain letters, digits, and dashes')

export const swiftCode = z
  .string()
  .trim()
  .regex(/^([A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?)?$/i, 'SWIFT/BIC must be 8 or 11 alphanumeric characters')
  .optional()
  .or(z.literal(''))

export const routingNumber = z
  .string()
  .trim()
  .regex(/^\d{9}?$/, 'Routing number must be 9 digits')
  .optional()
  .or(z.literal(''))

export const nonEmptyString = (label = 'This field') =>
  z.string({ required_error: `${label} is required` }).trim().min(1, `${label} is required`)

// Money is trickier because <input type="number"> gives us a string until
// coerced. We normalize to number and enforce > 0 by default.
export const moneyAmount = (options = {}) => {
  const { min = 0.01, max = 1_000_000_000, label = 'Amount' } = options
  return z
    .union([z.string(), z.number()])
    .transform((v) => (typeof v === 'string' ? Number(v) : v))
    .refine((n) => !Number.isNaN(n), `${label} must be a number`)
    .refine((n) => n >= min, `${label} must be at least ${min}`)
    .refine((n) => n <= max, `${label} must not exceed ${max}`)
}

// Login: accepts account number, username, or email. Server does the OR
// match; client just requires something.
export const loginIdentifier = z
  .string({ required_error: 'Enter your account number, username, or email' })
  .trim()
  .min(1, 'Enter your account number, username, or email')

// Common form-level schemas. Extend in views for per-form fields.
export const loginSchema = z.object({
  acct_no: loginIdentifier,
  acct_password: legacyPassword,
})

export const forgotPasswordSchema = z.object({
  email,
})

export const resetPasswordSchema = z
  .object({
    new_password: password,
    confirm_password: z.string({ required_error: 'Confirm your new password' }),
  })
  .refine((d) => d.new_password === d.confirm_password, {
    path: ['confirm_password'],
    message: 'Passwords do not match',
  })

// Register wizard — one schema per step so `handleSubmit` per step doesn't
// short-circuit until the whole form is valid at the very end.
const nameField = z
  .string({ required_error: 'Required' })
  .trim()
  .min(2, 'Must be at least 2 characters')
  .max(50, 'Must be 50 characters or fewer')
  .regex(/^[\p{L} .'-]+$/u, 'Only letters, spaces, and .-\'')

const isoDate = z
  .string({ required_error: 'Required' })
  .regex(/^\d{4}-\d{2}-\d{2}$/, 'Enter a valid date')

const eighteenOrOlder = isoDate.refine((s) => {
  const dob = new Date(s + 'T00:00:00')
  if (Number.isNaN(dob.getTime())) return false
  const today = new Date()
  let age = today.getFullYear() - dob.getFullYear()
  const m = today.getMonth() - dob.getMonth()
  if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age -= 1
  return age >= 18 && age <= 120
}, 'You must be at least 18 years old')

// All catalogs kept in step with the shared JSON so client validation
// and server validation cannot drift. Uppercase sets for O(1) lookups.
const KNOWN_CURRENCY_CODES = new Set(currencyList().map((c) => c.code))
const KNOWN_COUNTRY_CODES = new Set(countries.map((c) => String(c.code).toUpperCase()))
const KNOWN_COUNTRY_NAMES = new Set(countries.map((c) => String(c.name).toLowerCase()))
const KNOWN_ACCOUNT_TYPES = new Set(accountTypes.map((a) => String(a.code)))

// Countries accept either the ISO code ("US") or the full name — some
// legacy rows carry free-form names, and admin panels might not have been
// migrated yet.
export const registerStep1Schema = z.object({
  firstname: nameField,
  lastname: nameField,
  dob: eighteenOrOlder,
  country: z
    .string({ required_error: 'Select a country' })
    .trim()
    .min(2, 'Select a country')
    .refine(
      (v) => KNOWN_COUNTRY_CODES.has(v.toUpperCase()) || KNOWN_COUNTRY_NAMES.has(v.toLowerCase()),
      { message: 'Select a supported country' },
    ),
})

export const registerStep2Schema = z.object({
  email,
  phone,
  currency: z
    .string({ required_error: 'Select a currency' })
    .trim()
    .min(1, 'Select a currency')
    .refine((code) => KNOWN_CURRENCY_CODES.has(code.toUpperCase()), {
      message: 'Select a supported currency',
    }),
  acct_type: z
    .string({ required_error: 'Select an account type' })
    .refine((code) => KNOWN_ACCOUNT_TYPES.has(code), {
      message: 'Select a supported account type',
    }),
})

export const registerStep3Schema = z
  .object({
    password,
    confirm_password: z.string({ required_error: 'Confirm your password' }),
    pin,
    terms_accepted: z.literal(true, { errorMap: () => ({ message: 'You must accept the terms' }) }),
  })
  .refine((d) => d.password === d.confirm_password, {
    path: ['confirm_password'],
    message: 'Passwords do not match',
  })
