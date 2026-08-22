import { useEffect, useMemo, useState } from 'react'
import { Check, FlaskConical, Info, Search, Sparkles, TriangleAlert, Wand2 } from 'lucide-react'
import { cx } from '../../lib/utils'
import Boton from '../ui/Boton'
import { Campo } from '../ui/Campos'
import { EstadoVacio, Etiqueta } from '../ui/Datos'

/**
 * Paso 4 · Asignacion de materias.
 *
 * El sistema propone (segun la afinidad calculada desde las skills) y el
 * administrador revisa: puede quitar sugerencias o agregar materias que
 * el docente no "cumple" del todo, bajo su criterio.
 */
export default function PasoMaterias({ sugerencias = [], confirmadas = [], alGuardar, guardando }) {
  const [elegidas, setElegidas] = useState(new Set())
  const [busqueda, setBusqueda] = useState('')
  const [soloAptas, setSoloAptas] = useState(true)

  useEffect(() => {
    const yaConfirmadas = confirmadas.map((m) => Number(m.materia_id))
    if (yaConfirmadas.length) {
      setElegidas(new Set(yaConfirmadas))
    } else {
      setElegidas(new Set(sugerencias.filter((s) => s.sugerida).map((s) => Number(s.materia_id))))
    }
  }, [sugerencias, confirmadas])

  const visibles = useMemo(() => {
    const texto = busqueda.trim().toLowerCase()
    return sugerencias.filter((s) => {
      if (soloAptas && !s.cumple && !elegidas.has(Number(s.materia_id))) return false
      if (!texto) return true
      return s.nombre.toLowerCase().includes(texto) || s.codigo.toLowerCase().includes(texto)
    })
  }, [sugerencias, busqueda, soloAptas, elegidas])

  function alternar(materiaId) {
    setElegidas((prev) => {
      const copia = new Set(prev)
      if (copia.has(materiaId)) copia.delete(materiaId)
      else copia.add(materiaId)
      return copia
    })
  }

  const recomendadas = sugerencias.filter((s) => s.sugerida)

  return (
    <div className="space-y-5">
      <div className="flex gap-3 rounded-xl bg-marca-50 p-4 text-sm text-marca-900">
        <Wand2 className="mt-0.5 h-4 w-4 shrink-0 text-marca-700" />
        <p className="leading-relaxed">
          Estas son las materias que el docente puede dictar segun sus habilidades. Ya vienen preseleccionadas
          las de mayor afinidad; puedes ajustar la seleccion antes de guardar.
          <span className="mt-1 block text-xs text-marca-800">
            Las marcadas en amarillo no cumplen algun minimo exigido: puedes asignarlas igual bajo tu criterio.
          </span>
        </p>
      </div>

      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="w-full sm:max-w-xs">
          <Campo placeholder="Buscar materia" icono={Search} value={busqueda} onChange={(e) => setBusqueda(e.target.value)} />
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => setSoloAptas((v) => !v)}
            className={cx(
              'rounded-lg px-3 py-1.5 text-xs font-semibold ring-1 ring-inset transition',
              soloAptas
                ? 'bg-marca-50 text-marca-800 ring-marca-200'
                : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50'
            )}
          >
            {soloAptas ? 'Mostrando solo las aptas' : 'Mostrando todas'}
          </button>
          <Etiqueta tono={elegidas.size ? 'exito' : 'aviso'}>{elegidas.size} seleccionadas</Etiqueta>
          <Etiqueta tono="neutro" icono={Sparkles}>{recomendadas.length} recomendadas</Etiqueta>
        </div>
      </div>

      {visibles.length === 0 ? (
        <EstadoVacio
          icono={TriangleAlert}
          titulo="No hay materias que encajen con sus habilidades"
          mensaje="Vuelve al paso anterior y agrega mas competencias, o revisa el perfil que exigen las materias."
        />
      ) : (
        <div className="grid gap-2 sm:grid-cols-2">
          {visibles.map((m) => {
            const id = Number(m.materia_id)
            const activa = elegidas.has(id)

            return (
              <button
                key={id}
                type="button"
                onClick={() => alternar(id)}
                className={cx(
                  'flex items-start gap-3 rounded-2xl border p-3.5 text-left transition',
                  activa
                    ? 'border-marca-500 bg-marca-50 shadow-sm'
                    : m.cumple
                      ? 'border-slate-200 bg-white hover:border-slate-300'
                      : 'border-amber-200 bg-amber-50/40 hover:border-amber-300'
                )}
              >
                <span
                  className={cx(
                    'mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 transition',
                    activa ? 'border-marca-700 bg-marca-700 text-white' : 'border-slate-300 bg-white'
                  )}
                >
                  {activa && <Check className="h-3 w-3" />}
                </span>

                <span className="min-w-0 flex-1">
                  <span className="flex flex-wrap items-center gap-1.5">
                    <span className="font-mono text-[0.7rem] font-semibold text-slate-500">{m.codigo}</span>
                    <span
                      className="inline-flex items-center rounded-full px-1.5 py-0.5 text-[0.65rem] font-semibold text-white"
                      style={{ backgroundColor: m.carrera_color }}
                    >
                      {m.carrera_codigo}
                    </span>
                    {Number(m.requiere_laboratorio) === 1 && (
                      <FlaskConical className="h-3.5 w-3.5 text-sky-600" title="Se dicta en laboratorio" />
                    )}
                  </span>

                  <span className="mt-0.5 block text-sm font-semibold text-slate-800">{m.nombre}</span>
                  <span className="text-xs text-slate-500">{m.semestre}° semestre · {m.unidades_credito} UC</span>

                  {!m.cumple && m.faltantes?.length > 0 && (
                    <span className="mt-1.5 block text-[0.7rem] leading-snug text-amber-800">
                      Le falta nivel en: {m.faltantes.map((f) => `${f.habilidad} (${f.tiene}/${f.requerido})`).join(', ')}
                    </span>
                  )}
                </span>

                <span className="shrink-0 text-right">
                  <span
                    className={cx(
                      'block text-sm font-bold',
                      m.afinidad >= 80 ? 'text-emerald-600' : m.afinidad >= 60 ? 'text-marca-700' : 'text-amber-600'
                    )}
                  >
                    {Math.round(m.afinidad)}%
                  </span>
                  <span className="block text-[0.65rem] text-slate-400">afinidad</span>
                </span>
              </button>
            )
          })}
        </div>
      )}

      <div className="flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
        <p className="flex items-center gap-2 text-sm text-slate-500">
          <Info className="h-4 w-4 shrink-0" />
          Puedes volver a este paso cuando quieras para ajustar la lista.
        </p>
        <Boton onClick={() => alGuardar([...elegidas])} cargando={guardando} disabled={elegidas.size === 0}>
          Confirmar {elegidas.size} materia(s)
        </Boton>
      </div>
    </div>
  )
}
