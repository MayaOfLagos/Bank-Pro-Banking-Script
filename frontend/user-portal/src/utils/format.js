/**
 * Formatting helpers shared across dashboard components. Every place that
 * displays money or a card number goes through here so the visual
 * conventions stay consistent.
 */

/** Format a number for display. Returns { integer, fraction } so views can
 *  render the fraction at a smaller size than the integer per the spec.
 *  Set trimTrailingZero to collapse ".00" -> ".0" and ".50" -> ".5" for
 *  the round-number typography the reference uses. */
export function formatMoney(value, {
  minFractionDigits = 2,
  maxFractionDigits = 2,
  trimTrailingZero = false,
} = {}) {
  const n = coerceNumber(value)
  const parts = n.toLocaleString(undefined, {
    minimumFractionDigits: minFractionDigits,
    maximumFractionDigits: maxFractionDigits,
  }).split('.')
  let fraction = parts[1] ?? ''
  // Only trim when the fraction is entirely zeros — the intent is
  // round-number typography ("$45.00" → "$45"), not lopping meaningful
  // digits off values like $45.20.
  if (trimTrailingZero && fraction && /^0+$/.test(fraction)) {
    fraction = ''
  }
  return {
    integer: parts[0],
    fraction,
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

/**
 * Two-letter avatar initials for a person, from whatever name parts exist.
 *
 * Callers pass whatever they have, in any order. Accounts here were created
 * through several different signup flows over the years, so a row may carry
 * only a firstname, only a lastname, or a single fullname — all three have to
 * yield two sensible letters, which is why a lone token falls back to its
 * first two characters instead of a single initial.
 *
 *   ('Ada', 'Lovelace')    -> 'AL'
 *   ('Ada Byron Lovelace') -> 'AL'   first + last; middle names skipped
 *   ('Ada', '')            -> 'AD'
 *   ('', 'Lovelace')       -> 'LO'
 *   ('X')                  -> 'X'    one letter is all there is
 *   ('', '')               -> '?'
 */
export function personInitials(...parts) {
  const words = parts
    .flatMap((part) => String(part ?? '').split(/[\s,._-]+/))
    // Strip punctuation so "O'Brien" or "(Ada)" can't yield a quote or paren.
    .map((word) => word.replace(/[^\p{L}\p{N}]/gu, ''))
    .filter(Boolean)

  if (!words.length) return '?'
  if (words.length === 1) return words[0].slice(0, 2).toLocaleUpperCase()
  return (words[0][0] + words[words.length - 1][0]).toLocaleUpperCase()
}
