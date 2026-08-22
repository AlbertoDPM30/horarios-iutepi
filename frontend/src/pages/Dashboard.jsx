import { Link } from 'react-router-dom'
import {
  ArrowRight, BookOpen, CalendarClock, CalendarDays, CalendarPlus, CircleCheck, DoorOpen,
  FlaskConical, Hourglass, Lock, TriangleAlert, UserRound, Users,
} from 'lucide-react'
import { useState } from 'react'
import { useAuth } from '../context/AuthContext'
import { useDatos } from '../lib/hooks'
import { cx, diasTexto, ESTADOS_PERIODO, fechaCorta } from '../lib/utils'
import Boton from '../components/ui/Boton'
import { Cargando, EstadoVacio, Etiqueta, Metrica, Tarjeta, TituloSeccion } from '../components/ui/Datos'
import ModalPeriodo from '../components/ModalPeriodo'

/**
 * Primera pantalla. Muestra el panel de periodos con su semaforo:
 *   amarillo = por iniciar (con la cuenta regresiva)
 *   verde    = en curso
 *   gris     = finalizado (solo lectura)
 */
export default function Dashboard() {
  const { rol, esAdmin, esEstudiante, usuario } = useAuth()
  const { datos, cargando, error, recargar } = useDatos('/dashboard', null, { ttl: 20_000 })
  const [modalNuevo, setModalNuevo] = useState(false)

  if (cargando && !datos) return <Cargando texto="Cargando tu panel..." />

  if (error) {
    return (
      <EstadoVacio
        icono={TriangleAlert}
        titulo="No se pudo cargar el panel"
        mensaje={error.message}
        accion={<Boton onClick={recargar}>Reintentar</Boton>}
      />
    )
  }

  const periodos = datos?.periodos ?? []
  const resumen = datos?.resumen ?? {}
  const modulosVacios = (datos?.modulos ?? []).filter((m) => m.vacio)

  const saludo = new Date().getHours() < 12 ? 'Buenos dias' : new Date().getHours() < 19 ? 'Buenas tardes' : 'Buenas noches'
  const nombreCorto = (usuario?.nombres || usuario?.nombre_completo || '').split(' ')[0]

  return (
    <div className="space-y-6">
      <TituloSeccion
        icono={CalendarDays}
        titulo={`${saludo}, ${nombreCorto}`}
        descripcion={
          esAdmin
            ? 'Panel general de periodos academicos.'
            : rol === 'DOCENTE'
              ? 'Aqui ves los periodos y tus horarios de clase.'
              : 'Aqui ves tus periodos y tu horario.'
        }
        acciones={
          esAdmin && (
            <Boton icono={CalendarPlus} onClick={() => setModalNuevo(true)}>
              Nuevo periodo
            </Boton>
          )
        }
      />

      {/* ---- Avisos de configuracion pendiente ---- */}
      {esAdmin && modulosVacios.length > 0 && (
        <div className="rounded-2xl border border-amber-200 bg-amber-50 p-4">
          <div className="flex items-start gap-3">
            <TriangleAlert className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
            <div className="min-w-0">
              <p className="text-sm font-semibold text-amber-900">
                Hay {modulosVacios.length} modulo(s) sin datos cargados
              </p>
              <p className="mt-0.5 text-sm text-amber-800">
                Sin esta informacion el sistema no puede generar horarios completos.
              </p>
              <div className="mt-2.5 flex flex-wrap gap-2">
                {modulosVacios.map((m) => (
                  <Link
                    key={m.clave}
                    to={m.ruta}
                    title={`${m.nombre}: ${m.descripcion}`}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-white px-2.5 py-1.5 text-xs font-semibold text-amber-900 ring-1 ring-inset ring-amber-300 transition hover:bg-amber-100"
                  >
                    {m.nombre}
                    <ArrowRight className="h-3.5 w-3.5" />
                  </Link>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ---- Metricas ---- */}
      {esAdmin && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <Metrica etiqueta="Docentes" valor={resumen.docentes ?? 0} icono={Users} />
          <Metrica etiqueta="Estudiantes" valor={resumen.estudiantes ?? 0} icono={UserRound} tono="info" />
          <Metrica etiqueta="Materias" valor={resumen.materias ?? 0} icono={BookOpen} tono="violeta" />
          <Metrica etiqueta="Salones" valor={resumen.salones ?? 0} icono={DoorOpen} tono="neutro" />
          <Metrica etiqueta="Laboratorios" valor={resumen.laboratorios ?? 0} icono={FlaskConical} tono="exito" />
          <Metrica
            etiqueta="Conflictos"
            valor={resumen.conflictos_pendientes ?? 0}
            icono={TriangleAlert}
            tono={resumen.conflictos_pendientes > 0 ? 'peligro' : 'exito'}
            pie={resumen.conflictos_pendientes > 0 ? 'Requieren tu decision' : 'Todo resuelto'}
          />
        </div>
      )}

      {rol === 'DOCENTE' && (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <Metrica etiqueta="Materias asignadas" valor={resumen.materias_asignadas ?? 0} icono={BookOpen} />
          <Metrica etiqueta="Bloques por semana" valor={resumen.bloques_semana ?? 0} icono={CalendarClock} tono="info" />
          <Metrica etiqueta="Periodos en curso" valor={resumen.periodos_en_curso ?? 0} icono={CircleCheck} tono="exito" />
          <Metrica etiqueta="Por iniciar" valor={resumen.periodos_planificacion ?? 0} icono={Hourglass} tono="aviso" />
        </div>
      )}

      {/* ---- Panel de periodos ---- */}
      <div>
        <div className="mb-3 flex items-center justify-between">
          <h2 className="font-titulo text-lg font-semibold text-slate-900">Periodos academicos</h2>
          <p className="text-sm text-slate-500">{periodos.length} en total</p>
        </div>

        {periodos.length === 0 ? (
          <EstadoVacio
            icono={CalendarDays}
            titulo="Aun no hay periodos"
            mensaje="Crea el primer periodo academico para empezar a cargar secciones y generar horarios."
            accion={esAdmin && <Boton icono={CalendarPlus} onClick={() => setModalNuevo(true)}>Crear periodo</Boton>}
          />
        ) : (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {periodos.map((periodo) => (
              <TarjetaPeriodo key={periodo.periodo_id} periodo={periodo} navegable={!esEstudiante} />
            ))}
          </div>
        )}
      </div>

      {esAdmin && (
        <ModalPeriodo
          abierto={modalNuevo}
          alCerrar={() => setModalNuevo(false)}
          alGuardar={() => {
            setModalNuevo(false)
            recargar()
          }}
        />
      )}
    </div>
  )
}

/**
 * Tarjeta de periodo.
 *
 * Con `navegable` en false se dibuja como ficha informativa: es el caso
 * del alumno, que ve el panel de periodos pero no entra al detalle (ahi
 * se administran secciones y horarios, que no le competen).
 */
function TarjetaPeriodo({ periodo, navegable = true }) {
  const estado = ESTADOS_PERIODO[periodo.estado] ?? ESTADOS_PERIODO.PLANIFICACION
  const porIniciar = periodo.estado === 'PLANIFICACION'
  const finalizado = periodo.estado === 'FINALIZADO'
  const dias = Number(periodo.dias_para_iniciar)

  const Contenedor = navegable ? Link : 'div'
  const propsContenedor = navegable ? { to: `/periodos/${periodo.periodo_id}` } : {}

  return (
    <Contenedor
      {...propsContenedor}
      className={cx(
        'group block rounded-2xl border p-5 shadow-tarjeta transition',
        estado.tarjeta,
        navegable && 'hover:shadow-flotante',
        navegable && !finalizado && 'hover:-translate-y-0.5',
        navegable && finalizado && 'hover:border-slate-300'
      )}
    >
      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="flex items-center gap-2">
            <h3 className={cx('font-titulo text-lg font-semibold', finalizado ? 'text-slate-600' : 'text-slate-900')}>
              {periodo.codigo}
            </h3>
            <Etiqueta tono={porIniciar ? 'aviso' : finalizado ? 'neutro' : 'exito'} punto>
              {estado.etiqueta}
            </Etiqueta>
          </div>
          <p className="mt-0.5 truncate text-sm text-slate-500">{periodo.nombre}</p>
        </div>

        <span
          className={cx(
            'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl',
            porIniciar ? 'bg-amber-100 text-amber-700' : finalizado ? 'bg-slate-200 text-slate-500' : 'bg-emerald-100 text-emerald-700'
          )}
        >
          {finalizado ? <Lock className="h-4 w-4" /> : porIniciar ? <Hourglass className="h-4 w-4" /> : <CircleCheck className="h-4 w-4" />}
        </span>
      </div>

      {porIniciar && dias >= 0 && (
        <p className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-amber-100 px-2.5 py-1.5 text-xs font-semibold text-amber-900">
          <CalendarClock className="h-3.5 w-3.5" />
          Proximo a iniciar {diasTexto(dias)}
        </p>
      )}

      {periodo.estado === 'EN_CURSO' && Number(periodo.dias_para_terminar) >= 0 && (
        <p className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-emerald-100 px-2.5 py-1.5 text-xs font-semibold text-emerald-900">
          <CalendarClock className="h-3.5 w-3.5" />
          Termina {diasTexto(periodo.dias_para_terminar)}
        </p>
      )}

      <dl className="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
        <div>
          <dt className="text-xs text-slate-400">Modalidad</dt>
          <dd className="font-medium text-slate-700">
            {periodo.modalidad === 'SABATINO' ? 'Sabatino' : 'Entre semana'}
          </dd>
        </div>
        <div>
          <dt className="text-xs text-slate-400">Duracion</dt>
          <dd className="font-medium text-slate-700">{periodo.semanas} semanas</dd>
        </div>
        <div className="col-span-2">
          <dt className="text-xs text-slate-400">Del</dt>
          <dd className="font-medium text-slate-700">
            {fechaCorta(periodo.fecha_inicio)} al {fechaCorta(periodo.fecha_fin)}
          </dd>
        </div>
      </dl>

      <div className="mt-4 flex flex-wrap items-center gap-1.5 border-t border-slate-200/70 pt-3 text-xs">
        <Etiqueta tono="neutro">{periodo.total_secciones} secciones</Etiqueta>
        <Etiqueta tono="neutro">{periodo.total_estudiantes} alumnos</Etiqueta>
        {Number(periodo.conflictos_pendientes) > 0 ? (
          <Etiqueta tono="peligro" icono={TriangleAlert}>
            {periodo.conflictos_pendientes} conflicto(s)
          </Etiqueta>
        ) : Number(periodo.total_bloques) > 0 ? (
          <Etiqueta tono="exito" icono={CircleCheck}>Horario listo</Etiqueta>
        ) : (
          <Etiqueta tono="aviso">Sin horario</Etiqueta>
        )}
        {navegable && (
          <ArrowRight className="ml-auto h-4 w-4 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-marca-700" />
        )}
      </div>
    </Contenedor>
  )
}
