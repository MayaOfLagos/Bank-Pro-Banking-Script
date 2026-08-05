import { readFile } from 'node:fs/promises'
import { dirname, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

/**
 * Guards the URL allowlist that notification bodies are rendered through.
 *
 * Notification bodies are the only HTML-injection sink in the customer portal,
 * and their hrefs are admin-authored. An adversarial review found that the
 * original allowlist guarded `//evil.test` but not `/\evil.test` — a browser
 * parsing a special-scheme URL treats a backslash as a slash, so the second
 * form is the same off-origin navigation, and it was being classified as
 * site-relative and rendered as a same-tab link inside the authenticated SPA.
 *
 * The regexes are imported from the real module rather than restated here, so
 * this fails if the shipped values regress rather than if a copy drifts.
 * `safeInternalPath` (the router.push sink, which had the same gap) cannot be
 * imported — its module pulls in axios and Vue — so it is lifted out of the
 * source text.
 */

const scriptDirectory = dirname(fileURLToPath(import.meta.url))
const portalDirectory = resolve(scriptDirectory, '..')

const { ALLOWED_URI_REGEXP, OFF_ORIGIN_RE } = await import('../src/utils/markdown.js')

const storeSource = await readFile(resolve(portalDirectory, 'src/stores/notifications.js'), 'utf8')
const declaration = storeSource.match(/export function safeInternalPath[\s\S]*?\n\}/)
if (!declaration) {
  console.error('verify-markdown-uris: safeInternalPath is no longer declared as expected in stores/notifications.js')
  process.exit(1)
}
const safeInternalPath = new Function(`${declaration[0].replace('export ', '')}\nreturn safeInternalPath`)()

// [href, may the sanitiser keep it, may router.push accept it]
const cases = [
  ['/dashboard', true, true],
  ['/transactions/wire/9', true, true],
  ['#top', true, false],
  ['?q=1', true, false],
  ['https://x.test/a', true, false],
  ['mailto:a@b.c', true, false],
  ['tel:+15551234', true, false],
  ['//evil.test', false, false],
  ['/\\evil.test', false, false],
  ['\\\\evil.test', false, false],
  ['https:/\\evil.test', false, false],
  ['javascript:alert(1)', false, false],
  ['data:text/html,x', false, false],
]

const failures = []

for (const [href, mayRender, mayNavigate] of cases) {
  const rendered = ALLOWED_URI_REGEXP.test(href)
  if (rendered !== mayRender) {
    failures.push(`${JSON.stringify(href)}: allowlist ${rendered ? 'kept' : 'stripped'} it, expected the opposite`)
  }
  const navigated = Boolean(safeInternalPath(href))
  if (navigated !== mayNavigate) {
    failures.push(`${JSON.stringify(href)}: safeInternalPath ${navigated ? 'allowed' : 'blocked'} it, expected the opposite`)
  }
  // Anything that survives the allowlist and is not recognised as off-origin
  // renders as a same-tab link with no rel guard. That combination is only
  // ever acceptable for a genuinely local reference.
  if (rendered && !OFF_ORIGIN_RE.test(href) && /^[/\\]{2}|^[a-z][a-z0-9+.-]*:[/\\]{2}/i.test(href)) {
    failures.push(`${JSON.stringify(href)}: kept, but treated as same-tab despite being authority-relative`)
  }
}

if (failures.length) {
  console.error('verify-markdown-uris FAILED:')
  for (const line of failures) console.error('  - ' + line)
  process.exit(1)
}

console.log(`Verified notification URL allowlist (${cases.length} cases)`)
