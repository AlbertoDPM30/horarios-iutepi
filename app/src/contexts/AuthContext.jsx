import { createContext, useContext, useState, useEffect, useCallback } from 'react'
import { api } from '../services/api'

const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null)
  const [token, setToken] = useState(localStorage.getItem('authToken'))
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (token) {
      setLoading(false)
    } else {
      setLoading(false)
    }
  }, [token])

  const login = useCallback(async (username, password) => {
    const API_URL = import.meta.env.VITE_API_URL || '/api'
    const res = await fetch(`${API_URL}/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password }),
    })
    const data = await res.json()
    if (data.success === false) {
      throw new Error(data.message || 'Error al iniciar sesión')
    }
    if (data.token) {
      localStorage.setItem('authToken', data.token)
      setToken(data.token)
      setUser(data.data?.username ? data.data : { username })
      return data
    }
    throw new Error(data.message || 'Error al iniciar sesión')
  }, [])

  const logout = useCallback(async () => {
    try {
      await api.post('logout')
    } catch (_) {}
    localStorage.removeItem('authToken')
    setToken(null)
    setUser(null)
  }, [])

  const value = { user, token, login, logout, loading, isAuthenticated: !!token }

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const context = useContext(AuthContext)
  if (!context) throw new Error('useAuth debe usarse dentro de AuthProvider')
  return context
}
