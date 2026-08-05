import { marked } from 'marked'
import DOMPurify from 'dompurify'

/**
 * Notification bodies are markdown authored in the admin panel and rendered
 * with v-html inside an authenticated banking session, which makes this the
 * only HTML-injection sink in the customer portal. The allowlist below is
 * therefore deliberately narrower than "whatever an admin might want to
 * write" — an admin account is a privileged account, not a trusted one, and a
 * single compromised or coerced admin should not be able to reach a
 * customer's session.
 *
 * What is excluded, and why:
 *   - <script>, <iframe>, <object>, <embed>, <form>, <input>, <button>:
 *     direct script execution, clickjacking, or a convincing fake login form
 *     rendered inside the real portal chrome.
 *   - <style> and the `style` attribute: CSS is an exfiltration channel
 *     (attribute selectors + background-image) and can position an invisible
 *     overlay on top of a real "Send money" control.
 *   - <img>, <video>, <audio>, <svg>: `onerror` is the classic payload
 *     carrier, and even a plain src beacons "this customer read this alert,
 *     at this time, from this IP" to a third-party host. Notifications are
 *     text; nothing of value is lost.
 *   - `class`, `id`, `data-*`, `aria-*`: content must not be able to reach
 *     into the app's own stylesheet or its accessibility tree.
 *   - Any on* handler attribute — none are allowlisted, so DOMPurify drops
 *     them all.
 *   - Any URL scheme other than http(s)/mailto/tel/site-relative, which is
 *     what neutralises `javascript:`, `data:` and `vbscript:` hrefs.
 *
 * Everything a notification legitimately needs — emphasis, links, lists,
 * headings, quotes, inline and block code, rules, line breaks — is allowed.
 */
const ALLOWED_TAGS = [
  'p', 'br', 'hr',
  'strong', 'b', 'em', 'i', 'u', 's', 'del', 'ins', 'sub', 'sup', 'small',
  'a',
  'ul', 'ol', 'li',
  'blockquote',
  'code', 'pre',
  'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
  'span',
]

const ALLOWED_ATTR = ['href', 'title']

// Strict allowlist rather than a blocklist of bad schemes: an absolute
// http(s) URL, mailto, tel, a root-relative path, a fragment, or a query.
//
// The `(?![/\\])` guard is what rejects an off-origin navigation wearing a
// local costume. Both characters matter: when parsing a special-scheme URL a
// browser treats a backslash as a slash, so `/\evil.example` resolves to
// `https://evil.example` exactly as `//evil.example` does. Guarding only `/`
// left the backslash form matching as "site-relative", which kept the href and
// sent it down the same-tab branch of the hook below — a full-page takeover of
// the authenticated SPA from an admin-composed body.
//
// DOMPurify strips control characters and whitespace before testing, so
// "java\nscript:" cannot sneak past by splitting the scheme across lines.
// Exported for scripts/verify-markdown-uris.mjs, which asserts the bypass
// above stays closed.
export const ALLOWED_URI_REGEXP = /^(?:https?:\/\/|mailto:|tel:|\/(?![/\\])|#|\?)/i

// Same equivalence, used by the hook: leading slash-or-backslash pairs are all
// authority-relative, whether or not a scheme precedes them.
export const OFF_ORIGIN_RE = /^(?:https?:)?[/\\]{2}/i

const PURIFY_CONFIG = {
  ALLOWED_TAGS,
  ALLOWED_ATTR,
  ALLOWED_URI_REGEXP,
  ALLOW_DATA_ATTR: false,
  ALLOW_ARIA_ATTR: false,
  ALLOW_UNKNOWN_PROTOCOLS: false,
  // FORBID_CONTENTS is deliberately NOT set. It *replaces* DOMPurify's default
  // list rather than extending it, and the default already covers script,
  // style, template, noscript and title — plus iframe, svg, math, noembed,
  // xmp and annotation-xml, which is the element set the historical mXSS
  // chains were built on. Naming a shorter list here only hoisted those
  // elements' contents back into the output.
  KEEP_CONTENT: true,
  RETURN_TRUSTED_TYPE: false,
}

let hooksInstalled = false

function installHooks() {
  if (hooksInstalled) return
  hooksInstalled = true

  DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName !== 'A') return

    const href = node.getAttribute('href') || ''
    // A link whose href did not survive the URI allowlist is left as inert
    // text rather than a dead anchor the customer can still click.
    if (!href) {
      node.removeAttribute('target')
      node.removeAttribute('rel')
      return
    }

    // Off-origin links open in a new tab without handing the opener window
    // (and therefore this session's tab) to the destination.
    if (OFF_ORIGIN_RE.test(href)) {
      node.setAttribute('target', '_blank')
      node.setAttribute('rel', 'noopener noreferrer nofollow')
    } else {
      node.removeAttribute('target')
      node.setAttribute('rel', 'noopener noreferrer')
    }
  })
}

/**
 * Markdown source -> sanitised HTML string. The only function in the app that
 * may produce a value bound to v-html; never bypass it.
 */
export function renderMarkdown(source) {
  const raw = typeof source === 'string' ? source : ''
  if (!raw.trim()) return ''

  installHooks()

  let html = ''
  try {
    html = marked.parse(raw, { async: false, gfm: true, breaks: true })
  } catch {
    // A malformed body must never take the list down with it; fall back to
    // the escaped source so the customer still sees the words.
    return DOMPurify.sanitize(raw, { ALLOWED_TAGS: [], ALLOWED_ATTR: [] })
  }

  return DOMPurify.sanitize(String(html), PURIFY_CONFIG)
}

/**
 * Plain-text projection of a markdown body, for accessible names and
 * anywhere a single-line summary is needed. Strips every tag rather than
 * allowlisting, so it is safe to interpolate as text.
 */
export function markdownToText(source) {
  const raw = typeof source === 'string' ? source : ''
  if (!raw.trim()) return ''

  let html = ''
  try {
    html = marked.parse(raw, { async: false, gfm: true, breaks: true })
  } catch {
    html = raw
  }

  const text = DOMPurify.sanitize(String(html), {
    ALLOWED_TAGS: [],
    ALLOWED_ATTR: [],
    FORBID_CONTENTS: ['script', 'style'],
  })

  // sanitize() returns an escaped string; decode it through textContent so
  // "&amp;" reads as "&" instead of leaking the entity to the user.
  const holder = document.createElement('div')
  holder.innerHTML = text
  return (holder.textContent || '').replace(/\s+/g, ' ').trim()
}
