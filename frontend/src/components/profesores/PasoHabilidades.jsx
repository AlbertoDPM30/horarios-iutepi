import { useEffect, useMemo, useState } from 'react'
import {
  Briefcase, CircuitBoard, Code2, Database, Info, MonitorSmartphone, Network, Search, Sigma,
  Sparkles,
} from 'lucide-react'
import { cx } from '../../lib/utils'
import Boton from '../ui/Boton'
import { Campo, Estrellas } from '../ui/Campos'
import { Etiqueta } from '../ui/Datos'

const ICONOS = { Code2, Database, Network, Sigma, Briefcase, CircuitBoard, BookOpen: Sparkles, MonitorSmartphone }

/**
 * Paso 3 · Habilidades.
 *
 * Seleccion multiple por categoria; el nivel se marca con estrellas
 * (maximo 5). Cero estrellas significa que el docente no tiene esa
 * competencia y la habilidad no se guarda.
 */
export default function PasoHabilidades({ catalogo = [], valorInicial = [], alGuardar, guardando }) {
  const [niveles, setNiveles] = useState({})
  const [busqueda, setBusqueda] = useState('')
  const [categoriaAbierta, setCategoriaAbierta] = useState(null)

  useEffect(() => {
    setNiveles(Object.fromEntries((valorInicial || []).map((h) => [h.habilidad_id, Number(h.estrellas)])))
  }, [valorInicial])

  const seleccionadas = useMemo(
    () => Object.entries(niveles).filter(([, n]) => n > 0),
    [niveles]
  )

  const categoriasVisibles = useMemo(() => {
    const texto = busqueda.trim().toLowerCase()
    if (!texto) return catalogo

    return catalogo
      .map((c) => ({ ...c, habilidades: c.habilidades.filter((h) => h.nombre.toLowerCase().includes(texto)) }))
      .filter((c) => c.habilidades.length > 0)
  }, [catalogo, busqueda])

  return (
    <div className="space-y-5">
      <div className="flex gap-3 rounded-xl bg-marca-50 p-4 text-sm text-marca-900">
        <Info className="mt-0.5 h-4 w-4 shrink-0 text-marca-700" />
        <p className="leading-relaxed">
          Marca con estrellas el nivel de dominio del docente en cada competencia (1 = basico, 5 = experto).
          Con esto el sistema le propone despues las materias que puede dictar.
          <span className="mt-1 block text-xs text-marca-800">Lo que dejes en cero simplemente no se guarda.</span>
        </p>
      </div>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="w-full sm:max-w-xs">
          <Campo
            placeholder="Buscar habilidad"
            icono={Search}
            value={busqueda}
            onChange={(e) => setBusqueda(e.target.value)}
          />
        </div>
        <Etiqueta tono={seleccionadas.length ? 'exito' : 'aviso'} icono={Sparkles}>
          {seleccionadas.length} habilidad(es) seleccionadas
        </Etiqueta>
      </div>

      <div className="space-y-3">
        {categoriasVisibles.map((cat) => {
          const Icono = ICONOS[cat.icono] || Sparkles
          const enCategoria = cat.habilidades.filter((h) => (niveles[h.habilidad_id] || 0) > 0).length
          const abierta = categoriaAbierta === cat.categoria_id || Boolean(busqueda) || enCategoria > 0

          return (
            <div key={cat.categoria_id} className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
              <button
                type="button"
                onClick={() => setCategoriaAbierta(abierta && !busqueda ? null : cat.categoria_id)}
                className="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-slate-50"
              >
                <span
                  className={cx(
                    'flex h-9 w-9 items-center justify-center rounded-xl',
                    enCategoria > 0 ? 'bg-marca-100 text-marca-800' : 'bg-slate-100 text-slate-500'
                  )}
                >
                  <Icono className="h-4.5 w-4.5" style={{ width: '1.1rem', height: '1.1rem' }} />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block text-sm font-semibold text-slate-800">{cat.nombre}</span>
                  <span className="block text-xs text-slate-500">{cat.habilidades.length} habilidades</span>
                </span>
                {enCategoria > 0 && <Etiqueta tono="marca">{enCategoria}</Etiqueta>}
              </button>

              {abierta && (
                <div className="grid gap-1.5 border-t border-slate-100 bg-slate-50/60 p-3 sm:grid-cols-2">
                  {cat.habilidades.map((h) => {
                    const nivel = niveles[h.habilidad_id] || 0
                    return (
                      <div
                        key={h.habilidad_id}
                        className={cx(
                          'flex items-center justify-between gap-2 rounded-xl border bg-white px-3 py-2 transition',
                          nivel > 0 ? 'border-marca-300 shadow-sm' : 'border-slate-200'
                        )}
                      >
                        <span className="min-w-0 truncate text-sm text-slate-700">{h.nombre}</span>
                        <Estrellas
                          tamano="sm"
                          etiqueta={h.nombre}
                          valor={nivel}
                          onChange={(v) => setNiveles((prev) => ({ ...prev, [h.habilidad_id]: v }))}
                        />
                      </div>
                    )
                  })}
                </div>
              )}
            </div>
          )
        })}

        {categoriasVisibles.length === 0 && (
          <p className="rounded-xl bg-slate-50 py-8 text-center text-sm text-slate-500">
            Ninguna habilidad coincide con «{busqueda}».
          </p>
        )}
      </div>

      <div className="flex justify-end">
        <Boton
          onClick={() =>
            alGuardar(seleccionadas.map(([habilidad_id, estrellas]) => ({
              habilidad_id: Number(habilidad_id),
              estrellas,
            })))
          }
          cargando={guardando}
          disabled={seleccionadas.length === 0}
        >
          Guardar y ver materias sugeridas
        </Boton>
      </div>
    </div>
  )
}
