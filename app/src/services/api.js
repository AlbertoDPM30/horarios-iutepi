const API_URL = import.meta.env.VITE_API_URL || '/api'

async function request(endpoint, options = {}) {
  const token = localStorage.getItem('authToken')
  const headers = {
    'Content-Type': 'application/json',
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...options.headers,
  }

  try {
    const response = await fetch(`${API_URL}/${endpoint}`, { ...options, headers })
    const data = await response.json()
    if (data && (data.success === false || data.error)) {
      throw new Error(data.message || data.error || 'Error en la solicitud')
    }
    if (data && data.data !== undefined) {
      return data.data
    }
    return data
  } catch (err) {
    if (err.name === 'SyntaxError') throw new Error('Error de conexión con el servidor')
    throw err
  }
}

export const api = {
  get: (endpoint) => request(endpoint),
  post: (endpoint, body) => request(endpoint, { method: 'POST', body: JSON.stringify(body) }),
  put: (endpoint, body) => request(endpoint, { method: 'PUT', body: JSON.stringify(body) }),
  patch: (endpoint, body) => request(endpoint, { method: 'PATCH', body: JSON.stringify(body) }),
  delete: (endpoint) => request(endpoint, { method: 'DELETE' }),
}
