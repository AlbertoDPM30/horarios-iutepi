import { useEffect, useMemo, useRef, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import {
  ArrowLeft, BookOpen, CalendarDays, Check, CircleCheck, Clock, IdCard, Sparkles, UserPlus,
} from 'lucide-react'
import api from '../lib/api'
import { useAccion, useDatos } from '../lib/hooks'
import { useAvisos } from '../context/AvisosContext'
import Boton from '../components/ui/Boton'
import { Campo, Select } from '../components/ui/Campos'
import { Cargando, EstadoVacio, Etiqueta, Tarjeta } from '../components/ui/Datos'
import RejillaHorario, { SelectorModulo } from '../components/horarios/RejillaHorario'
import PasoDisponibilidad from '../components/profesores/PasoDisponibilidad'
import PasoHabilidades from '../components/profesores/PasoHabilidades'
import PasoMaterias from '../components/profesores/PasoMaterias'
import { cx } from '../lib/utils'

const PASOS = [
  { numero: 1, titulo: 'Informacion general', icono: IdCard },
  { numero: 2, titulo: 'Disponibilidad', icono: Clock },
  { numero: 3, titulo: 'Habilidades', icono: Sparkles },
  { numero: 4, titulo: 'Materias', icono: BookOpen },
  { numero: 5, titulo: 'Horario', icono: CalendarDays },
]

/**
 * Alta y edicion de docentes en 5 pasos.
 *
 * Cada paso guarda en la base al terminar y salta al siguiente; volver
 * atras abre el paso en modo edicion con lo que ya se guardo. Asi nadie
 * pierde el trabajo si se cae la conexion a mitad del formulario.
 */
export default function ProfesorFormulario() {
  const { id } = useParams()
  const navegar = useNavigate()
  const { avisar } = useAvisos()

  const [profesorId, setProfesorId] = useState(id ? Number(id) : null)
  const [paso, setPaso] = useState(1)

  const { datos: profesor, cargando, recargar } = useDatos(
    profesorId ? `/profesores/${profesorId}` : null,
    null,
    { ttl: 0 }
  )
  const { datos: catalogos } = useDatos('/catalogos', null, { ttl: 300_000 })

  // Al abrir un docente existente se arranca en el paso donde quedo,
  // pero solo la primera vez: despues manda la navegacion del asistente.
  const pasoInicializado = useRef(false)

  useEffect(() => {
    if (pasoInicializado.current || !profesor || !id) return
    pasoInicializado.current = true
    setPaso(Math.min(5, Math.max(1, Number(profesor.paso_registro) || 1)))
  }, [profesor, id])

  const pasoMaximo = profesor ? Math.min(5, Number(profesor.paso_registro) + 1) : 1

  if (profesorId && cargando && !profesor) return <Cargando texto="Cargando datos del docente..." />

  return (
    <div>
      <button
        type="button"
        onClick={() => navegar('/profesores')}
        className="mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-slate-800"
      >
        <ArrowLeft className="h-4 w-4" />
        Volver a docentes
      </button>

      <div className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="font-titulo text-xl font-semibold text-slate-900 sm:text-2xl">
            {profesor ? `${profesor.nombres} ${profesor.apellidos}` : 'Nuevo docente'}
          </h1>
          <p className="mt-0.5 text-sm text-slate-500">
            {profesor
              ? `${profesor.cedula} · ${profesor.titulo || 'sin titulo registrado'}`
              : 'El formulario guarda cada paso al terminarlo.'}
          </p>
        </div>
        {profesor && Number(profesor.paso_registro) >= 5 && (
          <Etiqueta tono="exito" icono={CircleCheck}>Registro completo</Etiqueta>
        )}
      </div>

      {/* ---- Indicador de pasos ---- */}
      <ol className="mb-6 flex gap-1 overflow-x-auto pb-1">
        {PASOS.map((p) => {
          const completo = profesor && Number(profesor.paso_registro) >= p.numero
          const habilitado = p.numero <= pasoMaximo
          const activo = paso === p.numero
          const Icono = p.icono

          return (
            <li key={p.numero} className="min-w-0 flex-1">
              <button
                type="button"
                disabled={!habilitado}
                onClick={() => habilitado && setPaso(p.numero)}
                className={cx(
                  'group flex w-full flex-col items-start gap-1.5 rounded-xl border-2 p-2.5 text-left transition',
                  activo
                    ? 'border-marca-700 bg-marca-50'
                    : completo
                      ? 'border-emerald-200 bg-emerald-50/60 hover:border-emerald-300'
                      : habilitado
                        ? 'border-slate-200 bg-white hover:border-slate-300'
                        : 'cursor-not-allowed border-dashed border-slate-200 bg-slate-50 opacity-60'
                )}
              >
                <span className="flex items-center gap-1.5">
                  <span
                    className={cx(
                      'flex h-6 w-6 shrink-0 items-center justify-center rounded-lg text-xs font-bold',
                      activo ? 'bg-marca-700 text-white' : completo ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-500'
                    )}
                  >
                    {completo && !activo ? <Check className="h-3.5 w-3.5" /> : p.numero}
                  </span>
                  <Icono className={cx('hidden h-4 w-4 sm:block', activo ? 'text-marca-700' : 'text-slate-400')} />
                </span>
                <span
                  className={cx(
                    'truncate text-[0.7rem] font-semibold leading-tight sm:text-xs',
                    activo ? 'text-marca-900' : completo ? 'text-emerald-800' : 'text-slate-500'
                  )}
                >
                  {p.titulo}
                </span>
              </button>
            </li>
          )
        })}
      </ol>

      <Tarjeta>
        {paso === 1 && (
          <PasoDatos
            profesor={profesor}
            alGuardar={(nuevoId) => {
              pasoInicializado.current = true
              setProfesorId(nuevoId)
              if (!id) navegar(`/profesores/${nuevoId}`, { replace: true })
              recargar()
              setPaso(2)
            }}
          />
        )}

        {paso === 2 && profesorId && (
          <PasoDisponibilidadConexion profesorId={profesorId} profesor={profesor} alTerminar={() => { recargar(); setPaso(3) }} />
        )}

        {paso === 3 && profesorId && (
          <PasoHabilidadesConexion
            profesorId={profesorId}
            profesor={profesor}
            catalogo={catalogos?.habilidades ?? []}
            alTerminar={() => { recargar(); setPaso(4) }}
          />
        )}

        {paso === 4 && profesorId && (
          <PasoMateriasConexion profesorId={profesorId} alTerminar={() => { recargar(); setPaso(5) }} />
        )}

        {paso === 5 && profesorId && (
          <PasoHorario profesorId={profesorId} profesor={profesor} alFinalizar={() => { recargar(); avisar.exito('Registro del docente completado.') }} />
        )}
      </Tarjeta>
    </div>
  )
}

/* ================================================================== */
/* Paso 1 · Datos generales                                            */
/* ================================================================== */

function PasoDatos({ profesor, alGuardar }) {
  const { avisar } = useAvisos()
  const editando = Boolean(profesor?.profesor_id)

  const [forma, setForma] = useState({
    cedula: '', nombres: '', apellidos: '', telefono: '', correo: '', titulo: '',
    tipo_contrato: 'POR_HORAS', max_bloques_semana: 12,
  })

  useEffect(() => {
    if (profesor) {
      setForma({
        cedula: profesor.cedula || '',
        nombres: profesor.nombres || '',
        apellidos: profesor.apellidos || '',
        telefono: profesor.telefono || '',
        correo: profesor.correo || '',
        titulo: profesor.titulo || '',
        tipo_contrato: profesor.tipo_contrato || 'POR_HORAS',
        max_bloques_semana: profesor.max_bloques_semana || 12,
      })
    }
  }, [profesor])

  const { ejecutar, enviando, errores } = useAccion(
    async () => {
      const cuerpo = {
        cedula: forma.cedula.trim().toUpperCase(),
        nombres: forma.nombres.trim(),
        apellidos: forma.apellidos.trim(),
        telefono: forma.telefono?.trim() || undefined,
        correo: forma.correo?.trim() || undefined,
        titulo: forma.titulo?.trim() || undefined,
        tipo_contrato: forma.tipo_contrato,
        max_bloques_semana: Number(forma.max_bloques_semana),
      }

      return editando
        ? api.put(`/profesores/${profesor.profesor_id}`, cuerpo)
        : api.post('/profesores', cuerpo)
    },
    {
      alTerminar: (r) => {
        avisar.exito(editando ? 'Datos actualizados.' : 'Docente creado. Ahora carga su disponibilidad.')
        alGuardar(editando ? profesor.profesor_id : r.datos.profesor.profesor_id)
      },
      alFallar: (e) => !e.esValidacion && avisar.error(e.message),
    }
  )

  return (
    <div className="space-y-5">
      <div>
        <h2 className="font-titulo text-lg font-semibold text-slate-900">Informacion general</h2>
        <p className="text-sm text-slate-500">
          La cedula sera la credencial con la que el docente entra al sistema.
        </p>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <Campo
          etiqueta="Cedula" requerido placeholder="V-12345678"
          value={forma.cedula} error={errores.cedula}
          onChange={(e) => setForma((f) => ({ ...f, cedula: e.target.value.toUpperCase() }))}
        />
        <Campo
          etiqueta="Titulo academico" placeholder="Ing. en Informatica"
          value={forma.titulo} error={errores.titulo}
          onChange={(e) => setForma((f) => ({ ...f, titulo: e.target.value }))}
        />
        <Campo
          etiqueta="Nombres" requerido value={forma.nombres} error={errores.nombres}
          onChange={(e) => setForma((f) => ({ ...f, nombres: e.target.value }))}
        />
        <Campo
          etiqueta="Apellidos" requerido value={forma.apellidos} error={errores.apellidos}
          onChange={(e) => setForma((f) => ({ ...f, apellidos: e.target.value }))}
        />
        <Campo
          etiqueta="Telefono" placeholder="0414-1234567"
          value={forma.telefono} error={errores.telefono}
          onChange={(e) => setForma((f) => ({ ...f, telefono: e.target.value }))}
        />
        <Campo
          etiqueta="Correo" type="email" placeholder="docente@iutepi.edu.ve"
          value={forma.correo} error={errores.correo}
          onChange={(e) => setForma((f) => ({ ...f, correo: e.target.value }))}
        />
        <Select
          etiqueta="Tipo de contrato" value={forma.tipo_contrato} error={errores.tipo_contrato}
          onChange={(e) => setForma((f) => ({ ...f, tipo_contrato: e.target.value }))}
        >
          <option value="TIEMPO_COMPLETO">Tiempo completo</option>
          <option value="MEDIO_TIEMPO">Medio tiempo</option>
          <option value="POR_HORAS">Por horas</option>
        </Select>
        <Campo
          etiqueta="Maximo de bloques por semana" type="number" min={2} max={40}
          ayuda="Tope de carga que respetara el generador."
          value={forma.max_bloques_semana} error={errores.max_bloques_semana}
          onChange={(e) => setForma((f) => ({ ...f, max_bloques_semana: e.target.value }))}
        />
      </div>

      <div className="flex justify-end border-t border-slate-200 pt-4">
        <Boton onClick={() => ejecutar()} cargando={enviando} icono={editando ? Check : UserPlus}>
          {editando ? 'Guardar y continuar' : 'Crear y continuar'}
        </Boton>
      </div>
    </div>
  )
}

/* ================================================================== */
/* Paso 2 · Disponibilidad                                             */
/* ================================================================== */

function PasoDisponibilidadConexion({ profesorId, profesor, alTerminar }) {
  const { avisar } = useAvisos()

  const { ejecutar, enviando } = useAccion(
    async (franjas) => api.put(`/profesores/${profesorId}/disponibilidad`, { franjas }),
    {
      alTerminar: () => { avisar.exito('Disponibilidad guardada.'); alTerminar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  return (
    <div className="space-y-5">
      <div>
        <h2 className="font-titulo text-lg font-semibold text-slate-900">Disponibilidad</h2>
        <p className="text-sm text-slate-500">¿Que dias y en que horario puede dar clase?</p>
      </div>

      <PasoDisponibilidad
        valorInicial={profesor?.disponibilidad ?? []}
        alGuardar={ejecutar}
        guardando={enviando}
      />
    </div>
  )
}

/* ================================================================== */
/* Paso 3 · Habilidades                                                */
/* ================================================================== */

function PasoHabilidadesConexion({ profesorId, profesor, catalogo, alTerminar }) {
  const { avisar } = useAvisos()

  const { ejecutar, enviando } = useAccion(
    async (habilidades) => api.put(`/profesores/${profesorId}/habilidades`, { habilidades }),
    {
      alTerminar: () => { avisar.exito('Habilidades guardadas. Ya puedes ver las materias sugeridas.'); alTerminar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  return (
    <div className="space-y-5">
      <div>
        <h2 className="font-titulo text-lg font-semibold text-slate-900">Habilidades</h2>
        <p className="text-sm text-slate-500">Marca el nivel de dominio en cada competencia.</p>
      </div>

      <PasoHabilidades
        catalogo={catalogo}
        valorInicial={profesor?.habilidades ?? []}
        alGuardar={ejecutar}
        guardando={enviando}
      />
    </div>
  )
}

/* ================================================================== */
/* Paso 4 · Materias                                                   */
/* ================================================================== */

function PasoMateriasConexion({ profesorId, alTerminar }) {
  const { avisar } = useAvisos()
  const { datos, cargando } = useDatos(`/profesores/${profesorId}/materias-sugeridas`, null, { ttl: 0 })

  const { ejecutar, enviando } = useAccion(
    async (materias) => api.put(`/profesores/${profesorId}/materias`, { materias }),
    {
      alTerminar: () => { avisar.exito('Materias confirmadas.'); alTerminar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  if (cargando) return <Cargando texto="Calculando afinidad con cada materia..." />

  return (
    <div className="space-y-5">
      <div>
        <h2 className="font-titulo text-lg font-semibold text-slate-900">Materias que puede dictar</h2>
        <p className="text-sm text-slate-500">Propuesta automatica, revisada por ti.</p>
      </div>

      <PasoMaterias
        sugerencias={datos?.sugerencias ?? []}
        confirmadas={datos?.confirmadas ?? []}
        alGuardar={ejecutar}
        guardando={enviando}
      />
    </div>
  )
}

/* ================================================================== */
/* Paso 5 · Horario                                                    */
/* ================================================================== */

function PasoHorario({ profesorId, profesor, alFinalizar }) {
  const { avisar } = useAvisos()
  const { datos: periodos } = useDatos('/periodos', null, { ttl: 60_000 })

  const vigentes = useMemo(
    () => (periodos || []).filter((p) => p.estado !== 'FINALIZADO'),
    [periodos]
  )
  const [periodoId, setPeriodoId] = useState('')
  const [modulo, setModulo] = useState(1)

  useEffect(() => {
    if (!periodoId && vigentes.length) setPeriodoId(String(vigentes[0].periodo_id))
  }, [vigentes, periodoId])

  const { datos: horario, cargando } = useDatos(
    periodoId ? `/profesores/${profesorId}/horario` : null,
    { periodo_id: periodoId, modulo },
    { ttl: 0 }
  )
  const { datos: rejilla } = useDatos(
    periodoId ? '/horarios' : null,
    { periodo_id: periodoId, modulo, profesor_id: profesorId },
    { ttl: 0 }
  )

  const { ejecutar: finalizar, enviando } = useAccion(
    async () => api.post(`/profesores/${profesorId}/finalizar-registro`),
    {
      alTerminar: () => alFinalizar(),
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const completo = Number(profesor?.paso_registro) >= 5

  return (
    <div className="space-y-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h2 className="font-titulo text-lg font-semibold text-slate-900">Horario del docente</h2>
          <p className="text-sm text-slate-500">
            Se llena solo cuando el administrador genera los horarios del periodo.
          </p>
        </div>

        <div className="flex flex-wrap items-end gap-3">
          <Select
            etiqueta="Periodo"
            className="w-48"
            value={periodoId}
            onChange={(e) => setPeriodoId(e.target.value)}
          >
            {vigentes.map((p) => (
              <option key={p.periodo_id} value={p.periodo_id}>{p.codigo}</option>
            ))}
          </Select>
          <SelectorModulo modulos={rejilla?.modulos ?? []} valor={modulo} alCambiar={setModulo} />
        </div>
      </div>

      {cargando ? (
        <Cargando texto="Cargando horario..." />
      ) : !rejilla?.clases?.length ? (
        <EstadoVacio
          icono={CalendarDays}
          titulo="Todavia no tiene clases asignadas"
          mensaje="Cuando se generen los horarios de este periodo, apareceran aqui automaticamente."
        />
      ) : (
        <>
          <div className="flex flex-wrap gap-2">
            <Etiqueta tono="marca" icono={BookOpen}>
              {new Set(rejilla.clases.map((c) => c.materia_id)).size} materias
            </Etiqueta>
            <Etiqueta tono="info" icono={Clock}>{rejilla.clases.length} bloques</Etiqueta>
            <Etiqueta tono="neutro">
              tope: {profesor?.max_bloques_semana} bloques por semana
            </Etiqueta>
          </div>

          <RejillaHorario
            bloques={rejilla.bloques}
            dias={rejilla.dias}
            clases={rejilla.clases}
            mostrarProfesor={false}
            mostrarSeccion
            vacioTitulo="Sin clases en este modulo"
          />
        </>
      )}

      <div className="flex justify-end border-t border-slate-200 pt-4">
        {completo ? (
          <Etiqueta tono="exito" icono={CircleCheck}>Registro completo</Etiqueta>
        ) : (
          <Boton onClick={() => finalizar()} cargando={enviando} icono={Check} variante="exito">
            Marcar registro como completo
          </Boton>
        )}
      </div>
    </div>
  )
}
