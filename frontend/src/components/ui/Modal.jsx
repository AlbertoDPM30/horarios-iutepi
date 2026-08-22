import { useEffect, useRef } from 'react'
import { createPortal } from 'react-dom'
import { AlertTriangle, X } from 'lucide-react'
import Boton from './Boton'
import { cx } from '../../lib/utils'

const ANCHOS = {
  sm: 'sm:max-w-md',
  md: 'sm:max-w-lg',
  lg: 'sm:max-w-2xl',
  xl: 'sm:max-w-4xl',
  full: 'sm:max-w-6xl',
}

/**
 * Ventana modal. En movil sube desde abajo (hoja) y en escritorio se
 * centra; es el patron que menos confunde a quien no usa mucho la PC.
 */
export default function Modal({
  abierto,
  alCerrar,
  titulo,
  descripcion,
  ancho = 'md',
  children,
  pie,
  cerrarConFondo = true,
}) {
  const contenedor = useRef(null)

  useEffect(() => {
    if (!abierto) return

    const alTeclear = (e) => {
      if (e.key === 'Escape') alCerrar?.()
    }
    document.addEventListener('keydown', alTeclear)

    const overflowPrevio = document.body.style.overflow
    document.body.style.overflow = 'hidden'

    contenedor.current?.focus()

    return () => {
      document.removeEventListener('keydown', alTeclear)
      document.body.style.overflow = overflowPrevio
    }
  }, [abierto, alCerrar])

  if (!abierto) return null

  return createPortal(
    <div className="fixed inset-0 z-50 flex items-end justify-center sm:items-center" role="dialog" aria-modal="true">
      <div
        className="absolute inset-0 bg-slate-900/40 backdrop-blur-[2px]"
        onClick={() => cerrarConFondo && alCerrar?.()}
      />

      <div
        ref={contenedor}
        tabIndex={-1}
        className={cx(
          'relative flex max-h-[92vh] w-full flex-col overflow-hidden bg-white shadow-flotante outline-none',
          'rounded-t-2xl sm:rounded-2xl animate-aparecer',
          ANCHOS[ancho]
        )}
      >
        <div className="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
          <div className="min-w-0">
            <h2 className="font-titulo text-lg font-semibold text-slate-900">{titulo}</h2>
            {descripcion && <p className="mt-0.5 text-sm text-slate-500">{descripcion}</p>}
          </div>
          <button
            type="button"
            onClick={alCerrar}
            className="-mr-1 rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-slate-700"
            aria-label="Cerrar"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="flex-1 overflow-y-auto px-5 py-4">{children}</div>

        {pie && (
          <div className="flex flex-col-reverse gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3.5 sm:flex-row sm:justify-end">
            {pie}
          </div>
        )}
      </div>
    </div>,
    document.body
  )
}

/** Confirmacion para acciones que no se pueden deshacer. */
export function Confirmar({
  abierto,
  alCerrar,
  alConfirmar,
  titulo = '¿Confirmas la accion?',
  mensaje,
  textoConfirmar = 'Si, continuar',
  variante = 'peligro',
  cargando = false,
}) {
  return (
    <Modal
      abierto={abierto}
      alCerrar={alCerrar}
      titulo={titulo}
      ancho="sm"
      pie={
        <>
          <Boton variante="secundario" onClick={alCerrar} disabled={cargando}>
            Cancelar
          </Boton>
          <Boton variante={variante} onClick={alConfirmar} cargando={cargando}>
            {textoConfirmar}
          </Boton>
        </>
      }
    >
      <div className="flex gap-3">
        <div
          className={cx(
            'flex h-10 w-10 shrink-0 items-center justify-center rounded-full',
            variante === 'peligro' ? 'bg-rose-100' : 'bg-amber-100'
          )}
        >
          <AlertTriangle
            className={cx('h-5 w-5', variante === 'peligro' ? 'text-rose-600' : 'text-amber-600')}
          />
        </div>
        <p className="pt-1.5 text-sm leading-relaxed text-slate-600">{mensaje}</p>
      </div>
    </Modal>
  )
}
