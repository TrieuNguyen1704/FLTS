import { apiDownload, apiRequest } from './api'

export const documentService = {
  list: (courseId, query = '') => apiRequest(`/courses/${courseId}/documents${query ? `?q=${encodeURIComponent(query)}` : ''}`),
  upload: (courseId, file) => {
    const formData = new FormData()
    // The key must match Laravel's `document` validation rule; changing only UI text would not be enough.
    formData.append('document', file)
    return apiRequest(`/courses/${courseId}/documents`, { method: 'POST', body: formData })
  },
  remove: (courseId, documentId) => apiRequest(`/courses/${courseId}/documents/${documentId}`, { method: 'DELETE' }),
  download: (courseId, document) => apiDownload(`/courses/${courseId}/documents/${document.id}/download`, document.original_name),
}
