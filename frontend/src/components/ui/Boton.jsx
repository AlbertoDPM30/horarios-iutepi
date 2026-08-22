import { Loader2 } from 'lucide-react'
import { cx } from '../../lib/utils'

const VARIANTES = {
  primario:
    'bg-marca-700 text-white shadow-sm hover:bg-marca-800 focus-visible:outline-marca-700 disabled:bg-marca-700/50',
  secundario:
    'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 focus-visible:outline-slate-400',
  suave:
    'bg-marca-50 text-marca-800 hover:bg-marca-100 focus-visible:outline-marca-400',
  peligro:
    'bg-rose-600 text-white shadow-sm hover:bg-rose-700 focus-visible:outline-rose-600',
  exito:
    'bg-emerald-600 text-white shadow-sm hover:bg-emerald-700 focus-visible:outline-emerald-600',
  fantasma:
    'text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus-visible:outline-slate-400',
}

const TAMANOS = {
  sm: 'px-2.5 py-1.5 text-xs gap-1.5 rounded-lg',
  md: 'px-3.5 py-2.5 text-sm gap-2 rounded-xl',
  lg: 'px-5 py-3 text-base gap-2.5 rounded-xl',
}

/**
 * Boton base. Con `cargando` se bloquea solo y muestra el spinner, que
 * es la forma mas simple de evitar los dobles envios.
 */
export default function Boton({
  children,
  variante = 'primario',
  tamano = 'md',
  icono: Icono,
  iconoDerecha: IconoDerecha,
  cargando = false,
  bloque = false,
  className,
  disabled,
  ...props
}) {
  return (
    <button
      type="button"
      disabled={disabled || cargando}
      className={cx(
        'inline-flex items-center justify-center font-semibold transition-colors',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-60',
        VARIANTES[variante],
        TAMANOS[tamano],
        bloque && 'w-full',
        className
      )}
      {...props}
    >
      {cargando ? (
        <Loader2 className={cx('animate-spin', tamano === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4')} />
      ) : (
        Icono && <Icono className={cx(tamano === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4')} />
      )}
      {children}
      {IconoDerecha && !cargando && (
        <IconoDerecha className={cx(tamano === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4')} />
      )}
    </button>
  )
}

/** Boton solo-icono, para las acciones de una fila de tabla. */
export function BotonIcono({ icono: Icono, titulo, variante = 'fantasma', className, ...props }) {
  return (
    <button
      type="button"
      title={titulo}
      aria-label={titulo}
      className={cx(
        'inline-flex h-9 w-9 items-center justify-center rounded-lg transition-colors',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-50',
        VARIANTES[variante],
        className
      )}
      {...props}
    >
      <Icono className="h-4 w-4" />
    </button>
  )
}
