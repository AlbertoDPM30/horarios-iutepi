import { ChevronLeft, ChevronRight, Inbox, Loader2 } from 'lucide-react'
import Boton from './Boton'
import { cx } from '../../lib/utils'

/* ---------------------------------------------------------------- */
/* Contenedores                                                      */
/* ---------------------------------------------------------------- */

export function Tarjeta({ children, className, sinPadding = false, ...props }) {
  return (
    <div
      className={cx(
        'rounded-2xl border border-slate-200 bg-white shadow-tarjeta',
        !sinPadding && 'p-5',
        className
      )}
      {...props}
    >
      {children}
    </div>
  )
}

export function TituloSeccion({ titulo, descripcion, acciones, icono: Icono }) {
  return (
    <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div className="flex items-start gap-3">
        {Icono && (
          <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-marca-50 text-marca-800">
            <Icono className="h-5 w-5" />
          </span>
        )}
        <div className="min-w-0">
          <h1 className="font-titulo text-xl font-semibold text-slate-900 sm:text-2xl">{titulo}</h1>
          {descripcion && <p className="mt-0.5 text-sm text-slate-500">{descripcion}</p>}
        </div>
      </div>
      {acciones && <div className="flex shrink-0 flex-wrap gap-2">{acciones}</div>}
    </div>
  )
}

/* ---------------------------------------------------------------- */
/* Etiquetas                                                         */
/* ---------------------------------------------------------------- */

const TONOS = {
  neutro: 'bg-slate-100 text-slate-700 ring-slate-200',
  marca: 'bg-marca-50 text-marca-800 ring-marca-200',
  exito: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  aviso: 'bg-amber-50 text-amber-800 ring-amber-200',
  peligro: 'bg-rose-50 text-rose-700 ring-rose-200',
  info: 'bg-sky-50 text-sky-700 ring-sky-200',
  violeta: 'bg-violet-50 text-violet-700 ring-violet-200',
}

export function Etiqueta({ children, tono = 'neutro', className, icono: Icono, punto = false }) {
  return (
    <span
      className={cx(
        'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset',
        TONOS[tono],
        className
      )}
    >
      {punto && <span className="h-1.5 w-1.5 rounded-full bg-current opacity-70" />}
      {Icono && <Icono className="h-3.5 w-3.5" />}
      {children}
    </span>
  )
}

/* ---------------------------------------------------------------- */
/* Estados                                                           */
/* ---------------------------------------------------------------- */

export function Cargando({ texto = 'Cargando...', className }) {
  return (
    <div className={cx('flex flex-col items-center justify-center gap-3 py-14 text-slate-500', className)}>
      <Loader2 className="h-7 w-7 animate-spin text-marca-700" />
      <p className="text-sm">{texto}</p>
    </div>
  )
}

export function PantallaCarga({ texto = 'Preparando tu sesion...' }) {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 bg-slate-50">
      <img src="./marca/favicon-192.png" alt="IUTEPI" width={192} height={192} className="h-14 w-14 object-contain" />
      <Loader2 className="h-5 w-5 animate-spin text-marca-700" />
      <p className="text-sm text-slate-500">{texto}</p>
    </div>
  )
}

export function Esqueleto({ filas = 4, className }) {
  return (
    <div className={cx('space-y-3', className)} aria-hidden>
      {Array.from({ length: filas }).map((_, i) => (
        <div key={i} className="h-12 animate-pulse rounded-xl bg-slate-100" />
      ))}
    </div>
  )
}

export function EstadoVacio({ titulo, mensaje, icono: Icono = Inbox, accion }) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-slate-50/60 px-6 py-14 text-center">
      <span className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm">
        <Icono className="h-6 w-6" />
      </span>
      <div>
        <p className="font-semibold text-slate-700">{titulo}</p>
        {mensaje && <p className="mx-auto mt-1 max-w-md text-sm text-slate-500">{mensaje}</p>}
      </div>
      {accion}
    </div>
  )
}

/* ---------------------------------------------------------------- */
/* Tabla                                                             */
/* ---------------------------------------------------------------- */

/**
 * Tabla responsive: en pantallas chicas cada fila se vuelve una tarjeta
 * con las etiquetas de columna delante del dato.
 */
export function Tabla({ columnas, filas, claveFila, alHacerClic, vacio, cargando }) {
  if (cargando) return <Esqueleto filas={5} />
  if (!filas?.length) return vacio || <EstadoVacio titulo="Sin resultados" mensaje="No hay datos para mostrar." />

  return (
    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-tarjeta">
      {/* Escritorio */}
      <div className="hidden overflow-x-auto md:block">
        <table className="min-w-full divide-y divide-slate-200 text-sm">
          <thead className="bg-slate-50">
            <tr>
              {columnas.map((c) => (
                <th
                  key={c.clave}
                  scope="col"
                  className={cx(
                    'whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500',
                    c.alineacion === 'derecha' && 'text-right'
                  )}
                >
                  {c.titulo}
                </th>
              ))}
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {filas.map((fila, i) => (
              <tr
                key={claveFila ? fila[claveFila] : i}
                onClick={alHacerClic ? () => alHacerClic(fila) : undefined}
                className={cx('transition-colors', alHacerClic && 'cursor-pointer hover:bg-marca-50/50')}
              >
                {columnas.map((c) => (
                  <td
                    key={c.clave}
                    className={cx(
                      'px-4 py-3 align-middle text-slate-700',
                      c.alineacion === 'derecha' && 'text-right',
                      c.nowrap !== false && 'whitespace-nowrap'
                    )}
                  >
                    {c.render ? c.render(fila) : fila[c.clave]}
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {/* Movil */}
      <ul className="divide-y divide-slate-100 md:hidden">
        {filas.map((fila, i) => (
          <li
            key={claveFila ? fila[claveFila] : i}
            onClick={alHacerClic ? () => alHacerClic(fila) : undefined}
            className={cx('space-y-2 p-4', alHacerClic && 'cursor-pointer active:bg-slate-50')}
          >
            {columnas
              .filter((c) => !c.ocultarEnMovil)
              .map((c) => (
                <div key={c.clave} className="flex items-start justify-between gap-3">
                  <span className="text-xs font-medium uppercase tracking-wide text-slate-400">{c.titulo}</span>
                  <span className="min-w-0 flex-1 text-right text-sm text-slate-700">
                    {c.render ? c.render(fila) : fila[c.clave]}
                  </span>
                </div>
              ))}
          </li>
        ))}
      </ul>
    </div>
  )
}

export function Paginacion({ pagina, paginas, total, alCambiar }) {
  if (!paginas || paginas <= 1) return null

  return (
    <div className="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
      <p className="text-sm text-slate-500">
        Pagina <span className="font-semibold text-slate-700">{pagina}</span> de {paginas}
        {total !== undefined && <> · {total} registros</>}
      </p>
      <div className="flex gap-2">
        <Boton
          variante="secundario"
          tamano="sm"
          icono={ChevronLeft}
          disabled={pagina <= 1}
          onClick={() => alCambiar(pagina - 1)}
        >
          Anterior
        </Boton>
        <Boton
          variante="secundario"
          tamano="sm"
          iconoDerecha={ChevronRight}
          disabled={pagina >= paginas}
          onClick={() => alCambiar(pagina + 1)}
        >
          Siguiente
        </Boton>
      </div>
    </div>
  )
}

/** Bloque de estadistica del dashboard. */
export function Metrica({ etiqueta, valor, icono: Icono, tono = 'marca', pie }) {
  return (
    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-tarjeta">
      <div className="flex items-center justify-between gap-3">
        <p className="text-[0.7rem] font-semibold uppercase tracking-[0.08em] text-slate-500">{etiqueta}</p>
        {Icono && (
          <span className={cx('flex h-8 w-8 items-center justify-center rounded-lg', TONOS[tono])}>
            <Icono className="h-4 w-4" />
          </span>
        )}
      </div>
      <p className="cifra mt-2 text-[1.75rem] leading-none text-slate-900">{valor}</p>
      {pie && <p className="mt-0.5 text-xs text-slate-500">{pie}</p>}
    </div>
  )
}
