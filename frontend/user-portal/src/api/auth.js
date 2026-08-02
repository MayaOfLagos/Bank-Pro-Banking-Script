import client from './client'

export const authApi = {
  status: () => client.get('/api/auth/status.php'),
  login: (payload) => client.post('/api/auth/login.php', payload),
  pinContext: () => client.get('/api/auth/pin-context.php'),
  verifyPin: (payload) => client.post('/api/auth/pin-verify.php', payload),
  forgotPassword: (payload) => client.post('/api/auth/forgot-password.php', payload),
  validateResetToken: (params) => client.get('/api/auth/reset-token-validate.php', { params }),
  resetPassword: (payload) => client.post('/api/auth/reset-password.php', payload),
  logout: () => client.post('/api/auth/logout.php')
}
