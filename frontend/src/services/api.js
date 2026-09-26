const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || '/api'
const TOKEN_KEY = 'flts_token'

export class ApiError extends Error {
  constructor(message, status, errors = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

export async function apiRequest(path, options = {}) {
  const token = localStorage.getItem(TOKEN_KEY)
  const headers = new Headers(options.headers || {})

  if (token) headers.set('Authorization', `Bearer ${token}`)
  // Browsers add the multipart boundary for FormData; forcing JSON here would break document uploads.
  if (options.body && !(options.body instanceof FormData) && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json')
  }

  const response = await fetch(`${API_BASE_URL}${path}`, { ...options, headers })
  const data = await response.json().catch(() => ({}))

  if (!response.ok) {
    const fallbackMessage = response.status === 413
      ? 'The selected file exceeds the 10 MB upload limit.'
      : `Request failed (${response.status})`
    throw new ApiError(data.message || fallbackMessage, response.status, data.errors || {})
  }

  return data
}
