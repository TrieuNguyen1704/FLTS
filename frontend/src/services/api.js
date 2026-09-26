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
      ? 'Tệp tin đã chọn vượt quá giới hạn tải lên tối đa 10 MB.'
      : `Yêu cầu thất bại (${response.status})`
    throw new ApiError(data.message || fallbackMessage, response.status, data.errors || {})
  }

  return data
}

export async function apiDownload(path, fallbackFilename) {
  const token = localStorage.getItem(TOKEN_KEY)
  const headers = new Headers()
  if (token) headers.set('Authorization', `Bearer ${token}`)
  const response = await fetch(`${API_BASE_URL}${path}`, { headers })

  if (!response.ok) {
    const data = await response.json().catch(() => ({}))
    throw new ApiError(data.message || `Tải xuống thất bại (${response.status})`, response.status, data.errors || {})
  }

  const blob = await response.blob()
  const contentDisposition = response.headers.get('content-disposition') || ''
  const matchedName = contentDisposition.match(/filename\*?=(?:UTF-8''|\")?([^\";]+)/i)
  const filename = matchedName ? decodeURIComponent(matchedName[1].replace(/\"/g, '')) : fallbackFilename
  const objectUrl = URL.createObjectURL(blob)
  const anchor = document.createElement('a')
  anchor.href = objectUrl
  anchor.download = filename
  document.body.appendChild(anchor)
  anchor.click()
  anchor.remove()
  URL.revokeObjectURL(objectUrl)
}
