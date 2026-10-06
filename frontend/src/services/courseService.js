import { apiRequest } from './api'

export const courseService = {
  list: () => apiRequest('/courses'),
  get: (courseId) => apiRequest(`/courses/${courseId}`),
  create: (payload) => apiRequest('/courses', { method: 'POST', body: JSON.stringify(payload) }),
  update: (courseId, payload) => apiRequest(`/courses/${courseId}`, { method: 'PATCH', body: JSON.stringify(payload) }),
  destroy: (courseId, confirmation) => apiRequest(`/courses/${courseId}`, { method: 'DELETE', body: JSON.stringify({ confirmation }) }),
  getStudents: (courseId) => apiRequest(`/courses/${courseId}/students`),
  getAvailableStudents: (courseId, search = '') =>
    apiRequest(`/courses/${courseId}/students/available${search ? `?q=${encodeURIComponent(search)}` : ''}`),
  enrollStudent: (courseId, studentId) =>
    apiRequest(`/courses/${courseId}/enrollments`, { method: 'POST', body: JSON.stringify({ student_id: studentId }) }),
  unenrollStudent: (courseId, studentId) =>
    apiRequest(`/courses/${courseId}/enrollments/${studentId}`, { method: 'DELETE' }),
  joinCourse: (code) =>
    apiRequest('/courses/join', { method: 'POST', body: JSON.stringify({ code }) }),
  regenerateEnrollmentCode: (courseId) =>
    apiRequest(`/courses/${courseId}/enrollment-code/regenerate`, { method: 'POST' }),
  toggleEnrollment: (courseId) =>
    apiRequest(`/courses/${courseId}/enrollment-code/toggle`, { method: 'PATCH' }),
}
