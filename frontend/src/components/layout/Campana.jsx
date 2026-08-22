import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import {
  Bell, BellOff, CalendarDays, CheckCheck, Info, Settings, TriangleAlert, WifiOff,
} from 'lucide-react'
import { useNotificaciones } from '../../context/NotificacionesContext'
import { cx, tiempoRelativo } from '../../lib/utils'

const ICONO_TIPO = {
  CONFLICTO: TriangleAlert,
  HORARIO: CalendarDays,
  PERIODO: CalendarDays,
  CONEXION: WifiOff,
  SISTEMA: Settings,
}

const COLOR_SEVERIDAD = {
  ERROR: 'bg-rose-100 text-rose-700',
  ADVERTENCIA: 'bg-amber-100 text-amber-700',
  EXITO: 'bg-emerald-100 text-emerald-700',
  INFO: 'bg-marca-100 text-marca-700',
}

/** Campana del navbar: contador de no leidas y bandeja desplegable. */
export default function Campana() {
  const { notificaciones, noLeidas, marcarLeida, marcarTodas, consultar } = useNotificaciones()
  const [abierto, setAbierto] = useState(false)
  const contenedor = useRef(null)

  useEffect(() => {
    if (!abierto) return

    const alClicFuera = (e) => {
      if (contenedor.current && !contenedor.current.contains(e.target)) setAbierto(false)
    }
    const alEscape = (e) => e.key === 'Escape' && setAbierto(false)

    document.addEventListener('mousedown', alClicFuera)
    document.addEventListener('keydown', alEscape)
    return () => {
      document.removeEventListener('mousedown', alClicFuera)
      document.removeEventListener('keydown', alEscape)
    }
  }, [abierto])

  return (
    <div className="relative" ref={contenedor}>
      <button
        type="button"
        onClick={() => {
          setAbierto((v) => !v)
          if (!abierto) consultar({ silencioso: false })
        }}
        className="relative rounded-xl p-2 text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
        aria-label={`Notificaciones${noLeidas ? `, ${noLeidas} sin leer` : ''}`}
      >
        <Bell className={cx('h-5 w-5', noLeidas > 0 && 'animate-latido text-marca-800')} />
        {noLeidas > 0 && (
          <span className="absolute -right-0.5 -top-0.5 flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-rose-600 px-1 text-[0.65rem] font-bold text-white ring-2 ring-white">
            {noLeidas > 99 ? '99+' : noLeidas}
          </span>
        )}
      </button>

      {abierto && (
        <div className="absolute right-0 top-12 z-50 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-flotante animate-aparecer">
          <div className="flex items-center justify-between gap-2 border-b border-slate-200 bg-slate-50 px-4 py-3">
            <p className="text-sm font-semibold text-slate-800">
              Notificaciones {noLeidas > 0 && <span className="text-slate-500">({noLeidas} sin leer)</span>}
            </p>
            {noLeidas > 0 && (
              <button
                type="button"
                onClick={marcarTodas}
                className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-marca-700 transition hover:bg-marca-50"
              >
                <CheckCheck className="h-3.5 w-3.5" />
                Marcar todas
              </button>
            )}
          </div>

          <div className="max-h-[26rem] overflow-y-auto">
            {notificaciones.length === 0 ? (
              <div className="flex flex-col items-center gap-2 px-6 py-10 text-center">
                <BellOff className="h-8 w-8 text-slate-300" />
                <p className="text-sm font-medium text-slate-600">Todo en orden</p>
                <p className="text-xs text-slate-400">No tienes avisos pendientes.</p>
              </div>
            ) : (
              <ul className="divide-y divide-slate-100">
                {notificaciones.map((n) => {
                  const Icono = ICONO_TIPO[n.tipo] || Info
                  const leida = Number(n.leida) === 1
                  const Contenido = (
                    <div className={cx('flex gap-3 px-4 py-3 transition', !leida && 'bg-marca-50/40')}>
                      <span
                        className={cx(
                          'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
                          COLOR_SEVERIDAD[n.severidad] || COLOR_SEVERIDAD.INFO
                        )}
                      >
                        <Icono className="h-4 w-4" />
                      </span>
                      <div className="min-w-0 flex-1">
                        <p className={cx('text-sm leading-snug', leida ? 'text-slate-600' : 'font-semibold text-slate-900')}>
                          {n.titulo}
                        </p>
                        <p className="mt-0.5 line-clamp-2 text-xs leading-relaxed text-slate-500">{n.mensaje}</p>
                        <p className="mt-1 text-[0.7rem] text-slate-400">{tiempoRelativo(n.creado_en)}</p>
                      </div>
                      {!leida && <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-marca-700" />}
                    </div>
                  )

                  const alAbrir = () => {
                    if (!leida) marcarLeida(n.notificacion_id)
                    setAbierto(false)
                  }

                  return (
                    <li key={n.notificacion_id}>
                      {n.enlace ? (
                        <Link to={n.enlace} onClick={alAbrir} className="block hover:bg-slate-50">
                          {Contenido}
                        </Link>
                      ) : (
                        <button type="button" onClick={alAbrir} className="block w-full text-left hover:bg-slate-50">
                          {Contenido}
                        </button>
                      )}
                    </li>
                  )
                })}
              </ul>
            )}
          </div>
        </div>
      )}
    </div>
  )
}
