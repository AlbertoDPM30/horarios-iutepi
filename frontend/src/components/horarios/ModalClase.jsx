import { useState } from 'react'
import {
  BookOpen, Check, Clock, FlaskConical, Info, MapPin, Monitor, TriangleAlert, UserRound, UserX,
} from 'lucide-react'
import api from '../../lib/api'
import { useAccion, useDatos } from '../../lib/hooks'
import { useAvisos } from '../../context/AvisosContext'
import Boton from '../ui/Boton'
import Modal from '../ui/Modal'
import { Cargando, Etiqueta } from '../ui/Datos'
import { cx, DIAS_LARGOS, hora } from '../../lib/utils'

/**
 * Detalle de una clase con la accion mas usada por coordinacion:
 * cambiar de docente sin mover la materia de bloque.
 *
 * Con el periodo en curso, la API exige indicar el reemplazo en el mismo
 * momento en que se desasigna: por eso aqui no hay boton de "quitar".
 */
export default function ModalClase({ clase, periodo, alCerrar, alActualizar }) {
  const { avisar } = useAvisos()
  const [seleccionado, setSeleccionado] = useState(null)

  const { datos: candidatos, cargando } = useDatos(
    `/asignaciones/${clase.asignacion_id}/candidatos`,
    null,
    { ttl: 0 }
  )

  const enCurso = periodo?.estado === 'EN_CURSO'
  const finalizado = periodo?.estado === 'FINALIZADO'

  const { ejecutar: cambiar, enviando } = useAccion(
    async (profesorId) => api.patch(`/asignaciones/${clase.asignacion_id}/docente`, { profesor_id: profesorId }),
    {
      alTerminar: () => { avisar.exito('Docente actualizado. La materia se queda en el mismo bloque.'); alActualizar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const { ejecutar: virtualizar, enviando: virtualizando } = useAccion(
    async (modalidad) => api.patch(`/asignaciones/${clase.asignacion_id}/modalidad`, { modalidad }),
    {
      alTerminar: () => { avisar.exito('Modalidad de la clase actualizada.'); alActualizar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const esVirtual = clase.modalidad_clase === 'VIRTUAL'

  return (
    <Modal
      abierto
      alCerrar={alCerrar}
      ancho="lg"
      titulo={clase.materia}
      descripcion={`${clase.materia_codigo} · Seccion ${clase.seccion} · ${clase.modulo}° modulo`}
      pie={<Boton variante="secundario" onClick={alCerrar}>Cerrar</Boton>}
    >
      <div className="space-y-5">
        {/* ---- Ficha ---- */}
        <dl className="grid gap-3 sm:grid-cols-2">
          <Dato icono={Clock} etiqueta="Bloque">
            {DIAS_LARGOS[clase.dia]} · {hora(clase.hora_inicio)} a {hora(clase.hora_fin)}
          </Dato>
          <Dato icono={clase.espacio_tipo === 'LABORATORIO' ? FlaskConical : MapPin} etiqueta="Espacio">
            {esVirtual ? 'Clase virtual' : clase.espacio || 'Sin asignar'}
          </Dato>
          <Dato icono={UserRound} etiqueta="Docente">
            {clase.profesor ? (
              <>
                {clase.profesor}
                {clase.profesor_telefono && (
                  <span className="block text-xs text-slate-500">{clase.profesor_telefono}</span>
                )}
              </>
            ) : (
              <span className="font-semibold text-rose-700">Sin docente asignado</span>
            )}
          </Dato>
          <Dato icono={BookOpen} etiqueta="Carrera">
            {clase.carrera} · {clase.semestre}° semestre
          </Dato>
        </dl>

        {finalizado ? (
          <div className="flex gap-3 rounded-xl bg-slate-100 p-3.5 text-sm text-slate-600">
            <Info className="mt-0.5 h-4 w-4 shrink-0" />
            <p>El periodo ya finalizo: este horario es solo de consulta.</p>
          </div>
        ) : (
          <>
            {enCurso && (
              <div className="flex gap-3 rounded-xl bg-amber-50 p-3.5 text-sm text-amber-900">
                <TriangleAlert className="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                <p className="leading-relaxed">
                  El periodo esta en curso. Puedes cambiar de docente, pero la materia se queda en el mismo
                  bloque horario para no alterar los horarios ya publicados.
                </p>
              </div>
            )}

            {/* ---- Cambio de docente ---- */}
            <section>
              <h3 className="mb-2 text-sm font-semibold text-slate-800">Cambiar el docente</h3>

              {cargando ? (
                <Cargando texto="Buscando docentes disponibles..." />
              ) : !candidatos?.length ? (
                <p className="rounded-xl bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                  No hay otros docentes habilitados para esta materia.
                </p>
              ) : (
                <div className="max-h-64 space-y-2 overflow-y-auto pr-1">
                  {candidatos.map((c) => {
                    const activo = seleccionado === c.profesor_id
                    return (
                      <button
                        key={c.profesor_id}
                        type="button"
                        disabled={!c.libre}
                        onClick={() => setSeleccionado(c.profesor_id)}
                        className={cx(
                          'flex w-full items-center gap-3 rounded-xl border p-3 text-left transition',
                          activo
                            ? 'border-marca-500 bg-marca-50 shadow-sm'
                            : c.libre
                              ? 'border-slate-200 bg-white hover:border-slate-300'
                              : 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-60'
                        )}
                      >
                        <span
                          className={cx(
                            'flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2',
                            activo ? 'border-marca-700 bg-marca-700 text-white' : 'border-slate-300'
                          )}
                        >
                          {activo && <Check className="h-3 w-3" />}
                        </span>

                        <span className="min-w-0 flex-1">
                          <span className="block text-sm font-semibold text-slate-800">{c.profesor}</span>
                          <span className="block text-xs text-slate-500">
                            {c.libre ? `Libre en ese bloque · ${c.carga_actual} bloques asignados` : c.motivo}
                          </span>
                        </span>

                        <span className="shrink-0 text-right">
                          <span className="block text-sm font-bold text-marca-700">{Math.round(c.afinidad)}%</span>
                          <span className="block text-[0.65rem] text-slate-400">afinidad</span>
                        </span>
                      </button>
                    )
                  })}
                </div>
              )}

              {seleccionado && (
                <Boton className="mt-3" onClick={() => cambiar(seleccionado)} cargando={enviando} icono={Check}>
                  Asignar este docente
                </Boton>
              )}
            </section>

            {/* ---- Modalidad ---- */}
            <section className="border-t border-slate-200 pt-4">
              <h3 className="mb-2 text-sm font-semibold text-slate-800">Modalidad de la clase</h3>
              <div className="flex flex-wrap items-center gap-2">
                <Etiqueta tono={esVirtual ? 'violeta' : 'exito'} icono={esVirtual ? Monitor : MapPin}>
                  {esVirtual ? 'Virtual' : 'Presencial'}
                </Etiqueta>
                <Boton
                  variante="secundario"
                  tamano="sm"
                  cargando={virtualizando}
                  onClick={() => virtualizar(esVirtual ? 'PRESENCIAL' : 'VIRTUAL')}
                  icono={esVirtual ? MapPin : Monitor}
                >
                  {esVirtual ? 'Volver a presencial' : 'Pasar a virtual'}
                </Boton>
              </div>
              <p className="mt-2 text-xs text-slate-500">
                Al pasar una clase a virtual se libera el aula, util cuando dos materias chocan en el mismo bloque.
              </p>
            </section>
          </>
        )}
      </div>
    </Modal>
  )
}

function Dato({ icono: Icono, etiqueta, children }) {
  return (
    <div className="rounded-xl bg-slate-50 p-3">
      <dt className="flex items-center gap-1.5 text-xs font-medium uppercase tracking-wide text-slate-400">
        <Icono className="h-3.5 w-3.5" />
        {etiqueta}
      </dt>
      <dd className="mt-1 text-sm font-medium text-slate-800">{children}</dd>
    </div>
  )
}
