import { apiRequest } from './api'

export const courseService = {
  list: () => apiRequest('/courses'),
  get: (courseId) => apiRequest(`/courses/${courseId}`),
  create: (payload) => apiRequest('/courses', { method: 'POST', body: JSON.stringify(payload) }),
  update: (courseId, payload) => apiRequest(`/courses/${courseId}`, { method: 'PATCH', body: JSON.stringify(payload) }),
}
