export type ApiSuccess<T> = {
  data: T
}

export type ApiErrorBody = {
  error: {
    code: string
    message: string
  }
}

export class ApiError extends Error {
  readonly code: string

  constructor(code: string, message: string) {
    super(message)
    this.name = 'ApiError'
    this.code = code
  }
}

type RequestOptions = {
  method?: string
  body?: unknown
  signal?: AbortSignal
}

let accessToken: string | null = null

export function setAccessToken(token: string | null) {
  accessToken = token
}

export function getAccessToken(): string | null {
  return accessToken
}

export async function downloadFile(path: string, fileName: string): Promise<void> {
  const headers = new Headers()
  if (accessToken) {
    headers.set('Authorization', `Bearer ${accessToken}`)
  }
  const response = await fetch(`/api/v1${path.startsWith('/') ? path : `/${path}`}`, {
    headers,
    credentials: 'include',
  })
  if (!response.ok) {
    throw new ApiError('HTTP_ERROR', 'Download failed')
  }
  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = fileName
  link.click()
  URL.revokeObjectURL(url)
}

export async function openAuthenticatedBlob(path: string): Promise<void> {
  const headers = new Headers()
  if (accessToken) {
    headers.set('Authorization', `Bearer ${accessToken}`)
  }
  const response = await fetch(`/api/v1${path.startsWith('/') ? path : `/${path}`}`, {
    headers,
    credentials: 'include',
  })
  if (!response.ok) {
    throw new ApiError('HTTP_ERROR', 'Open failed')
  }
  const blob = await response.blob()
  const url = URL.createObjectURL(blob)
  window.open(url, '_blank', 'noopener,noreferrer')
}

function isApiErrorBody(value: unknown): value is ApiErrorBody {
  if (typeof value !== 'object' || value === null || !('error' in value)) {
    return false
  }

  const error = value.error
  if (typeof error !== 'object' || error === null) {
    return false
  }

  const record = error as { code?: unknown; message?: unknown }
  return typeof record.code === 'string' && typeof record.message === 'string'
}

async function parseApiResponse<T>(response: Response): Promise<T> {
  const payload: unknown = await response.json().catch(() => null)

  if (isApiErrorBody(payload)) {
    throw new ApiError(payload.error.code, payload.error.message)
  }

  if (!response.ok) {
    throw new ApiError('HTTP_ERROR', response.statusText || 'Request failed')
  }

  if (typeof payload === 'object' && payload !== null && 'data' in payload) {
    return payload.data as T
  }

  throw new ApiError('INVALID_RESPONSE', 'Response did not include data')
}

export async function apiRequest<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const headers = new Headers()
  headers.set('Accept', 'application/json')
  if (options.body !== undefined) {
    headers.set('Content-Type', 'application/json')
  }
  if (accessToken) {
    headers.set('Authorization', `Bearer ${accessToken}`)
  }

  const response = await fetch(`/api/v1${path.startsWith('/') ? path : `/${path}`}`, {
    method: options.method ?? 'GET',
    headers,
    credentials: 'include',
    body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
    signal: options.signal,
  })

  return parseApiResponse<T>(response)
}

/** Multipart / form-data request (do not set Content-Type — browser sets boundary). */
export async function apiFormRequest<T>(
  path: string,
  formData: FormData,
  options: { method?: string; signal?: AbortSignal } = {},
): Promise<T> {
  const headers = new Headers()
  headers.set('Accept', 'application/json')
  if (accessToken) {
    headers.set('Authorization', `Bearer ${accessToken}`)
  }

  const response = await fetch(`/api/v1${path.startsWith('/') ? path : `/${path}`}`, {
    method: options.method ?? 'POST',
    headers,
    credentials: 'include',
    body: formData,
    signal: options.signal,
  })

  return parseApiResponse<T>(response)
}
