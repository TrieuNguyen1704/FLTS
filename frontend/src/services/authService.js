import { apiRequest } from './api'

export const authService = {
  register: (payload) => apiRequest('/auth/register', { method: 'POST', body: JSON.stringify(payload) }),
  login: (credentials) => apiRequest('/auth/login', { method: 'POST', body: JSON.stringify(credentials) }),
  requestPasswordReset: (email) => apiRequest('/auth/password-reset/request', { method: 'POST', body: JSON.stringify({ email }) }),
  resetPassword: (payload) => apiRequest('/auth/password-reset', { method: 'POST', body: JSON.stringify(payload) }),
  logout: () => apiRequest('/auth/logout', { method: 'POST' }),
  me: () => apiRequest('/auth/me'),
}
