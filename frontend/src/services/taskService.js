import { apiRequest } from './api'

export const taskService = {
  getTasks: () => apiRequest('/background-tasks'),
  retryDocumentTask: (courseId, documentId) =>
    apiRequest(`/courses/${courseId}/documents/${documentId}/processing-runs/retry`, {
      method: 'POST',
    }),
  retryGenerationTask: (courseId, learningObjectId) =>
    apiRequest(`/courses/${courseId}/learning-objects/${learningObjectId}/generation-runs/retry`, {
      method: 'POST',
    }),
}
