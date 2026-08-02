/**
 * Formatting helpers shared across dashboard components. Every place that
 * displays money or a card number goes through here so the visual
 * conventions stay consistent.
 */

/** Format a number for display. Returns { integer, fraction } so views can
 *  render the fraction at a smaller size than the integer per the spec. */
export function formatMoney(value, { minFractionDigits = 2, maxFractionDigits = 2 } = {}) {
  const n = coerceNumber(value)
  const parts = n.toLocaleString(undefined, {
    minimumFractionDigits: minFractionDigits,
    maximumFractionDigits: maxFractionDigits,
  }).split('.')
  return {
    integer: parts[0],
    fraction: parts[1] ?? '',
  }
}

/** Simple one-string version for inline use. */
export function formatMoneyInline(value, options) {
  const { integer, fraction } = formatMoney(value, options)
  return fraction ? `${integer}.${fraction}` : integer
}

export function maskCardNumber(raw) {
  const digits = String(raw ?? '').replace(/\D/g, '')
  if (!digits) return '•••• •••• •••• ••••'
  const last4 = digits.slice(-4).padStart(4, '•')
  return `•••• •••• •••• ${last4}`
}

export function formatCardNumber(raw) {
  const digits = String(raw ?? '').replace(/\D/g, '')
  if (!digits) return ''
  return digits.match(/.{1,4}/g)?.join(' ') ?? digits
}

export function last4(raw) {
  const digits = String(raw ?? '').replace(/\D/g, '')
  return digits.slice(-4)
}

export function coerceNumber(value) {
  if (value === null || value === undefined || value === '') return 0
  const n = Number(value)
  return Number.isFinite(n) ? n : 0
}

/** Merchant initials for TransactionItem when no icon is available. */
export function merchantInitials(name) {
  return String(name || '?')
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((w) => w[0].toUpperCase())
    .join('') || '?'
}
