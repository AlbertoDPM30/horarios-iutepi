import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import api from '../lib/api'
import { useAuth } from './AuthContext'
import { useAvisos } from './AvisosContext'

/**
 * Bandeja de la campana.
 *
 * Se consulta cada 60 s (no menos: el backend limita las rafagas) y solo
 * mientras la pestana esta visible, para no gastar bateria ni cuota.
 */
const NotificacionesContext = createContext(null)

const INTERVALO_MS = 60_000

export function NotificacionesProvider({ children }) {
  const { autenticado } = useAuth()
  const { push } = useAvisos()

  const [notificaciones, setNotificaciones] = useState([])
  const [noLeidas, setNoLeidas] = useState(0)
  const [cargando, setCargando] = useState(false)
  const ultimoIdVisto = useRef(0)

  const consultar = useCallback(
    async ({ silencioso = true } = {}) => {
      if (!autenticado) return
      if (!silencioso) setCargando(true)

      try {
        const { datos, meta } = await api.get('/notificaciones', null, { ttl: 20_000, forzar: !silencioso })
        setNotificaciones(datos || [])
        setNoLeidas(meta?.no_leidas ?? 0)

        // Notificacion push solo para lo que llego nuevo
        const masReciente = datos?.[0]
        if (masReciente && !masReciente.leida) {
          const id = Number(masReciente.notificacion_id)
          if (ultimoIdVisto.current && id > ultimoIdVisto.current) {
            push(masReciente.titulo, masReciente.mensaje, { etiqueta: `notif-${id}` })
          }
          ultimoIdVisto.current = Math.max(ultimoIdVisto.current, id)
        }
      } catch {
        // La campana no debe romper la pantalla si falla
      } finally {
        if (!silencioso) setCargando(false)
      }
    },
    [autenticado, push]
  )

  useEffect(() => {
    if (!autenticado) {
      setNotificaciones([])
      setNoLeidas(0)
      return
    }

    consultar()

    const timer = setInterval(() => {
      if (document.visibilityState === 'visible') consultar()
    }, INTERVALO_MS)

    const alVolver = () => {
      if (document.visibilityState === 'visible') consultar()
    }
    document.addEventListener('visibilitychange', alVolver)

    return () => {
      clearInterval(timer)
      document.removeEventListener('visibilitychange', alVolver)
    }
  }, [autenticado, consultar])

  const marcarLeida = useCallback(async (id) => {
    setNotificaciones((lista) =>
      lista.map((n) => (Number(n.notificacion_id) === Number(id) ? { ...n, leida: 1 } : n))
    )
    setNoLeidas((n) => Math.max(0, n - 1))
    try {
      await api.patch(`/notificaciones/${id}/leer`)
    } catch {
      consultar({ silencioso: false })
    }
  }, [consultar])

  const marcarTodas = useCallback(async () => {
    setNotificaciones((lista) => lista.map((n) => ({ ...n, leida: 1 })))
    setNoLeidas(0)
    try {
      await api.post('/notificaciones/leer-todas')
    } catch {
      consultar({ silencioso: false })
    }
  }, [consultar])

  const valor = useMemo(
    () => ({ notificaciones, noLeidas, cargando, consultar, marcarLeida, marcarTodas }),
    [notificaciones, noLeidas, cargando, consultar, marcarLeida, marcarTodas]
  )

  return <NotificacionesContext.Provider value={valor}>{children}</NotificacionesContext.Provider>
}

export function useNotificaciones() {
  const ctx = useContext(NotificacionesContext)
  if (!ctx) throw new Error('useNotificaciones debe usarse dentro de <NotificacionesProvider>')
  return ctx
}
