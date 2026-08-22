import { useState } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import {
  ArrowLeft, CalendarClock, CalendarDays, CircleCheck, Hourglass, LayoutGrid, Lock, Pencil,
  PlayCircle, Sparkles, TriangleAlert, Trash2, UserRound, Users, Wand2,
} from 'lucide-react'
import api from '../lib/api'
import { useAuth } from '../context/AuthContext'
import { useAccion, useDatos } from '../lib/hooks'
import { useAvisos } from '../context/AvisosContext'
import Boton from '../components/ui/Boton'
import { Interruptor } from '../components/ui/Campos'
import Modal, { Confirmar } from '../components/ui/Modal'
import { Cargando, EstadoVacio, Etiqueta, Metrica, Tarjeta } from '../components/ui/Datos'
import ModalPeriodo from '../components/ModalPeriodo'
import { cx, diasTexto, ESTADOS_PERIODO, fechaLarga } from '../lib/utils'

/**
 * Detalle de un periodo: su estado, lo que se puede hacer segun ese
 * estado y el boton que dispara la generacion automatica de horarios.
 */
export default function Periodo() {
  const { id } = useParams()
  const navegar = useNavigate()
  const { esAdmin } = useAuth()
  const { avisar } = useAvisos()

  const { datos: periodo, cargando, recargar } = useDatos(`/periodos/${id}`, null, { ttl: 10_000 })
  const { datos: resumen } = useDatos(`/periodos/${id}/resumen`, null, { ttl: 15_000 })

  const [editar, setEditar] = useState(false)
  const [borrar, setBorrar] = useState(false)
  const [generar, setGenerar] = useState(false)
  const [resultado, setResultado] = useState(null)

  const { ejecutar: eliminar, enviando: eliminando } = useAccion(
    async () => api.del(`/periodos/${id}`),
    {
      alTerminar: (r) => { avisar.exito(r.datos.mensaje); navegar('/') },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const { ejecutar: iniciar, enviando: iniciando } = useAccion(
    async () => api.post(`/periodos/${id}/estado`, { estado: 'EN_CURSO' }),
    {
      alTerminar: () => { avisar.exito('El periodo esta en curso. Los horarios quedaron cerrados.'); recargar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const { ejecutar: finalizar, enviando: finalizando } = useAccion(
    async () => api.post(`/periodos/${id}/estado`, { estado: 'FINALIZADO' }),
    {
      alTerminar: () => { avisar.exito('Periodo finalizado.'); recargar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  if (cargando && !periodo) return <Cargando texto="Cargando periodo..." />
  if (!periodo) return <EstadoVacio titulo="Periodo no encontrado" mensaje="Puede que lo hayan eliminado." />

  const estado = ESTADOS_PERIODO[periodo.estado]
  const permisos = periodo.permisos ?? {}
  const cobertura = resumen?.cobertura ?? {}
  const secciones = resumen?.secciones ?? []
  const conflictos = resumen?.conflictos ?? []
  const totalConflictos = conflictos.reduce((n, c) => n + Number(c.total), 0)

  return (
    <div>
      <button
        type="button"
        onClick={() => navegar('/')}
        className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-slate-800"
      >
        <ArrowLeft className="h-4 w-4" />
        Volver al panel
      </button>

      {/* ---- Cabecera ---- */}
      <div className={cx('mb-6 rounded-2xl border p-5 shadow-tarjeta', estado.tarjeta)}>
        <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2">
              <h1 className="font-titulo text-2xl font-semibold text-slate-900">{periodo.codigo}</h1>
              <Etiqueta
                tono={periodo.estado === 'EN_CURSO' ? 'exito' : periodo.estado === 'PLANIFICACION' ? 'aviso' : 'neutro'}
                punto
              >
                {estado.etiqueta}
              </Etiqueta>
              <Etiqueta tono="neutro">
                {periodo.modalidad === 'SABATINO' ? 'Sabatino' : 'Entre semana'}
              </Etiqueta>
            </div>

            <p className="mt-1 text-slate-600">{periodo.nombre}</p>
            <p className="mt-2 text-sm text-slate-500">
              Del <strong className="text-slate-700">{fechaLarga(periodo.fecha_inicio)}</strong> al{' '}
              <strong className="text-slate-700">{fechaLarga(periodo.fecha_fin)}</strong> · {periodo.semanas} semanas
            </p>

            {periodo.estado === 'PLANIFICACION' && Number(periodo.dias_para_iniciar) >= 0 && (
              <p className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-amber-100 px-3 py-1.5 text-sm font-semibold text-amber-900">
                <Hourglass className="h-4 w-4" />
                Proximo a iniciar {diasTexto(periodo.dias_para_iniciar)}
              </p>
            )}
            {periodo.estado === 'EN_CURSO' && (
              <p className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-emerald-100 px-3 py-1.5 text-sm font-semibold text-emerald-900">
                <CalendarClock className="h-4 w-4" />
                Termina {diasTexto(periodo.dias_para_terminar)}
              </p>
            )}
            {periodo.estado === 'FINALIZADO' && (
              <p className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-slate-200 px-3 py-1.5 text-sm font-semibold text-slate-700">
                <Lock className="h-4 w-4" />
                Solo consulta: no se puede modificar nada
              </p>
            )}
          </div>

          {esAdmin && (
            <div className="flex flex-wrap gap-2">
              {permisos.generar_horarios && (
                <Boton icono={Wand2} onClick={() => setGenerar(true)}>Generar horarios</Boton>
              )}
              {periodo.estado === 'PLANIFICACION' && Number(periodo.total_bloques ?? 0) >= 0 && (
                <Boton variante="exito" icono={PlayCircle} cargando={iniciando} onClick={() => iniciar()}>
                  Iniciar periodo
                </Boton>
              )}
              {periodo.estado === 'EN_CURSO' && (
                <Boton variante="secundario" icono={CircleCheck} cargando={finalizando} onClick={() => finalizar()}>
                  Finalizar
                </Boton>
              )}
              {permisos.editar_datos && (
                <Boton variante="secundario" icono={Pencil} onClick={() => setEditar(true)}>Editar</Boton>
              )}
              {permisos.eliminar && (
                <Boton variante="secundario" icono={Trash2} onClick={() => setBorrar(true)}>Eliminar</Boton>
              )}
            </div>
          )}
        </div>
      </div>

      {/* ---- Modulos ---- */}
      <div className="mb-5 grid gap-3 sm:grid-cols-2">
        {(periodo.modulos ?? []).map((m) => (
          <Tarjeta key={m.modulo_id} className="flex items-center gap-3">
            <span className="flex h-11 w-11 shrink-0 items-center justify-center cifra rounded-xl bg-marca-50 text-lg text-marca-700">
              {m.numero}
            </span>
            <div className="min-w-0">
              <p className="text-sm font-semibold text-slate-800">{m.numero}° modulo</p>
              <p className="text-sm text-slate-500">
                {fechaLarga(m.fecha_inicio)} — {fechaLarga(m.fecha_fin)}
              </p>
              <p className="text-xs text-slate-400">{m.semanas} semanas</p>
            </div>
          </Tarjeta>
        ))}
      </div>

      {/* ---- Metricas ---- */}
      <div className="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <Metrica etiqueta="Secciones" valor={periodo.total_secciones ?? secciones.length} icono={LayoutGrid} />
        <Metrica etiqueta="Estudiantes" valor={periodo.total_estudiantes ?? 0} icono={UserRound} tono="info" />
        <Metrica
          etiqueta="Materias ofertadas"
          valor={cobertura.asignaciones ?? 0}
          icono={Sparkles}
          tono="violeta"
          pie={Number(cobertura.sin_docente) > 0 ? `${cobertura.sin_docente} sin docente` : 'todas con docente'}
        />
        <Metrica
          etiqueta="Conflictos"
          valor={totalConflictos}
          icono={TriangleAlert}
          tono={totalConflictos > 0 ? 'peligro' : 'exito'}
          pie={totalConflictos > 0 ? 'requieren tu decision' : 'sin pendientes'}
        />
      </div>

      {totalConflictos > 0 && esAdmin && (
        <Link
          to={`/conflictos?periodo=${id}`}
          className="mb-5 flex items-center gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 transition hover:bg-rose-100"
        >
          <TriangleAlert className="h-5 w-5 shrink-0 text-rose-600" />
          <div className="min-w-0 flex-1">
            <p className="text-sm font-semibold text-rose-900">
              {totalConflictos} conflicto(s) esperando tu decision
            </p>
            <p className="text-sm text-rose-800">
              {conflictos.map((c) => `${c.total} ${c.tipo.toLowerCase().replace(/_/g, ' ')}`).join(', ')}
            </p>
          </div>
          <span className="shrink-0 text-sm font-semibold text-rose-900">Resolver →</span>
        </Link>
      )}

      {/* ---- Secciones ---- */}
      <div className="mb-3 flex items-center justify-between">
        <h2 className="font-titulo text-lg font-semibold text-slate-900">Secciones del periodo</h2>
        {esAdmin && (
          <Link to={`/secciones?periodo=${id}`} className="enlace text-sm">Administrar secciones</Link>
        )}
      </div>

      {secciones.length === 0 ? (
        <EstadoVacio
          icono={LayoutGrid}
          titulo="Este periodo no tiene secciones"
          mensaje="Sin secciones no se pueden generar horarios."
          accion={
            esAdmin && permisos.editar_estructura ? (
              <Link to={`/secciones?periodo=${id}`}>
                <Boton>Crear secciones</Boton>
              </Link>
            ) : undefined
          }
        />
      ) : (
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {secciones.map((s) => (
            <Link
              key={s.seccion_id}
              to={`/horarios?periodo=${id}&seccion=${s.seccion_id}`}
              className="group rounded-2xl border border-slate-200 bg-white p-4 shadow-tarjeta transition hover:-translate-y-0.5 hover:shadow-flotante"
            >
              <div className="flex items-start justify-between gap-2">
                <span
                  className="rounded-lg px-2 py-1 text-xs font-bold text-white"
                  style={{ backgroundColor: s.carrera_color }}
                >
                  {s.codigo}
                </span>
                <Etiqueta tono={Number(s.materias_asignadas) > 0 ? 'exito' : 'aviso'}>
                  {s.materias_asignadas} materias
                </Etiqueta>
              </div>

              <p className="mt-2 text-sm font-semibold text-slate-800">{s.carrera}</p>
              <p className="text-xs text-slate-500">{s.semestre}° semestre · aula {s.espacio || 'sin asignar'}</p>

              <p className="mt-2 flex items-center gap-1.5 text-xs text-slate-500">
                <Users className="h-3.5 w-3.5" />
                {s.inscritos} de {s.cupo} inscritos
              </p>

              <span className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-marca-800">
                <CalendarDays className="h-4 w-4" />
                Ver horario
              </span>
            </Link>
          ))}
        </div>
      )}

      {/* ---- Modales ---- */}
      {editar && (
        <ModalPeriodo
          abierto
          periodo={periodo}
          alCerrar={() => setEditar(false)}
          alGuardar={() => { setEditar(false); recargar() }}
        />
      )}

      {generar && (
        <ModalGenerar
          periodoId={id}
          alCerrar={() => setGenerar(false)}
          alTerminar={(r) => { setGenerar(false); setResultado(r); recargar() }}
        />
      )}

      {resultado && (
        <ModalResultado resultado={resultado} periodoId={id} alCerrar={() => setResultado(null)} />
      )}

      <Confirmar
        abierto={borrar}
        alCerrar={() => setBorrar(false)}
        alConfirmar={() => eliminar()}
        cargando={eliminando}
        titulo={`¿Eliminar el periodo ${periodo.codigo}?`}
        mensaje="Se borraran sus secciones, asignaciones y horarios. Esta accion no se puede deshacer."
      />
    </div>
  )
}

/* ------------------------------------------------------------------ */

function ModalGenerar({ periodoId, alCerrar, alTerminar }) {
  const { avisar } = useAvisos()
  const [limpiar, setLimpiar] = useState(true)
  const [reasignar, setReasignar] = useState(true)

  const { ejecutar, enviando } = useAccion(
    async () =>
      api.post(`/periodos/${periodoId}/generar-horarios`, {
        limpiar: limpiar ? 1 : 0,
        reasignar_docentes: reasignar ? 1 : 0,
      }),
    {
      alTerminar: (r) => alTerminar(r.datos),
      alFallar: (e) => avisar.error(e.message),
    }
  )

  return (
    <Modal
      abierto
      alCerrar={alCerrar}
      titulo="Generar horarios"
      descripcion="El sistema asignara docentes y ubicara cada materia respetando disponibilidad, aulas y laboratorios."
      pie={
        <>
          <Boton variante="secundario" onClick={alCerrar} disabled={enviando}>Cancelar</Boton>
          <Boton icono={Wand2} onClick={() => ejecutar()} cargando={enviando}>
            {enviando ? 'Generando...' : 'Generar ahora'}
          </Boton>
        </>
      }
    >
      <div className="space-y-3">
        <Interruptor
          etiqueta="Borrar el horario anterior"
          descripcion="Recomendado. Si lo desmarcas, se conserva lo ya ubicado y solo se completa lo que falta."
          checked={limpiar}
          onChange={setLimpiar}
        />
        <Interruptor
          etiqueta="Reasignar docentes automaticamente"
          descripcion="Si lo desmarcas, se respetan los docentes que ya elegiste a mano."
          checked={reasignar}
          onChange={setReasignar}
        />

        <p className="rounded-xl bg-slate-50 p-3.5 text-sm leading-relaxed text-slate-600">
          El proceso tarda unos segundos. Lo que el sistema no pueda resolver quedara listado como conflicto
          para que tu decidas que hacer.
        </p>
      </div>
    </Modal>
  )
}

function ModalResultado({ resultado, periodoId, alCerrar }) {
  const r = resultado.resumen ?? {}
  const conflictos = resultado.conflictos ?? []

  return (
    <Modal
      abierto
      alCerrar={alCerrar}
      ancho="lg"
      titulo={conflictos.length === 0 ? 'Horarios generados sin conflictos' : 'Horarios generados con observaciones'}
      pie={
        <>
          <Boton variante="secundario" onClick={alCerrar}>Cerrar</Boton>
          {conflictos.length > 0 && (
            <Link to={`/conflictos?periodo=${periodoId}`}>
              <Boton icono={TriangleAlert}>Ver conflictos</Boton>
            </Link>
          )}
        </>
      }
    >
      <div className="space-y-4">
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <Metrica etiqueta="Secciones" valor={r.secciones ?? 0} />
          <Metrica etiqueta="Materias" valor={r.asignaciones ?? 0} />
          <Metrica etiqueta="Sesiones ubicadas" valor={r.ubicadas ?? 0} tono="exito" />
          <Metrica
            etiqueta="Sin ubicar"
            valor={r.sin_ubicar ?? 0}
            tono={Number(r.sin_ubicar) > 0 ? 'peligro' : 'exito'}
          />
        </div>

        <p className="text-sm text-slate-500">
          Procesado en {r.segundos ?? 0} segundos.
          {Number(r.sin_docente) > 0 && ` ${r.sin_docente} materia(s) quedaron sin docente.`}
        </p>

        {conflictos.length > 0 && (
          <div>
            <p className="mb-2 text-sm font-semibold text-slate-800">Observaciones</p>
            <ul className="max-h-56 space-y-1.5 overflow-y-auto pr-1">
              {conflictos.map((c, i) => (
                <li key={i} className="rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">
                  <span className="font-medium">{c.titulo}</span>
                  <span className="mt-0.5 block text-xs leading-snug text-amber-800">{c.descripcion}</span>
                </li>
              ))}
            </ul>
          </div>
        )}
      </div>
    </Modal>
  )
}
