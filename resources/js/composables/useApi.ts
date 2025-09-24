// API utility for making authenticated requests with CSRF protection

function getCsrfToken(): string {
  const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement
  return meta?.content || ''
}

export interface ApiRequestOptions extends RequestInit {
  method?: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE'
}

export async function apiRequest(url: string, options: ApiRequestOptions = {}): Promise<Response> {
  const csrfToken = getCsrfToken()

  const defaultOptions: RequestInit = {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-CSRF-TOKEN': csrfToken,
      'X-Requested-With': 'XMLHttpRequest',
    },
  }

  const mergedOptions = {
    ...defaultOptions,
    ...options,
    headers: {
      ...defaultOptions.headers,
      ...options.headers,
    },
  }

  return fetch(url, mergedOptions)
}

export async function apiGet(
  url: string,
  options: Omit<ApiRequestOptions, 'method'> = {}
): Promise<Response> {
  return apiRequest(url, { ...options, method: 'GET' })
}

export async function apiPost(
  url: string,
  data?: any,
  options: Omit<ApiRequestOptions, 'method' | 'body'> = {}
): Promise<Response> {
  return apiRequest(url, {
    ...options,
    method: 'POST',
    body: data ? JSON.stringify(data) : undefined,
  })
}

export async function apiPut(
  url: string,
  data?: any,
  options: Omit<ApiRequestOptions, 'method' | 'body'> = {}
): Promise<Response> {
  return apiRequest(url, {
    ...options,
    method: 'PUT',
    body: data ? JSON.stringify(data) : undefined,
  })
}

export async function apiDelete(
  url: string,
  options: Omit<ApiRequestOptions, 'method'> = {}
): Promise<Response> {
  return apiRequest(url, { ...options, method: 'DELETE' })
}
