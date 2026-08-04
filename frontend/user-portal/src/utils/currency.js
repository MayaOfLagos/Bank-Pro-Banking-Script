/**
 * Currency helper — backed by the same ISO-style symbol JSON the PHP
 * side reads at include/data/currency-symbols.json. Any change to the
 * canonical file should be mirrored here.
 *
 * The JSON is imported statically so Vite tree-shakes it into the bundle
 * once (no fetch at runtime).
 */

import symbols from '../data/currency-symbols.json'
import { formatMoney } from './format'

const byCode = new Map()
for (const row of symbols) {
  if (row?.abbreviation) {
    const code = String(row.abbreviation).toUpperCase()
    byCode.set(code, {
      code,
      name: row.currency || code,
      symbol: row.symbol || code,
    })
  }
}

/**
 * Return the display symbol for a currency code (e.g. "USD" → "$").
 * Falls back to the code itself for unknown codes, or to `fallback`
 * when the input is empty/null/undefined.
 */
export function currencySymbol(code, fallback = '$') {
  if (!code) return fallback
  const upper = String(code).toUpperCase()
  return byCode.get(upper)?.symbol ?? upper
}

/** Human-readable currency name for a code (e.g. "USD" → "United States Dollar"). */
export function currencyName(code) {
  if (!code) return ''
  const upper = String(code).toUpperCase()
  return byCode.get(upper)?.name ?? upper
}

/** Full record for a code — { code, name, symbol } — or null. */
export function currencyEntry(code) {
  if (!code) return null
  return byCode.get(String(code).toUpperCase()) ?? null
}

/**
 * All known currencies as `{ code, name, symbol }` objects. Handy for
 * dropdowns. Sorted alphabetically by code so the order is stable.
 */
export function currencyList() {
  return Array.from(byCode.values()).sort((a, b) => a.code.localeCompare(b.code))
}

/**
 * Format a numeric amount with the appropriate symbol prefix.
 * Reuses formatMoney so trimTrailingZero / minFractionDigits work.
 *
 *   formatCurrency(1234, 'USD') → '$1,234.00'
 *   formatCurrency(1234, 'NGN', { trimTrailingZero: true }) → '₦1,234'
 */
export function formatCurrency(amount, code, options = {}) {
  const sym = currencySymbol(code)
  const { integer, fraction } = formatMoney(amount, options)
  return fraction ? `${sym}${integer}.${fraction}` : `${sym}${integer}`
}
