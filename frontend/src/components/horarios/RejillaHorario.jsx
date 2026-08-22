import { Coffee, Clock, FlaskConical, MapPin, Monitor, UserX } from 'lucide-react'
import { cx, DIAS_CORTOS, DIAS_LARGOS, hora } from '../../lib/utils'
import { EstadoVacio } from '../ui/Datos'

/* Paleta estable por materia: el mismo codigo siempre pinta igual, lo
   que ayuda a leer la rejilla de un vistazo.

   No hay rojos aqui a proposito: el carmesi de la marca esta reservado
   para acciones y navegacion, y si las celdas tambien fueran rojas la
   parrilla competiria con la interfaz en vez de leerse. */
const PALETA = [
  'bg-sky-50 text-sky-900 border-sky-300',
  'bg-emerald-50 text-emerald-900 border-emerald-300',
  'bg-amber-50 text-amber-900 border-amber-300',
  'bg-violet-50 text-violet-900 border-violet-300',
  'bg-teal-50 text-teal-900 border-teal-300',
  'bg-indigo-50 text-indigo-900 border-indigo-300',
  'bg-orange-50 text-orange-900 border-orange-300',
  'bg-cyan-50 text-cyan-900 border-cyan-300',
  'bg-lime-50 text-lime-900 border-lime-300',
  'bg-fuchsia-50 text-fuchsia-900 border-fuchsia-300',
  'bg-stone-100 text-stone-800 border-stone-300',
  'bg-blue-50 text-blue-900 border-blue-300',
]

function colorDe(texto = '') {
  let suma = 0
  for (let i = 0; i < texto.length; i++) suma = (suma + texto.charCodeAt(i) * (i + 1)) % 997
  return PALETA[suma % PALETA.length]
}

/** '07:20:00' -> 440 (minutos desde medianoche) */
function aMinutos(t = '') {
  const [h, m] = String(t).split(':').map(Number)
  return (h || 0) * 60 + (m || 0)
}

/** 70 -> '1h 10min' · 35 -> '35min' */
function duracion(minutos) {
  if (minutos <= 0) return ''
  const h = Math.floor(minutos / 60)
  const m = minutos % 60
  if (!h) return `${m}min`
  return m ? `${h}h ${m}min` : `${h}h`
}

/* Alto de cada bloque. Con esto la altura de la tarjeta ES la duracion
   de la clase: dos bloques ocupan el doble que uno. */
const ALTO_BLOQUE = '3.5rem'

/**
 * Cuanto detalle cabe en una tarjeta, segun los bloques que le tocan.
 *
 * En la vista de una sola seccion cada clase tiene su celda entera y se
 * muestra todo. En la de "todas las secciones" varias comparten celda y
 * el texto se recortaria, asi que se va quitando lo accesorio: primero
 * el aula, luego el docente. La hora de inicio y fin nunca se quita,
 * que es justo el dato que hay que poder leer siempre.
 */
function densidadDe(bloquesCelda, cuantasClases) {
  const porClase = bloquesCelda / Math.max(1, cuantasClases)
  if (porClase >= 1.8) return 'completa'
  if (porClase >= 0.9) return 'media'
  return 'minima'
}

/**
 * Rejilla de horario: filas = bloques, columnas = dias.
 *
 * Una clase que abarca varios bloques se dibuja como una sola tarjeta
 * que rellena exactamente esos bloques, con su hora de inicio y de fin
 * escritas encima. La duracion se lee de dos formas: por el tamano de
 * la tarjeta y por el texto.
 */
export default function RejillaHorario({
  bloques = [],
  dias = [],
  clases = [],
  alSeleccionar,
  mostrarProfesor = true,
  mostrarEspacio = true,
  mostrarSeccion = false,
  vacioTitulo = 'Sin horario generado',
  vacioMensaje = 'Todavia no hay clases ubicadas para esta seleccion.',
}) {
  if (!bloques.length || !dias.length) {
    return <EstadoVacio titulo="Falta la rejilla" mensaje="No se pudo cargar la estructura de bloques." />
  }

  if (!clases.length) {
    return <EstadoVacio titulo={vacioTitulo} mensaje={vacioMensaje} />
  }

  const ordenBloque = new Map(bloques.map((b) => [Number(b.bloque_id), Number(b.orden)]))

  /* --- Paso 1: bloques contiguos de una misma clase = un tramo ---
     El tramo guarda la hora de inicio del primer bloque y la de fin del
     ultimo, que es justo lo que hay que mostrar. */
  const tramos = []
  const porClase = {}

  clases.forEach((c) => {
    const clave = `${c.dia}|${c.asignacion_id}`
    ;(porClase[clave] ||= []).push(c)
  })

  Object.values(porClase).forEach((grupo) => {
    grupo.sort((a, b) => ordenBloque.get(Number(a.bloque_id)) - ordenBloque.get(Number(b.bloque_id)))

    let actual = null
    grupo.forEach((c) => {
      const orden = ordenBloque.get(Number(c.bloque_id))

      if (actual && orden === actual.ordenFin + 1) {
        actual.ordenFin = orden
        actual.horaFin = c.hora_fin
        actual.totalBloques += 1
      } else {
        actual = {
          ...c,
          ordenInicio: orden,
          ordenFin: orden,
          horaInicio: c.hora_inicio,
          horaFin: c.hora_fin,
          totalBloques: 1,
        }
        tramos.push(actual)
      }
    })
  })

  tramos.forEach((t) => {
    t.minutos = aMinutos(t.horaFin) - aMinutos(t.horaInicio)
  })

  /* --- Paso 2: agrupar en celdas los tramos que se solapan ---
     En la vista "todas las secciones" varias clases caen en el mismo
     dia y bloque. Si cada una intentara ocupar su propia celda, los
     rowSpan se pisarian y la tabla escupiria las tarjetas fuera de las
     columnas. Por eso los tramos solapados se funden en UNA celda que
     va del primer bloque al ultimo, con las clases apiladas dentro. */
  const celdasPorDia = {}

  dias.forEach((dia) => {
    const delDia = tramos
      .filter((t) => t.dia === dia)
      .sort((a, b) => a.ordenInicio - b.ordenInicio || a.ordenFin - b.ordenFin)

    const celdas = []
    delDia.forEach((t) => {
      const abierta = celdas[celdas.length - 1]

      if (abierta && t.ordenInicio <= abierta.ordenFin) {
        abierta.clases.push(t)
        abierta.ordenFin = Math.max(abierta.ordenFin, t.ordenFin)
      } else {
        celdas.push({ ordenInicio: t.ordenInicio, ordenFin: t.ordenFin, clases: [t] })
      }
    })

    celdasPorDia[dia] = celdas
  })

  const inicios = {}
  const cubiertos = {}

  dias.forEach((dia) => {
    inicios[dia] = {}
    cubiertos[dia] = new Set()

    celdasPorDia[dia].forEach((celda) => {
      inicios[dia][celda.ordenInicio] = celda
      for (let o = celda.ordenInicio; o <= celda.ordenFin; o++) {
        cubiertos[dia].add(o)
      }
    })
  })

  return (
    <div className="imprimible overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-tarjeta">
      <table className="w-full min-w-[44rem] table-fixed border-collapse text-sm">
        <colgroup>
          <col className="w-24" />
          {dias.map((dia) => (
            <col key={dia} />
          ))}
        </colgroup>

        <thead>
          <tr className="bg-slate-50">
            <th className="sticky left-0 z-10 border-b border-r border-slate-200 bg-slate-50 px-2 py-2.5 text-[0.7rem] font-semibold uppercase tracking-wider text-slate-500">
              Hora
            </th>
            {dias.map((dia) => (
              <th
                key={dia}
                className="border-b border-l border-slate-200 px-2 py-2.5 text-[0.7rem] font-semibold uppercase tracking-wider text-slate-600"
              >
                <span className="hidden sm:inline">{DIAS_LARGOS[dia]}</span>
                <span className="sm:hidden">{DIAS_CORTOS[dia]}</span>
              </th>
            ))}
          </tr>
        </thead>

        <tbody>
          {bloques.map((bloque) => {
            const orden = Number(bloque.orden)
            const receso = Number(bloque.es_receso) === 1

            if (receso) {
              return (
                <tr key={bloque.bloque_id}>
                  <td className="sticky left-0 z-10 whitespace-nowrap border-y border-r border-slate-200 bg-slate-100 px-2 py-1 text-center text-[0.68rem] font-medium text-slate-500">
                    {hora(bloque.hora_inicio)}–{hora(bloque.hora_fin)}
                  </td>
                  <td colSpan={dias.length} className="border-y border-slate-200 bg-slate-100 px-2 py-1 text-center">
                    <span className="inline-flex items-center gap-1.5 text-[0.68rem] font-semibold uppercase tracking-[0.2em] text-slate-500">
                      <Coffee className="h-3.5 w-3.5" />
                      Receso
                    </span>
                  </td>
                </tr>
              )
            }

            return (
              <tr key={bloque.bloque_id} style={{ height: ALTO_BLOQUE }}>
                {/* Columna de horas: el bloque con su numero de orden */}
                <td className="sticky left-0 z-10 border-b border-r border-slate-200 bg-white px-2 text-center align-middle">
                  <span className="block text-[0.78rem] font-semibold leading-tight text-slate-700">
                    {hora(bloque.hora_inicio)}
                  </span>
                  <span className="block text-[0.65rem] leading-tight text-slate-400">
                    a {hora(bloque.hora_fin)}
                  </span>
                </td>

                {dias.map((dia) => {
                  const celda = inicios[dia]?.[orden]

                  if (celda) {
                    const span = celda.ordenFin - celda.ordenInicio + 1
                    return (
                      /* `relative` + hijo `absolute inset-0` es lo unico
                         fiable para que el contenido rellene una celda
                         con rowSpan: `height:100%` no resuelve dentro de
                         un <td>. */
                      <td
                        key={dia}
                        rowSpan={span}
                        className="relative border-b border-l border-slate-100 p-0 align-top"
                      >
                        <div className="absolute inset-0 flex flex-col gap-1 overflow-y-auto p-1">
                          {celda.clases.map((clase) => (
                            <Clase
                              key={`${clase.asignacion_id}-${clase.ordenInicio}`}
                              clase={clase}
                              alSeleccionar={alSeleccionar}
                              mostrarProfesor={mostrarProfesor}
                              mostrarEspacio={mostrarEspacio}
                              mostrarSeccion={mostrarSeccion}
                              densidad={densidadDe(span, celda.clases.length)}
                            />
                          ))}
                        </div>
                      </td>
                    )
                  }

                  if (cubiertos[dia]?.has(orden)) return null

                  return <td key={dia} className="border-b border-l border-slate-100" />
                })}
              </tr>
            )
          })}
        </tbody>
      </table>
    </div>
  )
}

/* ------------------------------------------------------------------ */

function Clase({ clase, alSeleccionar, mostrarProfesor, mostrarEspacio, mostrarSeccion, densidad = 'completa' }) {
  const sinDocente = !clase.profesor
  const virtual = clase.modalidad_clase === 'VIRTUAL' || !clase.espacio
  const esLab = clase.espacio_tipo === 'LABORATORIO'

  const minima = densidad === 'minima'
  const completa = densidad === 'completa'

  const contenido = (
    <>
      <p className={cx('truncate font-semibold leading-tight', minima ? 'text-[0.68rem]' : 'text-xs')}>
        {clase.materia}
      </p>

      {/* Desde cuando, hasta cuando y cuanto dura. No se oculta nunca. */}
      <p className="flex items-center gap-1 text-[0.66rem] font-medium leading-tight">
        <Clock className="h-2.5 w-2.5 shrink-0 opacity-60" />
        <span className="tabular-nums">{hora(clase.horaInicio)}–{hora(clase.horaFin)}</span>
        <span className="truncate opacity-70">
          · {clase.totalBloques} {clase.totalBloques === 1 ? 'bloque' : 'bloques'}
          {completa && clase.minutos > 0 && ` · ${duracion(clase.minutos)}`}
        </span>
      </p>

      {mostrarSeccion && (
        <p className="truncate text-[0.66rem] font-semibold leading-tight opacity-80">{clase.seccion}</p>
      )}

      {mostrarProfesor && !minima && (
        <p
          className={cx(
            'flex items-center gap-1 text-[0.66rem] leading-tight',
            sinDocente ? 'font-semibold text-marca-700' : 'opacity-80'
          )}
        >
          {sinDocente && <UserX className="h-2.5 w-2.5 shrink-0" />}
          <span className="truncate">{clase.profesor || 'Sin docente'}</span>
        </p>
      )}

      {mostrarEspacio && completa && (
        <p className="flex items-center gap-1 text-[0.66rem] leading-tight opacity-75">
          {virtual ? (
            <>
              <Monitor className="h-2.5 w-2.5 shrink-0" />
              Virtual
            </>
          ) : (
            <>
              {esLab ? <FlaskConical className="h-2.5 w-2.5 shrink-0" /> : <MapPin className="h-2.5 w-2.5 shrink-0" />}
              <span className="truncate">{clase.espacio}</span>
            </>
          )}
        </p>
      )}

      {/* Con poco sitio, aula y modalidad se resumen junto al docente */}
      {mostrarEspacio && !completa && (
        <p className="flex items-center gap-1 text-[0.66rem] leading-tight opacity-70">
          {virtual ? <Monitor className="h-2.5 w-2.5 shrink-0" /> : esLab ? <FlaskConical className="h-2.5 w-2.5 shrink-0" /> : <MapPin className="h-2.5 w-2.5 shrink-0" />}
          <span className="truncate">{virtual ? 'Virtual' : clase.espacio}</span>
        </p>
      )}
    </>
  )

  const clases = cx(
    'celda-horario flex w-full shrink-0 flex-col justify-center gap-0.5 overflow-hidden border-l-4 border-y border-r text-left',
    completa ? 'flex-1' : 'flex-none',
    colorDe(clase.materia_codigo || clase.materia),
    sinDocente && 'ring-1 ring-inset ring-marca-300',
    alSeleccionar && 'cursor-pointer hover:brightness-[0.97]'
  )

  if (!alSeleccionar) return <div className={clases}>{contenido}</div>

  return (
    <button type="button" onClick={() => alSeleccionar(clase)} className={clases}>
      {contenido}
    </button>
  )
}

/** Selector de modulo (1er / 2do modulo del periodo). */
export function SelectorModulo({ modulos = [], valor, alCambiar }) {
  if (modulos.length <= 1) return null

  return (
    <div className="inline-flex rounded-xl bg-slate-100 p-1">
      {modulos.map((m) => {
        const numero = Number(m.numero)
        const activo = Number(valor) === numero
        return (
          <button
            key={numero}
            type="button"
            onClick={() => alCambiar(numero)}
            className={cx(
              'rounded-lg px-3 py-1.5 text-sm font-medium transition',
              activo ? 'bg-white text-marca-900 shadow-sm' : 'text-slate-500 hover:text-slate-800'
            )}
          >
            {numero}
            <span className="hidden sm:inline">
              {numero === 1 ? 'er' : numero === 2 ? 'do' : numero === 3 ? 'er' : 'to'} modulo
            </span>
          </button>
        )
      })}
    </div>
  )
}
