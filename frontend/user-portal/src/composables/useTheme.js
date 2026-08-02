import { ref, computed, watchEffect } from 'vue'

const STORAGE_KEY = 'bankpro:theme'

// Explicit user override: 'light' | 'dark' | null (null = follow system).
const override = ref(readStoredOverride())
// Live system preference, kept in sync via matchMedia.
const systemDark = ref(prefersDarkNow())

let mediaQuery = null
let mediaListener = null

function readStoredOverride() {
  if (typeof window === 'undefined') return null
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY)
    if (raw === 'light' || raw === 'dark') return raw
  } catch {
    // localStorage may be disabled — fall through to system default
  }
  return null
}

function prefersDarkNow() {
  if (typeof window === 'undefined' || !window.matchMedia) return false
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

function subscribeToSystem() {
  if (typeof window === 'undefined' || !window.matchMedia) return
  if (mediaQuery) return
  mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
  mediaListener = (event) => {
    systemDark.value = event.matches
  }
  if (typeof mediaQuery.addEventListener === 'function') {
    mediaQuery.addEventListener('change', mediaListener)
  } else if (typeof mediaQuery.addListener === 'function') {
    // Older Safari
    mediaQuery.addListener(mediaListener)
  }
}

/**
 * Composable used inside components. Returns the resolved theme + setters.
 * Refs live at module scope so every caller stays in sync.
 */
export function useTheme() {
  subscribeToSystem()

  const theme = computed(() => override.value ?? (systemDark.value ? 'dark' : 'light'))
  const isDark = computed(() => theme.value === 'dark')
  const isExplicit = computed(() => override.value !== null)

  function setTheme(next) {
    if (next !== 'light' && next !== 'dark') return
    override.value = next
    try {
      window.localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // ignore
    }
  }

  function toggleTheme() {
    setTheme(isDark.value ? 'light' : 'dark')
  }

  function clearOverride() {
    override.value = null
    try {
      window.localStorage.removeItem(STORAGE_KEY)
    } catch {
      // ignore
    }
  }

  // Apply data-theme to <html> whenever the resolved theme changes.
  watchEffect(() => {
    if (typeof document === 'undefined') return
    document.documentElement.setAttribute('data-theme', theme.value)
  })

  return { theme, isDark, isExplicit, setTheme, toggleTheme, clearOverride }
}

/**
 * Boot-time initializer for main.js. Applies the correct data-theme to
 * <html> before Vue mounts so there's no light→dark flash.
 */
export function applyThemeAtBoot() {
  const stored = readStoredOverride()
  const resolved = stored ?? (prefersDarkNow() ? 'dark' : 'light')
  if (typeof document !== 'undefined') {
    document.documentElement.setAttribute('data-theme', resolved)
  }
}
