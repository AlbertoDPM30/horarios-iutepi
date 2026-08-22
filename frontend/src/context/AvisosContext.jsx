import { createContext, useCallback, useContext, useEffect, useMemo, useRef, useState } from 'react'
import { AlertTriangle, CheckCircle2, Info, WifiOff, X, XCircle } from 'lucide-react'
import { alCambiarConexion } from '../lib/api'
import { cx } from '../lib/utils'

/**
 * Avisos emergentes (toasts) + notificaciones push del navegador.
 *
 * Los mensajes se muestran arriba a la derecha en escritorio y abajo en
 * movil, donde no tapan la navegacion.
 */
const AvisosContext = createContext(null)

const ICONOS = {
  exito: CheckCircle2,
  error: XCircle,
  aviso: AlertTriangle,
  info: Info,
  conexion: WifiOff,
}

const ESTILOS = {
  exito: 'border-emerald-200 bg-emerald-50 text-emerald-900',
  error: 'border-rose-200 bg-rose-50 text-rose-900',
  aviso: 'border-amber-200 bg-amber-50 text-amber-900',
  info: 'border-marca-200 bg-marca-50 text-marca-900',
  conexion: 'border-slate-300 bg-slate-800 text-white',
}

const COLOR_ICONO = {
  exito: 'text-emerald-600',
  error: 'text-rose-600',
  aviso: 'text-amber-600',
  info: 'text-marca-600',
  conexion: 'text-white',
}

export function AvisosProvider({ children }) {
  const [avisos, setAvisos] = useState([])
  const contador = useRef(0)

  const cerrar = useCallback((id) => {
    setAvisos((lista) => lista.filter((a) => a.id !== id))
  }, [])

  const mostrar = useCallback(
    (tipo, mensaje, { titulo = '', duracion = 4500, permanente = false } = {}) => {
      const id = ++contador.current
      setAvisos((lista) => [...lista.slice(-4), { id, tipo, mensaje, titulo, permanente }])

      if (!permanente) {
        setTimeout(() => cerrar(id), duracion)
      }
      return id
    },
    [cerrar]
  )

  const avisar = useMemo(
    () => ({
      exito: (m, o) => mostrar('exito', m, o),
      error: (m, o) => mostrar('error', m, { duracion: 7000, ...o }),
      aviso: (m, o) => mostrar('aviso', m, { duracion: 6000, ...o }),
      info: (m, o) => mostrar('info', m, o),
      cerrar,
    }),
    [mostrar, cerrar]
  )

  /* ---- Notificaciones push del navegador ---- */
  const pedirPermisoPush = useCallback(async () => {
    if (!('Notification' in window)) return false
    if (Notification.permission === 'granted') return true
    if (Notification.permission === 'denied') return false
    const permiso = await Notification.requestPermission()
    return permiso === 'granted'
  }, [])

  const push = useCallback((titulo, cuerpo, { etiqueta = 'iutepi' } = {}) => {
    if (!('Notification' in window) || Notification.permission !== 'granted') return
    if (document.visibilityState === 'visible') return // ya lo esta viendo
    try {
      new Notification(titulo, { body: cuerpo, tag: etiqueta, icon: '/favicon.ico' })
    } catch {
      // Algunos navegadores exigen ServiceWorker: no es critico
    }
  }, [])

  /* ---- Aviso global de caida de la API o la base de datos ---- */
  useEffect(() => {
    const idAviso = { actual: null }

    return alCambiarConexion((ok, detalle) => {
      if (!ok) {
        idAviso.actual = mostrar(
          'conexion',
          detalle || 'Se perdio la conexion con el servidor. Reintentando...',
          { titulo: 'Sin conexion', permanente: true }
        )
        push('Horarios IUTEPI', 'Se perdio la conexion con el servidor.')
      } else {
        if (idAviso.actual) cerrar(idAviso.actual)
        idAviso.actual = null
        mostrar('exito', 'Conexion restablecida.', { duracion: 3000 })
      }
    })
  }, [mostrar, cerrar, push])

  const valor = useMemo(
    () => ({ avisar, push, pedirPermisoPush }),
    [avisar, push, pedirPermisoPush]
  )

  return (
    <AvisosContext.Provider value={valor}>
      {children}

      <div
        className="pointer-events-none fixed inset-x-3 bottom-3 z-[100] flex flex-col gap-2 sm:inset-x-auto sm:bottom-auto sm:right-4 sm:top-4 sm:w-[26rem]"
        role="status"
        aria-live="polite"
      >
        {avisos.map((aviso) => {
          const Icono = ICONOS[aviso.tipo] || Info
          return (
            <div
              key={aviso.id}
              className={cx(
                'pointer-events-auto flex items-start gap-3 rounded-xl border p-3.5 shadow-flotante animate-entrarDerecha',
                ESTILOS[aviso.tipo]
              )}
            >
              <Icono className={cx('mt-0.5 h-5 w-5 shrink-0', COLOR_ICONO[aviso.tipo])} />
              <div className="min-w-0 flex-1">
                {aviso.titulo && <p className="text-sm font-semibold">{aviso.titulo}</p>}
                <p className="text-sm leading-snug break-words">{aviso.mensaje}</p>
              </div>
              <button
                type="button"
                onClick={() => cerrar(aviso.id)}
                className="rounded p-0.5 opacity-60 transition hover:opacity-100"
                aria-label="Cerrar aviso"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
          )
        })}
      </div>
    </AvisosContext.Provider>
  )
}

export function useAvisos() {
  const ctx = useContext(AvisosContext)
  if (!ctx) throw new Error('useAvisos debe usarse dentro de <AvisosProvider>')
  return ctx
}
