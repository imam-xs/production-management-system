// Thin wrapper over fetch. No axios — one less dependency, and the only two
// things we actually need are a base URL and the bearer token.

const BASE = import.meta.env.VITE_API_URL || 'http://localhost:8001/api/v1'
const TOKEN_KEY = 'pms_token'

export function getToken() {
  return localStorage.getItem(TOKEN_KEY)
}

export function setToken(token) {
  if (token) localStorage.setItem(TOKEN_KEY, token)
  else localStorage.removeItem(TOKEN_KEY)
}

// Thrown for any non-2xx response. `body` carries the API's JSON so callers can
// show the real domain message (e.g. the insufficient-inventory detail) rather
// than a generic failure.
export class ApiError extends Error {
  constructor(status, body) {
    super(body?.message || `Request failed (${status})`)
    this.status = status
    this.body = body
  }
}

async function request(method, path, data) {
  const headers = { Accept: 'application/json' }
  const token = getToken()
  if (token) headers.Authorization = `Bearer ${token}`
  if (data !== undefined) headers['Content-Type'] = 'application/json'

  const res = await fetch(`${BASE}${path}`, {
    method,
    headers,
    body: data === undefined ? undefined : JSON.stringify(data),
  })

  if (res.status === 204) return null

  const body = await res.json().catch(() => null)

  if (!res.ok) {
    // An expired or revoked token should drop us back to the login screen
    // rather than leaving the UI in a half-broken state.
    if (res.status === 401 && getToken()) {
      setToken(null)
      window.location.reload()
    }
    throw new ApiError(res.status, body)
  }

  return body
}

export const api = {
  get: (path) => request('GET', path),
  post: (path, data) => request('POST', path, data),
  put: (path, data) => request('PUT', path, data),
  del: (path) => request('DELETE', path),
}

// Maps a validation error response into { field: 'first message' }.
export function fieldErrors(error) {
  const errors = error?.body?.errors
  if (!errors) return {}
  return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]))
}
