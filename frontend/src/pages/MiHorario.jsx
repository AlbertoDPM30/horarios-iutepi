import { useEffect, useMemo, useState } from 'react'
import {
  BookOpen, CalendarCheck, CalendarDays, Check, CircleCheck, Info, Lock, Monitor, Plus,
  Printer, TriangleAlert, Trash2, Wand2,
} from 'lucide-react'
import api from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useAccion, useDatos } from '../lib/hooks'
import { useAvisos } from '../context/AvisosContext'
import Boton, { BotonIcono } from '../components/ui/Boton'
import { Select } from '../components/ui/Campos'
import Modal, { Confirmar } from '../components/ui/Modal'
import { Cargando, EstadoVacio, Etiqueta, Tarjeta, TituloSeccion } from '../components/ui/Datos'
import RejillaHorario, { SelectorModulo } from '../components/horarios/RejillaHorario'
import { cx, DIAS_LARGOS, fechaLarga, hora } from '../lib/utils'

/**
 * Pantalla del estudiante: arma, revisa y confirma su horario.
 *
 * Cuando dos materias caen en el mismo bloque (pasa seguido con los
 * repitientes) el sistema no bloquea: ofrece cursar una de las dos en
 * modalidad virtual, que es la salida que usa el instituto.
 */
export default function MiHorario() {
  const { usuario, esEstudiante } = useAuth()
  const { avisar } = useAvisos()

  const estudianteId = usuario?.estudiante_id
  const [periodoId, setPeriodoId] = useState('')
  const [modulo, setModulo] = useState(1)
  const [choque, setChoque] = useState(null)
  const [quitar, setQuitar] = useState(null)
  const [ofertaAbierta, setOfertaAbierta] = useState(false)

  const { datos: panel } = useDatos(esEstudiante ? '/dashboard' : null, null, { ttl: 60_000 })
  const inscripciones = panel?.inscripciones ?? []

  useEffect(() => {
    if (!periodoId && inscripciones.length) {
      const activa = inscripciones.find((i) => i.periodo_estado !== 'FINALIZADO') || inscripciones[0]
      setPeriodoId(String(activa.periodo_id))
    }
  }, [inscripciones, periodoId])

  const { datos: horario, cargando, recargar } = useDatos(
    estudianteId && periodoId ? `/estudiantes/${estudianteId}/horario` : null,
    { periodo_id: periodoId },
    { ttl: 0 }
  )

  const { datos: oferta, recargar: recargarOferta } = useDatos(
    estudianteId && periodoId && ofertaAbierta ? `/estudiantes/${estudianteId}/oferta` : null,
    { periodo_id: periodoId },
    { ttl: 0 }
  )

  const { datos: rejilla } = useDatos(
    periodoId ? '/bloques' : null,
    { modalidad: horario?.periodo?.modalidad },
    { ttl: 300_000 }
  )

  const editable = Boolean(horario?.editable)
  const periodo = horario?.periodo

  /* ---- Acciones ---- */

  const { ejecutar: agregar, enviando: agregando } = useAccion(
    async ({ asignacionId, virtual = false }) =>
      api.post(`/estudiantes/${estudianteId}/horario`, {
        periodo_id: Number(periodoId),
        asignacion_id: asignacionId,
        virtual,
      }),
    {
      alTerminar: (r) => {
        avisar.exito(
          r.datos?.modalidad_cursada === 'VIRTUAL'
            ? 'Materia agregada en modalidad virtual.'
            : 'Materia agregada a tu horario.'
        )
        setChoque(null)
        recargar()
        recargarOferta()
      },
      alFallar: (e) => {
        if (e.esConflicto && e.detalles?.requiere_decision) {
          setChoque({ ...e.detalles, mensaje: e.message })
        } else {
          avisar.error(e.message)
        }
      },
    }
  )

  const { ejecutar: eliminar, enviando: eliminando } = useAccion(
    async (asignacionId) =>
      api.del(`/estudiantes/${estudianteId}/horario/${asignacionId}`, null, { periodo_id: periodoId }),
    {
      alTerminar: () => { avisar.exito('Materia retirada.'); setQuitar(null); recargar(); recargarOferta() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const { ejecutar: generar, enviando: generando } = useAccion(
    async () => api.post(`/estudiantes/${estudianteId}/horario/generar`, { periodo_id: Number(periodoId) }),
    {
      alTerminar: (r) => {
        avisar.exito(`Se agregaron ${r.datos.agregadas} materias a tu horario.`)
        recargar()
        recargarOferta()
      },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const { ejecutar: confirmar, enviando: confirmando } = useAccion(
    async () => api.post(`/estudiantes/${estudianteId}/horario/confirmar`, { periodo_id: Number(periodoId) }),
    {
      alTerminar: () => { avisar.exito('Horario confirmado. Ya no podras modificarlo.'); recargar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  if (!esEstudiante) {
    return (
      <EstadoVacio
        icono={CalendarDays}
        titulo="Esta seccion es para estudiantes"
        mensaje="Si eres docente o administrador, consulta el modulo de Horarios."
      />
    )
  }

  if (!inscripciones.length) {
    return (
      <EstadoVacio
        icono={CalendarDays}
        titulo="Aun no estas inscrito en ningun periodo"
        mensaje="Acercate a control de estudios para formalizar tu inscripcion."
      />
    )
  }

  const clasesModulo = (horario?.bloques ?? []).filter((b) => Number(b.modulo) === Number(modulo))
  const materias = horario?.materias ?? []

  return (
    <div>
      <TituloSeccion
        icono={CalendarCheck}
        titulo="Mi horario"
        descripcion={
          periodo
            ? `${periodo.codigo} · del ${fechaLarga(periodo.fecha_inicio)} al ${fechaLarga(periodo.fecha_fin)}`
            : 'Arma tu horario del periodo.'
        }
        acciones={
          <Boton variante="secundario" icono={Printer} onClick={() => window.print()} className="no-imprimir">
            Imprimir
          </Boton>
        }
      />

      {/* ---- Selector de periodo ---- */}
      <div className="no-imprimir mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <Select
          etiqueta="Periodo"
          className="sm:max-w-xs"
          value={periodoId}
          onChange={(e) => { setPeriodoId(e.target.value); setModulo(1) }}
        >
          {inscripciones.map((i) => (
            <option key={i.periodo_id} value={i.periodo_id}>
              {i.periodo} — seccion {i.seccion}
            </option>
          ))}
        </Select>

        <SelectorModulo
          modulos={(periodo?.modulos ?? []).length ? periodo.modulos : [{ numero: 1 }, { numero: 2 }]}
          valor={modulo}
          alCambiar={setModulo}
        />
      </div>

      {/* ---- Estado del horario ---- */}
      {horario && (
        <div
          className={cx(
            'no-imprimir mb-5 flex flex-col gap-3 rounded-2xl border p-4 sm:flex-row sm:items-center sm:justify-between',
            editable
              ? 'border-marca-200 bg-marca-50'
              : Number(horario.inscripcion.horario_confirmado) === 1
                ? 'border-emerald-200 bg-emerald-50'
                : 'border-slate-200 bg-slate-50'
          )}
        >
          <div className="flex items-start gap-3">
            {editable ? (
              <Info className="mt-0.5 h-5 w-5 shrink-0 text-marca-700" />
            ) : (
              <Lock className="mt-0.5 h-5 w-5 shrink-0 text-slate-500" />
            )}
            <div>
              <p className="text-sm font-semibold text-slate-900">
                {editable
                  ? 'Puedes modificar tu horario'
                  : Number(horario.inscripcion.horario_confirmado) === 1
                    ? 'Tu horario esta confirmado'
                    : 'El periodo ya comenzo: tu horario quedo cerrado'}
              </p>
              <p className="text-sm text-slate-600">
                {editable
                  ? 'Agrega o quita materias y confirma cuando estes seguro. Despues de confirmar no se puede cambiar.'
                  : 'Si necesitas un cambio, comunicalo a control de estudios.'}
              </p>
            </div>
          </div>

          {editable && (
            <div className="flex shrink-0 flex-wrap gap-2">
              <Boton variante="secundario" icono={Wand2} onClick={() => generar()} cargando={generando}>
                Armar automaticamente
              </Boton>
              <Boton icono={Plus} onClick={() => setOfertaAbierta(true)}>Agregar materia</Boton>
            </div>
          )}
        </div>
      )}

      {cargando && !horario ? (
        <Cargando texto="Cargando tu horario..." />
      ) : (
        <div className="grid gap-5 lg:grid-cols-[1fr_20rem]">
          {/* ---- Rejilla ---- */}
          <div className="min-w-0">
            <RejillaHorario
              bloques={rejilla?.bloques ?? []}
              dias={rejilla?.dias?.[periodo?.modalidad] ?? []}
              clases={clasesModulo}
              mostrarSeccion={false}
              vacioTitulo="Tu horario esta vacio"
              vacioMensaje={
                editable
                  ? 'Usa "Armar automaticamente" para cargar las materias de tu semestre, o agregalas una por una.'
                  : 'No tienes materias registradas en este periodo.'
              }
            />
          </div>

          {/* ---- Materias elegidas ---- */}
          <aside className="space-y-3">
            <Tarjeta sinPadding>
              <div className="border-b border-slate-100 px-4 py-3">
                <h2 className="flex items-center gap-2 font-titulo text-base font-semibold text-slate-900">
                  <BookOpen className="h-4 w-4 text-slate-400" />
                  Mis materias
                </h2>
                <p className="text-xs text-slate-500">{materias.length} inscritas en total</p>
              </div>

              {materias.length === 0 ? (
                <p className="px-4 py-8 text-center text-sm text-slate-500">Aun no has elegido materias.</p>
              ) : (
                <ul className="divide-y divide-slate-100">
                  {materias.map((m) => (
                    <li key={m.estudiante_horario_id} className="flex items-start gap-2 px-4 py-3">
                      <div className="min-w-0 flex-1">
                        <p className="text-sm font-medium text-slate-800">{m.materia}</p>
                        <p className="mt-0.5 text-xs text-slate-500">
                          {m.materia_codigo} · {m.modulo}° modulo · {m.unidades_credito} UC
                        </p>
                        <p className="mt-0.5 text-xs text-slate-500">{m.profesor}</p>
                        {m.modalidad_cursada === 'VIRTUAL' && (
                          <Etiqueta tono="violeta" icono={Monitor} className="mt-1.5">Virtual</Etiqueta>
                        )}
                      </div>
                      {editable && (
                        <BotonIcono
                          icono={Trash2}
                          titulo="Quitar del horario"
                          className="hover:bg-rose-50 hover:text-rose-700"
                          onClick={() => setQuitar(m)}
                        />
                      )}
                    </li>
                  ))}
                </ul>
              )}
            </Tarjeta>

            {editable && materias.length > 0 && (
              <Boton
                variante="exito"
                bloque
                icono={CircleCheck}
                cargando={confirmando}
                onClick={() => confirmar()}
              >
                Confirmar mi horario
              </Boton>
            )}
          </aside>
        </div>
      )}

      {/* ---- Oferta ---- */}
      {ofertaAbierta && (
        <ModalOferta
          oferta={oferta?.oferta ?? []}
          alCerrar={() => setOfertaAbierta(false)}
          alAgregar={(asignacionId) => agregar({ asignacionId })}
          agregando={agregando}
        />
      )}

      {/* ---- Choque de horario ---- */}
      <Modal
        abierto={Boolean(choque)}
        alCerrar={() => setChoque(null)}
        titulo="Esa materia choca con otra"
        ancho="md"
        pie={
          <>
            <Boton variante="secundario" onClick={() => setChoque(null)}>Mejor no</Boton>
            <Boton
              icono={Monitor}
              cargando={agregando}
              onClick={() => agregar({ asignacionId: choque.asignacion_id, virtual: true })}
            >
              Cursarla virtual
            </Boton>
          </>
        }
      >
        {choque && (
          <div className="space-y-3">
            <div className="flex gap-3 rounded-xl bg-amber-50 p-3.5">
              <TriangleAlert className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
              <p className="text-sm leading-relaxed text-amber-900">{choque.mensaje}</p>
            </div>

            <div>
              <p className="mb-1.5 text-sm font-semibold text-slate-800">Se cruza con:</p>
              <ul className="space-y-1.5">
                {(choque.choques || []).map((c) => (
                  <li key={c.asignacion_id} className="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700">
                    <span className="font-medium">{c.materia}</span>
                    <span className="block text-xs text-slate-500">
                      {DIAS_LARGOS[c.dia]} · {c.etiqueta} · {c.modulo}° modulo
                    </span>
                  </li>
                ))}
              </ul>
            </div>

            <p className="text-sm leading-relaxed text-slate-600">
              Puedes cursar <strong>{choque.materia}</strong> en modalidad virtual y conservar las dos materias.
              La clase presencial seguira en su bloque para el resto de la seccion.
            </p>
          </div>
        )}
      </Modal>

      <Confirmar
        abierto={Boolean(quitar)}
        alCerrar={() => setQuitar(null)}
        alConfirmar={() => eliminar(quitar.asignacion_id)}
        cargando={eliminando}
        titulo={`¿Quitar ${quitar?.materia}?`}
        mensaje="La materia saldra de tu horario. Puedes volver a agregarla mientras el periodo no haya comenzado."
        textoConfirmar="Si, quitarla"
      />
    </div>
  )
}

/* ------------------------------------------------------------------ */

function ModalOferta({ oferta, alCerrar, alAgregar, agregando }) {
  const [modulo, setModulo] = useState('')

  const visibles = useMemo(
    () => oferta.filter((o) => !modulo || String(o.modulo) === String(modulo)),
    [oferta, modulo]
  )

  return (
    <Modal
      abierto
      alCerrar={alCerrar}
      ancho="xl"
      titulo="Materias disponibles"
      descripcion="Estas son las materias que puedes cursar este periodo."
      pie={<Boton variante="secundario" onClick={alCerrar}>Cerrar</Boton>}
    >
      <div className="space-y-4">
        <Select etiqueta="Modulo" className="sm:max-w-xs" value={modulo} onChange={(e) => setModulo(e.target.value)}>
          <option value="">Todos los modulos</option>
          <option value="1">1er modulo</option>
          <option value="2">2do modulo</option>
        </Select>

        {visibles.length === 0 ? (
          <EstadoVacio titulo="No hay materias disponibles" mensaje="Consulta con control de estudios." />
        ) : (
          <div className="grid gap-2 sm:grid-cols-2">
            {visibles.map((o) => (
              <div
                key={o.asignacion_id}
                className={cx(
                  'flex items-start gap-3 rounded-2xl border p-3.5 transition',
                  o.elegida ? 'border-emerald-300 bg-emerald-50/60' : 'border-slate-200 bg-white'
                )}
              >
                <div className="min-w-0 flex-1">
                  <p className="flex flex-wrap items-center gap-1.5">
                    <span className="font-mono text-[0.7rem] font-semibold text-slate-500">{o.materia_codigo}</span>
                    <span
                      className="inline-flex items-center rounded-full px-1.5 py-0.5 text-[0.65rem] font-semibold text-white"
                      style={{ backgroundColor: o.carrera_color }}
                    >
                      {o.carrera_codigo}
                    </span>
                    <Etiqueta tono="neutro">{o.modulo}° modulo</Etiqueta>
                  </p>
                  <p className="mt-1 text-sm font-semibold text-slate-800">{o.materia}</p>
                  <p className="text-xs text-slate-500">
                    {o.semestre}° semestre · {o.unidades_credito} UC · {o.profesor || 'sin docente'}
                  </p>
                  {o.bloques_detalle?.length > 0 && (
                    <p className="mt-1 text-xs text-slate-500">
                      {[...new Set(o.bloques_detalle.map((b) => `${DIAS_LARGOS[b.dia]} ${b.etiqueta.split(' - ')[0]}`))]
                        .slice(0, 3)
                        .join(' · ')}
                    </p>
                  )}
                  {o.choques?.length > 0 && !o.elegida && (
                    <p className="mt-1.5 text-xs font-medium text-amber-700">
                      Choca con {o.choques.map((c) => c.materia).join(', ')}
                    </p>
                  )}
                </div>

                {o.elegida ? (
                  <Etiqueta tono="exito" icono={Check}>Elegida</Etiqueta>
                ) : (
                  <Boton
                    tamano="sm"
                    variante={o.choques?.length ? 'secundario' : 'primario'}
                    icono={Plus}
                    disabled={agregando || Number(o.bloques) === 0}
                    onClick={() => alAgregar(Number(o.asignacion_id))}
                  >
                    Agregar
                  </Boton>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </Modal>
  )
}
