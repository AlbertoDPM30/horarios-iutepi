import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import {
  ArrowRightLeft, CalendarClock, CheckCircle2, CircleSlash, Monitor, RefreshCw, TriangleAlert,
  UserX, X,
} from 'lucide-react'
import api from '../lib/api'
import { useAccion, useDatos } from '../lib/hooks'
import { useAvisos } from '../context/AvisosContext'
import Boton from '../components/ui/Boton'
import { Select } from '../components/ui/Campos'
import Modal from '../components/ui/Modal'
import { Cargando, EstadoVacio, Etiqueta, Tarjeta, TituloSeccion } from '../components/ui/Datos'
import { cx, DIAS_LARGOS, hora, tiempoRelativo } from '../lib/utils'

const TIPOS = {
  SIN_DOCENTE: { etiqueta: 'Sin docente', icono: UserX, tono: 'peligro' },
  SIN_BLOQUE: { etiqueta: 'Sin bloque libre', icono: CalendarClock, tono: 'aviso' },
  SIN_ESPACIO: { etiqueta: 'Sin aula', icono: CircleSlash, tono: 'aviso' },
  PROFESOR_SOLAPADO: { etiqueta: 'Choque de docente', icono: ArrowRightLeft, tono: 'peligro' },
  ESPACIO_SOLAPADO: { etiqueta: 'Choque de aula', icono: ArrowRightLeft, tono: 'peligro' },
  SECCION_SOLAPADA: { etiqueta: 'Choque de seccion', icono: ArrowRightLeft, tono: 'peligro' },
  SIN_DISPONIBILIDAD: { etiqueta: 'Sin disponibilidad', icono: CalendarClock, tono: 'aviso' },
  CARGA_EXCEDIDA: { etiqueta: 'Carga excedida', icono: TriangleAlert, tono: 'aviso' },
  ESTUDIANTE_SOLAPADO: { etiqueta: 'Cruce de estudiante', icono: Monitor, tono: 'info' },
}

const OPCIONES = [
  {
    valor: 'REASIGNAR_DOCENTE',
    titulo: 'Asignar a otro docente',
    descripcion: 'La materia se queda donde esta y la toma otro profesor habilitado.',
    icono: ArrowRightLeft,
    necesitaDocente: true,
  },
  {
    valor: 'REGENERAR_HORARIO',
    titulo: 'Generar de nuevo el horario',
    descripcion: 'El sistema vuelve a resolver la seccion afectada desde cero.',
    icono: RefreshCw,
  },
  {
    valor: 'DEJAR_SIN_DOCENTE',
    titulo: 'Dejar sin docente',
    descripcion: 'La materia queda publicada en la parrilla y se asigna despues.',
    icono: UserX,
  },
  {
    valor: 'NUEVO_BLOQUE',
    titulo: 'Asignar nuevo bloque horario',
    descripcion: 'La materia se mueve a otro dia u horario que este libre.',
    icono: CalendarClock,
    necesitaBloque: true,
  },
]

/**
 * Bandeja de conflictos. Cada uno se resuelve eligiendo una de las
 * cuatro salidas previstas; el sistema aplica el cambio y deja registro
 * de quien lo decidio.
 */
export default function Conflictos() {
  const { avisar } = useAvisos()
  const [params, setParams] = useSearchParams()

  const { datos: periodos } = useDatos('/periodos', null, { ttl: 60_000 })
  const periodoId = params.get('periodo') || ''
  const [estado, setEstado] = useState('PENDIENTE')

  const { datos: conflictos, meta, cargando, recargar } = useDatos(
    '/conflictos',
    { periodo_id: periodoId || undefined, estado: estado || undefined },
    { ttl: 10_000 }
  )

  const [abierto, setAbierto] = useState(null)

  // Abrir directamente el conflicto que venga en el enlace de la campana
  useEffect(() => {
    const id = params.get('conflicto')
    if (id && conflictos?.length) {
      const encontrado = conflictos.find((c) => String(c.conflicto_id) === String(id))
      if (encontrado) setAbierto(encontrado)
    }
  }, [params, conflictos])

  const pendientes = meta?.pendientes ?? 0

  return (
    <div>
      <TituloSeccion
        icono={TriangleAlert}
        titulo="Conflictos"
        descripcion="Todo lo que el generador no pudo resolver solo y necesita una decision tuya."
        acciones={
          <Boton variante="secundario" icono={RefreshCw} onClick={recargar}>
            Actualizar
          </Boton>
        }
      />

      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Select
          etiqueta="Periodo"
          value={periodoId}
          onChange={(e) => {
            const nuevos = new URLSearchParams(params)
            if (e.target.value) nuevos.set('periodo', e.target.value)
            else nuevos.delete('periodo')
            setParams(nuevos)
          }}
        >
          <option value="">Todos los periodos</option>
          {(periodos || []).map((p) => (
            <option key={p.periodo_id} value={p.periodo_id}>{p.codigo}</option>
          ))}
        </Select>
        <Select etiqueta="Estado" value={estado} onChange={(e) => setEstado(e.target.value)}>
          <option value="PENDIENTE">Pendientes</option>
          <option value="RESUELTO">Resueltos</option>
          <option value="IGNORADO">Ignorados</option>
          <option value="">Todos</option>
        </Select>
      </div>

      {cargando && !conflictos ? (
        <Cargando texto="Buscando conflictos..." />
      ) : !conflictos?.length ? (
        <EstadoVacio
          icono={CheckCircle2}
          titulo={estado === 'PENDIENTE' ? 'No hay conflictos pendientes' : 'Sin resultados'}
          mensaje={
            estado === 'PENDIENTE'
              ? 'Todos los horarios quedaron resueltos. Si generas de nuevo y aparece algo, te avisamos por la campana.'
              : 'Prueba con otro filtro.'
          }
        />
      ) : (
        <>
          {pendientes > 0 && estado === 'PENDIENTE' && (
            <p className="mb-3 text-sm text-slate-500">
              <strong className="text-slate-800">{pendientes}</strong> conflicto(s) esperando tu decision.
            </p>
          )}

          <div className="space-y-3">
            {conflictos.map((c) => (
              <TarjetaConflicto key={c.conflicto_id} conflicto={c} alAbrir={() => setAbierto(c)} />
            ))}
          </div>
        </>
      )}

      {abierto && (
        <ModalResolver
          conflicto={abierto}
          alCerrar={() => {
            setAbierto(null)
            const nuevos = new URLSearchParams(params)
            nuevos.delete('conflicto')
            setParams(nuevos, { replace: true })
          }}
          alResolver={() => { setAbierto(null); recargar(); avisar.exito('Conflicto resuelto.') }}
        />
      )}
    </div>
  )
}

/* ------------------------------------------------------------------ */

function TarjetaConflicto({ conflicto, alAbrir }) {
  const tipo = TIPOS[conflicto.tipo] || { etiqueta: conflicto.tipo, icono: TriangleAlert, tono: 'neutro' }
  const Icono = tipo.icono
  const pendiente = conflicto.estado === 'PENDIENTE'

  return (
    <Tarjeta
      className={cx(
        'transition',
        pendiente ? 'border-l-4 border-l-amber-400 hover:shadow-flotante' : 'opacity-80'
      )}
    >
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start">
        <span
          className={cx(
            'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
            conflicto.severidad === 'CRITICA' || conflicto.severidad === 'ALTA'
              ? 'bg-rose-100 text-rose-700'
              : 'bg-amber-100 text-amber-700'
          )}
        >
          <Icono className="h-5 w-5" />
        </span>

        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="font-semibold text-slate-900">{conflicto.titulo}</h3>
            <Etiqueta tono={tipo.tono}>{tipo.etiqueta}</Etiqueta>
            {!pendiente && (
              <Etiqueta tono={conflicto.estado === 'RESUELTO' ? 'exito' : 'neutro'}>
                {conflicto.estado === 'RESUELTO' ? 'Resuelto' : 'Ignorado'}
              </Etiqueta>
            )}
          </div>

          <p className="mt-1 text-sm leading-relaxed text-slate-600">{conflicto.descripcion}</p>

          <div className="mt-2 flex flex-wrap gap-1.5 text-xs">
            <Etiqueta tono="neutro">{conflicto.periodo}</Etiqueta>
            {conflicto.seccion && <Etiqueta tono="neutro">Seccion {conflicto.seccion}</Etiqueta>}
            {conflicto.materia_codigo && <Etiqueta tono="neutro">{conflicto.materia_codigo}</Etiqueta>}
            <span className="self-center text-slate-400">{tiempoRelativo(conflicto.creado_en)}</span>
          </div>

          {!pendiente && conflicto.nota_resolucion && (
            <p className="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
              {conflicto.nota_resolucion}
              {conflicto.resuelto_por_nombre && <> · {conflicto.resuelto_por_nombre}</>}
            </p>
          )}
        </div>

        {pendiente && (
          <Boton onClick={alAbrir} className="shrink-0">Resolver</Boton>
        )}
      </div>
    </Tarjeta>
  )
}

/* ------------------------------------------------------------------ */

function ModalResolver({ conflicto, alCerrar, alResolver }) {
  const { avisar } = useAvisos()
  const [opcion, setOpcion] = useState(null)
  const [profesorId, setProfesorId] = useState('')
  const [dia, setDia] = useState('')
  const [bloques, setBloques] = useState([])

  const { datos: detalle, cargando } = useDatos(`/conflictos/${conflicto.conflicto_id}`, null, { ttl: 0 })
  const { datos: rejilla } = useDatos(
    opcion === 'NUEVO_BLOQUE' ? '/horarios' : null,
    { periodo_id: conflicto.periodo_id, seccion_id: conflicto.seccion_id, modulo: 1 },
    { ttl: 0 }
  )

  const candidatos = detalle?.candidatos ?? []
  const bloquesActuales = detalle?.bloques ?? []

  const { ejecutar, enviando } = useAccion(
    async () => {
      const cuerpo = { resolucion: opcion }
      if (opcion === 'REASIGNAR_DOCENTE') cuerpo.profesor_id = Number(profesorId)
      if (opcion === 'NUEVO_BLOQUE') {
        cuerpo.dia = dia
        cuerpo.bloques = bloques
      }
      return api.patch(`/conflictos/${conflicto.conflicto_id}/resolver`, cuerpo)
    },
    {
      alTerminar: () => alResolver(),
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const { ejecutar: ignorar, enviando: ignorando } = useAccion(
    async () => api.patch(`/conflictos/${conflicto.conflicto_id}/ignorar`),
    {
      alTerminar: () => { avisar.info('Conflicto marcado como ignorado.'); alResolver() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const listo =
    opcion &&
    (opcion !== 'REASIGNAR_DOCENTE' || profesorId) &&
    (opcion !== 'NUEVO_BLOQUE' || (dia && bloques.length > 0))

  const bloquesLectivos = (rejilla?.bloques ?? []).filter((b) => Number(b.es_receso) === 0)

  return (
    <Modal
      abierto
      alCerrar={alCerrar}
      ancho="xl"
      titulo="Resolver conflicto"
      descripcion={conflicto.titulo}
      pie={
        <>
          <Boton variante="secundario" onClick={alCerrar} disabled={enviando || ignorando}>Cancelar</Boton>
          <Boton variante="fantasma" onClick={() => ignorar()} cargando={ignorando} icono={X}>
            Ignorar
          </Boton>
          <Boton onClick={() => ejecutar()} cargando={enviando} disabled={!listo}>
            Aplicar decision
          </Boton>
        </>
      }
    >
      <div className="space-y-5">
        <div className="rounded-xl bg-slate-50 p-4">
          <p className="text-sm leading-relaxed text-slate-700">{conflicto.descripcion}</p>
          {bloquesActuales.length > 0 && (
            <p className="mt-2 text-xs text-slate-500">
              Ubicacion actual:{' '}
              {bloquesActuales.map((b) => `${DIAS_LARGOS[b.dia]} ${hora(b.hora_inicio)}`).join(', ')}
            </p>
          )}
        </div>

        <div>
          <p className="mb-2 text-sm font-semibold text-slate-800">¿Que quieres hacer?</p>
          <div className="grid gap-2 sm:grid-cols-2">
            {OPCIONES.map((op) => {
              const Icono = op.icono
              const activo = opcion === op.valor
              return (
                <button
                  key={op.valor}
                  type="button"
                  onClick={() => setOpcion(op.valor)}
                  className={cx(
                    'flex items-start gap-3 rounded-2xl border-2 p-3.5 text-left transition',
                    activo ? 'border-marca-700 bg-marca-50' : 'border-slate-200 bg-white hover:border-slate-300'
                  )}
                >
                  <Icono className={cx('mt-0.5 h-5 w-5 shrink-0', activo ? 'text-marca-700' : 'text-slate-400')} />
                  <span className="min-w-0">
                    <span className={cx('block text-sm font-semibold', activo ? 'text-marca-900' : 'text-slate-800')}>
                      {op.titulo}
                    </span>
                    <span className="block text-xs leading-snug text-slate-500">{op.descripcion}</span>
                  </span>
                </button>
              )
            })}
          </div>
        </div>

        {/* ---- Elegir docente ---- */}
        {opcion === 'REASIGNAR_DOCENTE' && (
          <div className="rounded-2xl border border-slate-200 p-4">
            <p className="mb-2 text-sm font-semibold text-slate-800">¿Quien toma la materia?</p>
            {cargando ? (
              <Cargando texto="Buscando docentes..." />
            ) : !candidatos.length ? (
              <p className="rounded-lg bg-amber-50 px-3 py-3 text-sm text-amber-900">
                Ningun docente esta habilitado para esta materia. Revisa las habilidades exigidas o elige otra opcion.
              </p>
            ) : (
              <div className="max-h-56 space-y-2 overflow-y-auto pr-1">
                {candidatos.map((c) => (
                  <label
                    key={c.profesor_id}
                    className={cx(
                      'flex cursor-pointer items-center gap-3 rounded-xl border p-2.5 transition',
                      String(profesorId) === String(c.profesor_id)
                        ? 'border-marca-500 bg-marca-50'
                        : c.libre
                          ? 'border-slate-200 hover:bg-slate-50'
                          : 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-60'
                    )}
                  >
                    <input
                      type="radio"
                      name="docente"
                      disabled={!c.libre}
                      checked={String(profesorId) === String(c.profesor_id)}
                      onChange={() => setProfesorId(c.profesor_id)}
                      className="h-4 w-4 text-marca-700 focus:ring-marca-700"
                    />
                    <span className="min-w-0 flex-1">
                      <span className="block text-sm font-medium text-slate-800">{c.profesor}</span>
                      <span className="block text-xs text-slate-500">
                        {c.libre ? `Libre · ${c.carga_actual} bloques asignados` : c.motivo}
                      </span>
                    </span>
                    <span className="text-sm font-bold text-marca-700">{Math.round(c.afinidad)}%</span>
                  </label>
                ))}
              </div>
            )}
          </div>
        )}

        {/* ---- Elegir bloque ---- */}
        {opcion === 'NUEVO_BLOQUE' && (
          <div className="rounded-2xl border border-slate-200 p-4">
            <p className="mb-2 text-sm font-semibold text-slate-800">¿A que bloque la movemos?</p>

            <Select etiqueta="Dia" value={dia} onChange={(e) => { setDia(e.target.value); setBloques([]) }}>
              <option value="">Selecciona un dia</option>
              {(rejilla?.dias ?? []).map((d) => (
                <option key={d} value={d}>{DIAS_LARGOS[d]}</option>
              ))}
            </Select>

            {dia && (
              <>
                <p className="mb-2 mt-3 text-xs text-slate-500">
                  Marca los bloques seguidos que ocupara la materia.
                </p>
                <div className="grid max-h-52 grid-cols-2 gap-1.5 overflow-y-auto pr-1 sm:grid-cols-3">
                  {bloquesLectivos.map((b) => {
                    const id = Number(b.bloque_id)
                    const ocupado = (rejilla?.clases ?? []).some(
                      (c) => c.dia === dia && Number(c.bloque_id) === id
                    )
                    const activo = bloques.includes(id)

                    return (
                      <button
                        key={id}
                        type="button"
                        disabled={ocupado}
                        onClick={() =>
                          setBloques((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]))
                        }
                        className={cx(
                          'rounded-lg border px-2 py-1.5 text-xs font-medium transition',
                          activo
                            ? 'border-marca-700 bg-marca-700 text-white'
                            : ocupado
                              ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400 line-through'
                              : 'border-slate-200 bg-white text-slate-700 hover:border-marca-300'
                        )}
                      >
                        {hora(b.hora_inicio)}–{hora(b.hora_fin)}
                      </button>
                    )
                  })}
                </div>
              </>
            )}
          </div>
        )}
      </div>
    </Modal>
  )
}
