/**
 * Country + geo helpers backed by include/data/countries.json (mirrored to
 * frontend/user-portal/src/data/countries.json so Vite tree-shakes it into
 * the bundle without a runtime fetch).
 */

import countries from '../data/countries.json'

const byCode = new Map()
const byName = new Map()
for (const row of countries) {
  const code = String(row.code || '').toUpperCase()
  if (!code) continue
  byCode.set(code, row)
  byName.set(String(row.name || '').toLowerCase(), row)
}

/** Full list sorted alphabetically by name — good default for dropdowns. */
export function countryList() {
  return countries.slice().sort((a, b) => a.name.localeCompare(b.name))
}

export function countryName(code) {
  if (!code) return ''
  return byCode.get(String(code).toUpperCase())?.name ?? String(code)
}

export function countryCode(name) {
  if (!name) return ''
  return byName.get(String(name).toLowerCase())?.code ?? ''
}

/**
 * Best-effort client-side country detection using nothing but browser
 * APIs — no network call, no privacy concerns.
 *
 * Priority:
 *   1. `Intl.Locale(navigator.language).region` — accurate when the OS
 *      locale carries a region (en-US, fr-FR, etc.); returns "" for
 *      language-only locales (en, fr).
 *   2. Fallback loop through all navigator.languages until one yields a
 *      region.
 *   3. Timezone-based guess via a small lookup for the most common IANA
 *      zones that map unambiguously to a single country.
 *
 * Returns a two-letter ISO code (e.g. "US") or "" if it can't guess.
 */
export function detectCountryCode() {
  const tryLocale = (lang) => {
    try {
      const region = new Intl.Locale(lang).region
      return region ? region.toUpperCase() : ''
    } catch {
      return ''
    }
  }

  if (typeof navigator !== 'undefined') {
    const primary = tryLocale(navigator.language || '')
    if (primary && byCode.has(primary)) return primary
    for (const lang of navigator.languages || []) {
      const r = tryLocale(lang)
      if (r && byCode.has(r)) return r
    }
  }

  try {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''
    const guess = TIMEZONE_TO_COUNTRY[tz]
    if (guess && byCode.has(guess)) return guess
  } catch {}

  return ''
}

/**
 * Minimal IANA timezone → ISO country map. Not exhaustive — only the
 * unambiguous mappings so a bad guess doesn't confidently mislead. If
 * detection matters more, wire an IP-based server-side lookup.
 */
const TIMEZONE_TO_COUNTRY = {
  'America/New_York': 'US', 'America/Chicago': 'US', 'America/Denver': 'US',
  'America/Los_Angeles': 'US', 'America/Phoenix': 'US', 'America/Anchorage': 'US',
  'America/Detroit': 'US', 'America/Indianapolis': 'US', 'Pacific/Honolulu': 'US',
  'America/Toronto': 'CA', 'America/Vancouver': 'CA', 'America/Montreal': 'CA',
  'America/Winnipeg': 'CA', 'America/Halifax': 'CA', 'America/Edmonton': 'CA',
  'America/Mexico_City': 'MX', 'America/Sao_Paulo': 'BR', 'America/Buenos_Aires': 'AR',
  'America/Santiago': 'CL', 'America/Bogota': 'CO', 'America/Lima': 'PE',
  'Europe/London': 'GB', 'Europe/Dublin': 'IE',
  'Europe/Paris': 'FR', 'Europe/Berlin': 'DE', 'Europe/Madrid': 'ES',
  'Europe/Rome': 'IT', 'Europe/Amsterdam': 'NL', 'Europe/Brussels': 'BE',
  'Europe/Vienna': 'AT', 'Europe/Zurich': 'CH', 'Europe/Warsaw': 'PL',
  'Europe/Stockholm': 'SE', 'Europe/Oslo': 'NO', 'Europe/Copenhagen': 'DK',
  'Europe/Helsinki': 'FI', 'Europe/Lisbon': 'PT', 'Europe/Athens': 'GR',
  'Europe/Prague': 'CZ', 'Europe/Budapest': 'HU', 'Europe/Bucharest': 'RO',
  'Europe/Moscow': 'RU', 'Europe/Istanbul': 'TR', 'Europe/Kiev': 'UA',
  'Africa/Lagos': 'NG', 'Africa/Johannesburg': 'ZA', 'Africa/Nairobi': 'KE',
  'Africa/Cairo': 'EG', 'Africa/Accra': 'GH', 'Africa/Casablanca': 'MA',
  'Africa/Algiers': 'DZ',
  'Asia/Tokyo': 'JP', 'Asia/Seoul': 'KR', 'Asia/Shanghai': 'CN',
  'Asia/Hong_Kong': 'HK', 'Asia/Taipei': 'TW', 'Asia/Singapore': 'SG',
  'Asia/Kuala_Lumpur': 'MY', 'Asia/Jakarta': 'ID', 'Asia/Manila': 'PH',
  'Asia/Bangkok': 'TH', 'Asia/Ho_Chi_Minh': 'VN',
  'Asia/Kolkata': 'IN', 'Asia/Calcutta': 'IN', 'Asia/Karachi': 'PK',
  'Asia/Dhaka': 'BD', 'Asia/Dubai': 'AE', 'Asia/Riyadh': 'SA',
  'Asia/Tehran': 'IR', 'Asia/Jerusalem': 'IL',
  'Australia/Sydney': 'AU', 'Australia/Melbourne': 'AU', 'Australia/Perth': 'AU',
  'Australia/Brisbane': 'AU', 'Australia/Adelaide': 'AU',
  'Pacific/Auckland': 'NZ',
}
