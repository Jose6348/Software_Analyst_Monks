const BASE_URL = '/api'

/** Erro normalizado do transporte, no formato `{ error: { code, message } }` da API. */
export class ApiError extends Error {
  readonly status: number
  readonly code: string

  constructor(status: number, code: string, message: string) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.code = code
  }
}

interface RequestOptions {
  method?: 'GET' | 'POST'
  employeeId?: number
  body?: unknown
}

async function request<T>(path: string, options: RequestOptions = {}): Promise<T> {
  const { method = 'GET', employeeId, body } = options
  const headers: Record<string, string> = {}

  if (employeeId !== undefined) {
    headers['X-Employee-Id'] = String(employeeId)
  }

  if (body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }

  let response: Response

  try {
    response = await fetch(`${BASE_URL}${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    // fetch rejeita sem resposta (API fora do ar, offline); sem isso o TypeError cru do
    // browser vazaria em inglês para a UI.
    throw new ApiError(0, 'NETWORK', 'Não foi possível falar com a API.')
  }

  const payload: unknown = await response.json().catch(() => undefined)

  if (!response.ok) {
    throw toApiError(response.status, payload)
  }

  if (payload === undefined) {
    // 2xx sem JSON é infra quebrada; devolver null tipado como T estouraria no consumidor.
    throw new ApiError(response.status, 'INVALID_RESPONSE', 'A API respondeu num formato inesperado.')
  }

  return payload as T
}

function toApiError(status: number, payload: unknown): ApiError {
  if (typeof payload === 'object' && payload !== null && 'error' in payload) {
    const error = payload.error

    if (
      typeof error === 'object' &&
      error !== null &&
      'code' in error &&
      'message' in error &&
      typeof error.code === 'string' &&
      typeof error.message === 'string'
    ) {
      return new ApiError(status, error.code, error.message)
    }
  }

  return new ApiError(status, 'UNKNOWN', 'Não foi possível falar com a API.')
}

export const api = {
  /** Rotas de catálogo, as únicas sem identidade. */
  getPublic: <T>(path: string): Promise<T> => request<T>(path),
  /** Rotas autenticadas: a identidade é obrigatória no tipo — esquecê-la é erro de compilação. */
  get: <T>(path: string, employeeId: number): Promise<T> => request<T>(path, { employeeId }),
  post: <T>(path: string, body: unknown, employeeId: number): Promise<T> =>
    request<T>(path, { method: 'POST', employeeId, body }),
}
