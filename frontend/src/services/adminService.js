import { apiRequest } from './api'

export const adminService = {
  listUsers: (query = '') => apiRequest(`/admin/users${query ? `?q=${encodeURIComponent(query)}` : ''}`),
  updateUser: (userId, payload) => apiRequest(`/admin/users/${userId}`, { method: 'PATCH', body: JSON.stringify(payload) }),
}
