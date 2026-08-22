import { useEffect, useState } from 'react'
import { Link, NavLink, Outlet, useLocation } from 'react-router-dom'
import {
  Bookmark, BookOpen, Briefcase, CalendarCheck, CalendarDays, CalendarRange, ChevronDown,
  DoorOpen, FlaskConical, GraduationCap, LayoutGrid, LogOut, Menu, Sparkles, TriangleAlert,
  UserRound, Users, X,
} from 'lucide-react'
import { useAuth } from '../../context/AuthContext'
import { useDatos } from '../../lib/hooks'
import { cx, iniciales, ROLES } from '../../lib/utils'
import Campana from './Campana'
import Logo from './Logo'

const ICONOS = {
  CalendarRange, GraduationCap, BookOpen, Sparkles, Users, UserRound,
  DoorOpen, FlaskConical, LayoutGrid, CalendarDays, TriangleAlert, CalendarCheck,
  Bookmark, Briefcase,
}

const ICONO_ROL = {
  ADMIN: Briefcase,
  DOCENTE: GraduationCap,
  ESTUDIANTE: UserRound,
}

/**
 * Armazon de la aplicacion: barra lateral con los modulos, barra
 * superior con el usuario y la campana, y el contenido de la ruta.
 *
 * Los modulos vienen del backend con una bandera `vacio`: los que aun no
 * tienen datos se pintan en amarillo con un aviso al pasar el cursor,
 * para que el administrador sepa por donde empezar.
 */
export default function Layout() {
  const { usuario, rol, salir } = useAuth()
  const ubicacion = useLocation()
  const [menuAbierto, setMenuAbierto] = useState(false)
  const [perfilAbierto, setPerfilAbierto] = useState(false)

  const { datos: dashboard } = useDatos('/dashboard', null, { ttl: 60_000 })
  const modulos = dashboard?.modulos ?? []

  // Cerrar el menu al navegar (en movil se queda abierto si no)
  useEffect(() => {
    setMenuAbierto(false)
    setPerfilAbierto(false)
  }, [ubicacion.pathname])

  const IconoRol = ICONO_ROL[rol] || UserRound

  const navegacion = (
    <nav className="flex-1 space-y-1 overflow-y-auto px-3 py-4">
      {modulos.map((modulo) => {
        const Icono = ICONOS[modulo.icono] || Bookmark
        const vacio = modulo.vacio && rol === 'ADMIN'

        return (
          <NavLink
            key={modulo.clave}
            to={modulo.ruta}
            title={vacio ? `${modulo.nombre}: aun no tiene datos cargados` : modulo.descripcion}
            className={({ isActive }) =>
              cx(
                'group relative flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition',
                isActive
                  ? 'bg-marca-700 text-white shadow-sm'
                  : vacio
                    ? 'bg-amber-50 text-amber-900 ring-1 ring-inset ring-amber-200 hover:bg-amber-100'
                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900'
              )
            }
          >
            {({ isActive }) => (
              <>
                <Icono className={cx('h-[1.15rem] w-[1.15rem] shrink-0', !isActive && vacio && 'text-amber-600')} />
                <span className="truncate">{modulo.nombre}</span>
                {vacio && (
                  <span className="ml-auto flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-amber-400 text-[0.65rem] font-bold text-amber-950">
                    !
                  </span>
                )}
                {!vacio && modulo.clave === 'conflictos' && modulo.registros > 0 && !isActive && (
                  <span className="ml-auto rounded-full bg-rose-100 px-1.5 py-0.5 text-[0.65rem] font-bold text-rose-700">
                    {modulo.registros}
                  </span>
                )}

                {/* Aviso al posar el cursor sobre un modulo vacio */}
                {vacio && (
                  <span className="pointer-events-none absolute left-full top-1/2 z-50 ml-3 hidden w-56 -translate-y-1/2 rounded-xl bg-slate-900 px-3 py-2 text-xs font-normal leading-snug text-white shadow-flotante group-hover:lg:block">
                    Este modulo todavia no tiene datos. Cargalos para poder generar horarios.
                  </span>
                )}
              </>
            )}
          </NavLink>
        )
      })}
    </nav>
  )

  return (
    <div className="min-h-screen bg-slate-50">
      {/* ---- Barra lateral (escritorio) ---- */}
      <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r border-slate-200 bg-white lg:flex">
        <Link to="/" className="flex flex-col gap-2 border-b border-slate-200 px-5 py-4">
          <Logo />
          <span className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-[0.14em] text-slate-500">
            <CalendarDays className="h-3.5 w-3.5 text-marca-700" />
            Horarios academicos
          </span>
        </Link>

        {navegacion}

        <div className="border-t border-slate-200 p-3">
          <button
            type="button"
            onClick={salir}
            className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-rose-50 hover:text-rose-700"
          >
            <LogOut className="h-[1.15rem] w-[1.15rem]" />
            Cerrar sesion
          </button>
        </div>
      </aside>

      {/* ---- Menu lateral (movil) ---- */}
      {menuAbierto && (
        <div className="fixed inset-0 z-40 lg:hidden">
          <div className="absolute inset-0 bg-slate-900/40" onClick={() => setMenuAbierto(false)} />
          <aside className="relative flex h-full w-72 max-w-[85vw] flex-col bg-white shadow-flotante animate-aparecer">
            <div className="flex items-center justify-between border-b border-slate-200 px-4 py-4">
              <Logo />
              <button
                type="button"
                onClick={() => setMenuAbierto(false)}
                className="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100"
                aria-label="Cerrar menu"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            {navegacion}

            <div className="border-t border-slate-200 p-3">
              <button
                type="button"
                onClick={salir}
                className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-slate-600 transition hover:bg-rose-50 hover:text-rose-700"
              >
                <LogOut className="h-[1.15rem] w-[1.15rem]" />
                Cerrar sesion
              </button>
            </div>
          </aside>
        </div>
      )}

      {/* ---- Contenido ---- */}
      <div className="lg:pl-64">
        <header className="sticky top-0 z-20 border-b border-slate-200 bg-white/85 backdrop-blur">
          <div className="flex h-16 items-center gap-3 px-4 sm:px-6">
            <button
              type="button"
              onClick={() => setMenuAbierto(true)}
              className="rounded-xl p-2 text-slate-600 transition hover:bg-slate-100 lg:hidden"
              aria-label="Abrir menu"
            >
              <Menu className="h-5 w-5" />
            </button>

            <Link to="/" className="lg:hidden">
              <Logo />
            </Link>

            <div className="ml-auto flex items-center gap-1.5">
              <Campana />

              <div className="relative">
                <button
                  type="button"
                  onClick={() => setPerfilAbierto((v) => !v)}
                  className="flex items-center gap-2.5 rounded-xl py-1.5 pl-1.5 pr-2 transition hover:bg-slate-100"
                >
                  <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-marca-700 text-sm font-semibold text-white">
                    {iniciales(usuario?.nombre_completo) || <IconoRol className="h-4 w-4" />}
                  </span>
                  <span className="hidden text-left sm:block">
                    <span className="block max-w-[11rem] truncate text-sm font-semibold leading-tight text-slate-800">
                      {usuario?.nombre_completo}
                    </span>
                    <span className="flex items-center gap-1 text-xs text-slate-500">
                      <IconoRol className="h-3 w-3" />
                      {ROLES[rol]?.etiqueta}
                    </span>
                  </span>
                  <ChevronDown className="hidden h-4 w-4 text-slate-400 sm:block" />
                </button>

                {perfilAbierto && (
                  <>
                    <div className="fixed inset-0 z-40" onClick={() => setPerfilAbierto(false)} />
                    <div className="absolute right-0 top-12 z-50 w-64 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-flotante animate-aparecer">
                      <div className="border-b border-slate-100 bg-slate-50 px-4 py-3">
                        <p className="truncate text-sm font-semibold text-slate-900">{usuario?.nombre_completo}</p>
                        <p className="mt-0.5 flex items-center gap-1.5 text-xs text-slate-500">
                          <IconoRol className="h-3.5 w-3.5" />
                          {ROLES[rol]?.etiqueta}
                        </p>
                        {usuario?.codigo && (
                          <p className="mt-1 text-xs text-slate-500">
                            Codigo <span className="font-medium text-slate-700">{usuario.codigo}</span>
                            {usuario.seccion && <> · Seccion <span className="font-medium text-slate-700">{usuario.seccion}</span></>}
                          </p>
                        )}
                        {usuario?.cedula && !usuario?.codigo && (
                          <p className="mt-1 text-xs text-slate-500">
                            Cedula <span className="font-medium text-slate-700">{usuario.cedula}</span>
                          </p>
                        )}
                      </div>
                      <button
                        type="button"
                        onClick={salir}
                        className="flex w-full items-center gap-2.5 px-4 py-3 text-sm font-medium text-slate-700 transition hover:bg-rose-50 hover:text-rose-700"
                      >
                        <LogOut className="h-4 w-4" />
                        Cerrar sesion
                      </button>
                    </div>
                  </>
                )}
              </div>
            </div>
          </div>
        </header>

        <main className="mx-auto w-full max-w-7xl px-4 py-6 sm:px-6 lg:py-8">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
