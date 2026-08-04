import client from './client'

export const authApi = {
  status: () => client.get('/api/auth/status.php'),
  login: ({ acct_no, password }) => client.post('/api/auth/login.php', {
    acct_no,
    acct_password: password
  }),
  pinContext: () => client.get('/api/auth/pin-context.php'),
  verifyPin: (payload) => client.post('/api/auth/pin-verify.php', payload),
  forgotPassword: (payload) => client.post('/api/auth/forgot-password.php', payload),
  validateResetToken: (params) => client.get('/api/auth/reset-token-validate.php', { params }),
  resetPassword: (payload) => client.post('/api/auth/reset-password.php', payload),
  register: (payload) => client.post('/api/auth/register.php', payload),
  logout: () => client.post('/api/auth/logout.php')
}
