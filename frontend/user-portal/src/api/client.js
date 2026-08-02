import axios from 'axios'

let csrfToken = ''
let onUnauthorized = null
let onSecurityTokenExpired = null
let onRateLimited = null
let onServerError = null

const client = axios.create({
  baseURL: '/',
  withCredentials: true,
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

    if (status === 401 && typeof onUnauthorized === 'function') {
      onUnauthorized(message)
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
export function registerAuthHandlers({ unauthorized, securityTokenExpired, rateLimited, serverError } = {}) {
  onUnauthorized = unauthorized || null
  onSecurityTokenExpired = securityTokenExpired || null
  onRateLimited = rateLimited || null
  onServerError = serverError || null
}

export default client
