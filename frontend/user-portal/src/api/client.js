import axios from 'axios'

let csrfToken = ''

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

client.interceptors.response.use((response) => {
  const nextToken = response?.data?.data?.csrf_token
  if (typeof nextToken === 'string' && nextToken) csrfToken = nextToken
  return response
})

export default client
