import axios from 'axios'

let csrfToken = ''
let onUnauthorized = null
let onSecurityTokenExpired = null
let onRateLimited = null
let onServerError = null
let onAccountHold = null

// Without a timeout a stalled connection never rejects, so callers that await
// a request before rendering (boot, route guards) wedge with no way out.
// File uploads pass their own longer timeout.
export const UPLOAD_TIMEOUT = 120000

const client = axios.create({
  baseURL: '/',
  withCredentials: true,
  timeout: 30000,
  headers: {
    'X-Requested-With': 'XMLHttpRequest'
  }
})

client.interceptors.request.use((config) => {
  const method = String(config.method || 'get').toLowerCase()
  if (csrfToken && !['get', 'head', 'options'].includes(method)) {
    config.headers = config.headers || {}
    config.headers['X-CSRF-Token'] = csrfToken
  }
  return config
})

client.interceptors.response.use(
  (response) => {
    const nextToken = response?.data?.data?.csrf_token
    if (typeof nextToken === 'string' && nextToken) csrfToken = nextToken
    return response
  },
  (error) => {
    const status = error?.response?.status
    const message = error?.response?.data?.message || ''

    if (!error?.response) {
      // Views surface err.message directly; axios' own "timeout of 30000ms
      // exceeded" is not something to show a customer.
      error.message = error?.code === 'ECONNABORTED'
        ? 'The server took too long to respond. Please try again.'
        : 'Network error. Check your connection and try again.'
    }

    if (status === 401 && typeof onUnauthorized === 'function') {
      onUnauthorized(message)
    } else if (status === 403 && error?.response?.data?.data?.account_hold && typeof onAccountHold === 'function') {
      // Only the account_hold flag routes here. Other 403s (a disabled
      // feature toggle, a card already issued) are the view's business and
      // must keep reaching its own catch block.
      onAccountHold(message)
    } else if (status === 419 && typeof onSecurityTokenExpired === 'function') {
      onSecurityTokenExpired(message)
    } else if (status === 429 && typeof onRateLimited === 'function') {
      onRateLimited(message)
    } else if (status >= 500 && typeof onServerError === 'function') {
      onServerError(message)
    }

    return Promise.reject(error)
  }
)

export function setCsrfToken(token) {
  if (typeof token === 'string' && token) csrfToken = token
}

export function getCsrfToken() {
  return csrfToken
}

// The auth/toast handlers live outside this module (they depend on Pinia +
// vue-router + vue-toastification which we don't want to import here to keep
// this file dependency-light). main.js installs them at startup.
export function registerAuthHandlers({ unauthorized, securityTokenExpired, rateLimited, serverError, accountHold } = {}) {
  onUnauthorized = unauthorized || null
  onSecurityTokenExpired = securityTokenExpired || null
  onRateLimited = rateLimited || null
  onServerError = serverError || null
  onAccountHold = accountHold || null
}

export default client
