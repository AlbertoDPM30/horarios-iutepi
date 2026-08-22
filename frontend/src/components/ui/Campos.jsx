import { useId } from 'react'
import { AlertCircle, Star } from 'lucide-react'
import { cx } from '../../lib/utils'

const BASE_CONTROL =
  'block w-full rounded-xl border-0 bg-white px-3.5 py-2.5 text-slate-900 shadow-sm ' +
  'ring-1 ring-inset ring-slate-300 placeholder:text-slate-400 transition ' +
  'focus:ring-2 focus:ring-inset focus:ring-marca-700 disabled:bg-slate-50 disabled:text-slate-500'

function Envoltura({ id, etiqueta, ayuda, error, requerido, children, className }) {
  return (
    <div className={cx('w-full', className)}>
      {etiqueta && (
        <label htmlFor={id} className="mb-1.5 block text-sm font-medium text-slate-700">
          {etiqueta}
          {requerido && <span className="ml-0.5 text-rose-600">*</span>}
        </label>
      )}
      {children}
      {error ? (
        <p className="mt-1.5 flex items-center gap-1 text-xs font-medium text-rose-600">
          <AlertCircle className="h-3.5 w-3.5 shrink-0" />
          {error}
        </p>
      ) : (
        ayuda && <p className="mt-1.5 text-xs text-slate-500">{ayuda}</p>
      )}
    </div>
  )
}

export function Campo({ etiqueta, ayuda, error, requerido, icono: Icono, className, ...props }) {
  const id = useId()

  return (
    <Envoltura id={id} etiqueta={etiqueta} ayuda={ayuda} error={error} requerido={requerido} className={className}>
      <div className="relative">
        {Icono && (
          <Icono className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        )}
        <input
          id={id}
          className={cx(BASE_CONTROL, Icono && 'pl-10', error && 'ring-rose-400 focus:ring-rose-500')}
          aria-invalid={Boolean(error)}
          {...props}
        />
      </div>
    </Envoltura>
  )
}

export function Select({ etiqueta, ayuda, error, requerido, children, className, ...props }) {
  const id = useId()

  return (
    <Envoltura id={id} etiqueta={etiqueta} ayuda={ayuda} error={error} requerido={requerido} className={className}>
      <select
        id={id}
        className={cx(BASE_CONTROL, 'pr-9', error && 'ring-rose-400 focus:ring-rose-500')}
        aria-invalid={Boolean(error)}
        {...props}
      >
        {children}
      </select>
    </Envoltura>
  )
}

export function AreaTexto({ etiqueta, ayuda, error, requerido, className, ...props }) {
  const id = useId()

  return (
    <Envoltura id={id} etiqueta={etiqueta} ayuda={ayuda} error={error} requerido={requerido} className={className}>
      <textarea
        id={id}
        rows={3}
        className={cx(BASE_CONTROL, 'resize-y', error && 'ring-rose-400 focus:ring-rose-500')}
        {...props}
      />
    </Envoltura>
  )
}

export function Interruptor({ etiqueta, descripcion, checked, onChange, disabled }) {
  return (
    <label
      className={cx(
        'flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 transition',
        checked ? 'border-marca-300 bg-marca-50/60' : 'hover:bg-slate-50',
        disabled && 'cursor-not-allowed opacity-60'
      )}
    >
      <input
        type="checkbox"
        checked={Boolean(checked)}
        onChange={(e) => onChange?.(e.target.checked)}
        disabled={disabled}
        className="mt-0.5 h-4.5 w-4.5 rounded border-slate-300 text-marca-700 focus:ring-marca-700"
        style={{ width: '1.05rem', height: '1.05rem' }}
      />
      <span className="min-w-0">
        <span className="block text-sm font-medium text-slate-800">{etiqueta}</span>
        {descripcion && <span className="block text-xs text-slate-500">{descripcion}</span>}
      </span>
    </label>
  )
}

/**
 * Selector de estrellas (1 a 5). Se usa para el nivel de dominio de cada
 * habilidad del docente; con 0 estrellas la habilidad no esta elegida.
 */
export function Estrellas({ valor = 0, onChange, max = 5, tamano = 'md', soloLectura = false, etiqueta }) {
  const dimension = { sm: 'h-4 w-4', md: 'h-5 w-5', lg: 'h-6 w-6' }[tamano]

  return (
    <div className="inline-flex items-center gap-0.5" role="group" aria-label={etiqueta || 'Nivel'}>
      {Array.from({ length: max }, (_, i) => i + 1).map((n) => (
        <button
          key={n}
          type="button"
          disabled={soloLectura}
          onClick={() => onChange?.(valor === n ? 0 : n)}
          title={soloLectura ? undefined : `${n} de ${max}`}
          aria-label={`${n} de ${max}`}
          className={cx(
            'rounded transition',
            !soloLectura && 'hover:scale-110 focus-visible:outline focus-visible:outline-2 focus-visible:outline-marca-500',
            soloLectura && 'cursor-default'
          )}
        >
          <Star
            className={cx(
              dimension,
              n <= valor ? 'fill-amber-400 text-amber-400' : 'text-slate-300'
            )}
          />
        </button>
      ))}
    </div>
  )
}

/** Grupo de opciones tipo tarjeta, mas facil de tocar que un radio. */
export function OpcionesTarjeta({ opciones, valor, onChange, columnas = 3 }) {
  return (
    <div
      className={cx(
        'grid gap-3',
        columnas === 2 ? 'sm:grid-cols-2' : columnas === 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-3'
      )}
    >
      {opciones.map((op) => {
        const activo = valor === op.valor
        const Icono = op.icono
        return (
          <button
            key={op.valor}
            type="button"
            onClick={() => onChange(op.valor)}
            className={cx(
              'flex flex-col items-start gap-1.5 rounded-2xl border-2 p-4 text-left transition',
              activo
                ? 'border-marca-700 bg-marca-50 shadow-sm'
                : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
            )}
          >
            {Icono && (
              <Icono className={cx('h-6 w-6', activo ? 'text-marca-700' : 'text-slate-400')} />
            )}
            <span className={cx('text-sm font-semibold', activo ? 'text-marca-900' : 'text-slate-800')}>
              {op.etiqueta}
            </span>
            {op.descripcion && <span className="text-xs text-slate-500">{op.descripcion}</span>}
          </button>
        )
      })}
    </div>
  )
}
