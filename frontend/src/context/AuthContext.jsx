import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react'
import api, { sesion } from '../lib/api'

/**
 * Estado de autenticacion de la aplicacion.
 *
 * Se hidrata desde localStorage para que un F5 no saque al usuario, y
 * luego confirma contra /auth/yo por si el token ya vencio o al usuario
 * lo desactivaron desde el panel.
 */
const AuthContext = createContext(null)

export function AuthProvider({ children }) {
  const [usuario, setUsuario] = useState(() => sesion.usuario())
  const [cargando, setCargando] = useState(Boolean(sesion.token()))

  useEffect(() => {
    let vivo = true

    async function confirmar() {
      if (!sesion.token()) {
        setCargando(false)
        return
      }
      try {
        const { datos } = await api.get('/auth/yo', null, { ttl: 0 })
        if (!vivo) return
        setUsuario(datos.usuario)
        sesion.guardar({ usuario: datos.usuario })
      } catch {
        if (!vivo) return
        sesion.limpiar()
        setUsuario(null)
      } finally {
        if (vivo) setCargando(false)
      }
    }

    confirmar()
    return () => {
      vivo = false
    }
  }, [])

  const entrar = useCallback(async (rol, credenciales) => {
    const rutas = {
      ESTUDIANTE: '/auth/login/estudiante',
      DOCENTE: '/auth/login/docente',
      ADMIN: '/auth/login/admin',
    }

    const { datos } = await api.post(rutas[rol], credenciales)
    sesion.guardar(datos)
    setUsuario(datos.usuario)
    return datos.usuario
  }, [])

  const salir = useCallback(async () => {
    try {
      await api.post('/auth/logout', { refresh_token: sesion.refresh() })
    } catch {
      // Cerrar sesion nunca debe fallar de cara al usuario
    }
    sesion.limpiar()
    setUsuario(null)
  }, [])

  const refrescarPerfil = useCallback(async () => {
    const { datos } = await api.get('/auth/yo', null, { ttl: 0, forzar: true })
    setUsuario(datos.usuario)
    sesion.guardar({ usuario: datos.usuario })
    return datos.usuario
  }, [])

  const valor = useMemo(
    () => ({
      usuario,
      cargando,
      autenticado: Boolean(usuario),
      rol: usuario?.rol ?? null,
      esAdmin: usuario?.rol === 'ADMIN',
      esDocente: usuario?.rol === 'DOCENTE',
      esEstudiante: usuario?.rol === 'ESTUDIANTE',
      entrar,
      salir,
      refrescarPerfil,
    }),
    [usuario, cargando, entrar, salir, refrescarPerfil]
  )

  return <AuthContext.Provider value={valor}>{children}</AuthContext.Provider>
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth debe usarse dentro de <AuthProvider>')
  return ctx
}
