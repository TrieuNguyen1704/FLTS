import { apiRequest } from './api'

export const learningObjectService = {
  list: (courseId) => apiRequest(`/courses/${courseId}/learning-objects`),
  get: (courseId, objectId) => apiRequest(`/courses/${courseId}/learning-objects/${objectId}`),
  generateQuiz: (courseId, payload) => apiRequest(`/courses/${courseId}/learning-objects/quizzes`, { method: 'POST', body: JSON.stringify(payload) }),
  retryGeneration: (courseId, objectId) => apiRequest(`/courses/${courseId}/learning-objects/${objectId}/generation-runs/retry`, { method: 'POST' }),
  update: (courseId, objectId, payload) => apiRequest(`/courses/${courseId}/learning-objects/${objectId}`, { method: 'PATCH', body: JSON.stringify(payload) }),
  publish: (courseId, objectId) => apiRequest(`/courses/${courseId}/learning-objects/${objectId}/publish`, { method: 'POST' }),
  archive: (courseId, objectId) => apiRequest(`/courses/${courseId}/learning-objects/${objectId}/archive`, { method: 'POST' }),
  startAttempt: (courseId, objectId) => apiRequest(`/courses/${courseId}/learning-objects/${objectId}/quiz-attempts`, { method: 'POST' }),
  submitAttempt: (courseId, objectId, attemptId, answers) => apiRequest(`/courses/${courseId}/learning-objects/${objectId}/quiz-attempts/${attemptId}/submit`, {
    method: 'POST',
    body: JSON.stringify({ answers }),
  }),
  attemptHistory: (courseId, objectId) => apiRequest(`/courses/${courseId}/learning-objects/${objectId}/quiz-attempts`),
}
