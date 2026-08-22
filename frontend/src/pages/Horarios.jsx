import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import {
  ArrowLeft, CalendarDays, FlaskConical, LayoutGrid, Printer, TriangleAlert, UserRound, Users,
} from 'lucide-react'
import { useAuth } from '../context/AuthContext'
import { useDatos } from '../lib/hooks'
import { Select } from '../components/ui/Campos'
import Boton from '../components/ui/Boton'
import { Cargando, EstadoVacio, Etiqueta, Tarjeta, TituloSeccion } from '../components/ui/Datos'
import RejillaHorario, { SelectorModulo } from '../components/horarios/RejillaHorario'
import ModalClase from '../components/horarios/ModalClase'
import { cx, fechaCorta } from '../lib/utils'

const VISTAS = [
  { clave: 'seccion', etiqueta: 'Por seccion', icono: LayoutGrid, roles: ['ADMIN', 'DOCENTE'] },
  { clave: 'profesor', etiqueta: 'Por docente', icono: Users, roles: ['ADMIN'] },
  { clave: 'laboratorio', etiqueta: 'Laboratorios', icono: FlaskConical, roles: ['ADMIN', 'DOCENTE'] },
]

/**
 * Consulta de horarios. Tres lecturas del mismo dato:
 *   - por seccion    (lo que ve el estudiante en la cartelera)
 *   - por docente    (la carga de cada profesor)
 *   - laboratorios   (ocupacion de los espacios de computo)
 */
export default function Horarios() {
  const { rol, esAdmin } = useAuth()
  const [params, setParams] = useSearchParams()

  const { datos: periodos } = useDatos('/periodos', null, { ttl: 60_000 })

  const periodoId =
    params.get('periodo') ||
    periodos?.find((p) => p.estado === 'EN_CURSO')?.periodo_id ||
    periodos?.[0]?.periodo_id ||
    ''

  const [vista, setVista] = useState(params.get('vista') || 'seccion')
  const [modulo, setModulo] = useState(1)
  const [seccionId, setSeccionId] = useState(params.get('seccion') || '')
  const [profesorId, setProfesorId] = useState('')
  const [espacioId, setEspacioId] = useState('')
  const [claseAbierta, setClaseAbierta] = useState(null)

  const vistasVisibles = VISTAS.filter((v) => v.roles.includes(rol))

  const { datos: secciones } = useDatos(periodoId ? '/secciones' : null, { periodo_id: periodoId })
  const { datos: profesores } = useDatos(esAdmin ? '/profesores' : null, { por_pagina: 200 })
  const { datos: catalogos } = useDatos('/catalogos', null, { ttl: 300_000 })

  const laboratorios = useMemo(
    () => (catalogos?.espacios ?? []).filter((e) => e.tipo === 'LABORATORIO'),
    [catalogos]
  )

  /* Se preselecciona una seccion la primera vez que llegan, para no
     abrir la pantalla con la parrilla de las siete a la vez. Despues no
     se vuelve a forzar: si no fuera asi, elegir "Todas las secciones"
     seria imposible porque el efecto lo revertiria al instante. */
  const seccionPrefijada = useRef(false)

  useEffect(() => {
    if (seccionPrefijada.current || vista !== 'seccion' || !secciones?.length) return
    seccionPrefijada.current = true
    if (!seccionId) setSeccionId(String(secciones[0].seccion_id))
  }, [vista, seccionId, secciones])

  const filtros = useMemo(() => {
    const base = { periodo_id: periodoId, modulo, vista }
    if (vista === 'seccion' && seccionId) base.seccion_id = seccionId
    if (vista === 'profesor' && profesorId) base.profesor_id = profesorId
    if (vista === 'laboratorio' && espacioId) base.espacio_id = espacioId
    return base
  }, [periodoId, modulo, vista, seccionId, profesorId, espacioId])

  const { datos, cargando } = useDatos(periodoId ? '/horarios' : null, filtros, { ttl: 15_000 })

  function cambiarPeriodo(valor) {
    const nuevos = new URLSearchParams(params)
    nuevos.set('periodo', valor)
    setParams(nuevos)
    setSeccionId('')
    setModulo(1)
  }

  const periodo = datos?.periodo
  const clases = datos?.clases ?? []
  const sinDocente = clases.filter((c) => !c.profesor).length

  /* Vuelve al periodo que se esta consultando, no a uno fijo: si se
     cambia el selector, el enlace sigue al periodo nuevo. */
  const periodoSeleccionado = periodo || (periodos || []).find((p) => String(p.periodo_id) === String(periodoId))

  /*
   * Una parrilla por entidad, no todo apilado en la misma.
   *
   * Cuando no se filtra por una seccion (o docente, o laboratorio) las
   * clases de todas ellas caerian en los mismos bloques y habria que
   * amontonar seis tarjetas en una celda, que no hay forma de leer. En
   * su lugar se dibuja una rejilla por cada una, como las hojas que se
   * pegan en la cartelera.
   */
  const parrillas = useMemo(() => {
    if (!clases.length) return []

    const agrupacion = {
      seccion: seccionId ? null : { campo: 'seccion_id', etiqueta: 'seccion', extra: 'carrera' },
      profesor: profesorId ? null : { campo: 'profesor_id', etiqueta: 'profesor', extra: null },
      laboratorio: espacioId ? null : { campo: 'espacio_id', etiqueta: 'espacio', extra: 'espacio_tipo' },
    }[vista]

    if (!agrupacion) return [{ clave: 'unica', titulo: null, clases }]

    const grupos = new Map()
    clases.forEach((c) => {
      const clave = c[agrupacion.campo] ?? 'sin'
      if (!grupos.has(clave)) {
        grupos.set(clave, {
          clave: String(clave),
          titulo: c[agrupacion.etiqueta] || 'Sin asignar',
          subtitulo: agrupacion.extra ? c[agrupacion.extra] : null,
          clases: [],
        })
      }
      grupos.get(clave).clases.push(c)
    })

    return [...grupos.values()].sort((a, b) => a.titulo.localeCompare(b.titulo, 'es'))
  }, [clases, vista, seccionId, profesorId, espacioId])

  return (
    <div>
      <Link
        to={periodoSeleccionado ? `/periodos/${periodoSeleccionado.periodo_id}` : '/'}
        className="no-imprimir mb-4 inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-slate-800"
      >
        <ArrowLeft className="h-4 w-4" />
        {periodoSeleccionado ? `Volver al periodo ${periodoSeleccionado.codigo}` : 'Volver al panel'}
      </Link>

      <TituloSeccion
        icono={CalendarDays}
        titulo="Horarios"
        descripcion={
          periodo
            ? `${periodo.codigo} · ${fechaCorta(periodo.fecha_inicio)} al ${fechaCorta(periodo.fecha_fin)}`
            : 'Consulta la parrilla de clases del periodo.'
        }
        acciones={
          <Boton variante="secundario" icono={Printer} onClick={() => window.print()} className="no-imprimir">
            Imprimir
          </Boton>
        }
      />

      {/* ---- Filtros ---- */}
      <Tarjeta className="no-imprimir mb-5">
        <div className="grid gap-4 lg:grid-cols-[1fr_auto]">
          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <Select etiqueta="Periodo" value={periodoId} onChange={(e) => cambiarPeriodo(e.target.value)}>
              {(periodos || []).map((p) => (
                <option key={p.periodo_id} value={p.periodo_id}>
                  {p.codigo} — {p.estado === 'EN_CURSO' ? 'en curso' : p.estado === 'PLANIFICACION' ? 'por iniciar' : 'finalizado'}
                </option>
              ))}
            </Select>

            {vista === 'seccion' && (
              <Select etiqueta="Seccion" value={seccionId} onChange={(e) => setSeccionId(e.target.value)}>
                <option value="">Todas las secciones</option>
                {(secciones || []).map((s) => (
                  <option key={s.seccion_id} value={s.seccion_id}>
                    {s.codigo} — {s.carrera_codigo} {s.semestre}° sem
                  </option>
                ))}
              </Select>
            )}

            {vista === 'profesor' && (
              <Select etiqueta="Docente" value={profesorId} onChange={(e) => setProfesorId(e.target.value)}>
                <option value="">Todos los docentes</option>
                {(profesores || []).map((p) => (
                  <option key={p.profesor_id} value={p.profesor_id}>{p.nombre_completo}</option>
                ))}
              </Select>
            )}

            {vista === 'laboratorio' && (
              <Select etiqueta="Laboratorio" value={espacioId} onChange={(e) => setEspacioId(e.target.value)}>
                <option value="">Todos los laboratorios</option>
                {laboratorios.map((l) => (
                  <option key={l.espacio_id} value={l.espacio_id}>{l.codigo} — {l.nombre}</option>
                ))}
              </Select>
            )}
          </div>

          <div className="flex flex-col justify-end gap-3 sm:flex-row sm:items-end">
            <SelectorModulo modulos={datos?.modulos ?? []} valor={modulo} alCambiar={setModulo} />
          </div>
        </div>

        {vistasVisibles.length > 1 && (
          <div className="mt-4 flex flex-wrap gap-2 border-t border-slate-100 pt-4">
            {vistasVisibles.map((v) => {
              const Icono = v.icono
              const activo = vista === v.clave
              return (
                <button
                  key={v.clave}
                  type="button"
                  onClick={() => {
                    setVista(v.clave)
                    const nuevos = new URLSearchParams(params)
                    nuevos.set('vista', v.clave)
                    setParams(nuevos)
                  }}
                  className={cx(
                    'inline-flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-medium transition',
                    activo
                      ? 'bg-marca-800 text-white shadow-sm'
                      : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                  )}
                >
                  <Icono className="h-4 w-4" />
                  {v.etiqueta}
                </button>
              )
            })}
          </div>
        )}
      </Tarjeta>

      {/* ---- Resumen ---- */}
      {clases.length > 0 && (
        <div className="mb-4 flex flex-wrap gap-2">
          <Etiqueta tono="marca" icono={CalendarDays}>{clases.length} bloques</Etiqueta>
          <Etiqueta tono="neutro">
            {new Set(clases.map((c) => c.materia_id)).size} materias
          </Etiqueta>
          <Etiqueta tono="neutro" icono={UserRound}>
            {new Set(clases.filter((c) => c.profesor_id).map((c) => c.profesor_id)).size} docentes
          </Etiqueta>
          {sinDocente > 0 && (
            <Etiqueta tono="peligro" icono={TriangleAlert}>{sinDocente} bloques sin docente</Etiqueta>
          )}
        </div>
      )}

      {/* ---- Rejilla(s) ---- */}
      {cargando && !datos ? (
        <Cargando texto="Cargando horario..." />
      ) : !periodoId ? (
        <EstadoVacio icono={CalendarDays} titulo="Selecciona un periodo" mensaje="Elige el periodo que quieres consultar." />
      ) : parrillas.length === 0 ? (
        <RejillaHorario
          bloques={datos?.bloques ?? []}
          dias={datos?.dias ?? []}
          clases={[]}
          vacioTitulo={
            Number(periodo?.horarios_generados) === 0
              ? 'Este periodo aun no tiene horarios generados'
              : 'Sin clases para esta seleccion'
          }
          vacioMensaje={
            periodo?.estado === 'PLANIFICACION'
              ? 'Entra al periodo y usa el boton "Generar horarios" para que el sistema arme la parrilla.'
              : 'Prueba cambiando de modulo, seccion o docente.'
          }
        />
      ) : (
        <div className="space-y-6">
          {parrillas.map((p) => (
            <section key={p.clave}>
              {p.titulo && (
                <div className="mb-2 flex flex-wrap items-center gap-2">
                  <h2 className="font-titulo text-base font-semibold text-slate-900">{p.titulo}</h2>
                  {p.subtitulo && <Etiqueta tono="neutro">{p.subtitulo}</Etiqueta>}
                  <Etiqueta tono="marca">{p.clases.length} bloques</Etiqueta>
                </div>
              )}
              <RejillaHorario
                bloques={datos?.bloques ?? []}
                dias={datos?.dias ?? []}
                clases={p.clases}
                alSeleccionar={esAdmin ? setClaseAbierta : undefined}
                mostrarProfesor={vista !== 'profesor' || !profesorId}
                mostrarSeccion={vista !== 'seccion'}
                mostrarEspacio
              />
            </section>
          ))}
        </div>
      )}

      {claseAbierta && (
        <ModalClase
          clase={claseAbierta}
          periodo={periodo}
          alCerrar={() => setClaseAbierta(null)}
          alActualizar={() => setClaseAbierta(null)}
        />
      )}
    </div>
  )
}
