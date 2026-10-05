import { apiRequest } from './api'

// Browser code only calls Laravel. The internal FastAPI token and Gemini key never reach Vue.
export const ragService = {
  startProcessing: (courseId, documentId) => apiRequest(`/courses/${courseId}/documents/${documentId}/processing-runs`, { method: 'POST' }),
  retryProcessing: (courseId, documentId) => apiRequest(`/courses/${courseId}/documents/${documentId}/processing-runs/retry`, { method: 'POST' }),
  processingStatus: (courseId, documentId) => apiRequest(`/courses/${courseId}/documents/${documentId}/processing`),
  search: (courseId, payload) => apiRequest(`/courses/${courseId}/retrieval-tests`, { method: 'POST', body: JSON.stringify(payload) }),
  generateEvidence: (courseId, payload) => apiRequest(`/courses/${courseId}/evidence-prototypes`, { method: 'POST', body: JSON.stringify(payload) })
}
