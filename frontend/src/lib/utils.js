/** Utilidades chicas compartidas por toda la interfaz. */

/** Une clases condicionales sin traer una dependencia extra. */
export function cx(...partes) {
  return partes.flat().filter(Boolean).join(' ')
}

const MESES = [
  'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
  'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre',
]

/** '2026-08-17' -> '17 de agosto de 2026' */
export function fechaLarga(iso) {
  if (!iso) return '—'
  const [a, m, d] = String(iso).slice(0, 10).split('-').map(Number)
  if (!a || !m || !d) return '—'
  return `${d} de ${MESES[m - 1]} de ${a}`
}

/** '2026-08-17' -> '17 ago 2026' */
export function fechaCorta(iso) {
  if (!iso) return '—'
  const [a, m, d] = String(iso).slice(0, 10).split('-').map(Number)
  if (!a || !m || !d) return '—'
  return `${d} ${MESES[m - 1].slice(0, 3)} ${a}`
}

export function fechaHora(iso) {
  if (!iso) return '—'
  const f = new Date(String(iso).replace(' ', 'T'))
  if (Number.isNaN(f.getTime())) return '—'
  return `${f.getDate()} ${MESES[f.getMonth()].slice(0, 3)}, ${String(f.getHours()).padStart(2, '0')}:${String(f.getMinutes()).padStart(2, '0')}`
}

/** '07:40:00' -> '7:40' */
export function hora(valor) {
  if (!valor) return ''
  const [h, m] = String(valor).split(':')
  return `${Number(h)}:${m}`
}

export function tiempoRelativo(iso) {
  if (!iso) return ''
  const f = new Date(String(iso).replace(' ', 'T'))
  const segundos = Math.floor((Date.now() - f.getTime()) / 1000)

  if (segundos < 60) return 'hace un momento'
  if (segundos < 3600) return `hace ${Math.floor(segundos / 60)} min`
  if (segundos < 86400) return `hace ${Math.floor(segundos / 3600)} h`
  if (segundos < 604800) return `hace ${Math.floor(segundos / 86400)} d`
  return fechaCorta(iso)
}

/** Etiqueta amable del estado de un periodo. */
export const ESTADOS_PERIODO = {
  PLANIFICACION: {
    etiqueta: 'Por iniciar',
    clase: 'bg-amber-50 text-amber-800 ring-amber-200',
    punto: 'bg-amber-500',
    tarjeta: 'border-amber-200 bg-amber-50/40',
  },
  EN_CURSO: {
    etiqueta: 'En curso',
    clase: 'bg-emerald-50 text-emerald-800 ring-emerald-200',
    punto: 'bg-emerald-500',
    tarjeta: 'border-emerald-200 bg-emerald-50/40',
  },
  FINALIZADO: {
    etiqueta: 'Finalizado',
    clase: 'bg-slate-100 text-slate-600 ring-slate-200',
    punto: 'bg-slate-400',
    tarjeta: 'border-slate-200 bg-slate-50 opacity-90',
  },
}

export const DIAS_CORTOS = {
  LUNES: 'Lun', MARTES: 'Mar', MIERCOLES: 'Mié', JUEVES: 'Jue', SABADO: 'Sáb',
}

export const DIAS_LARGOS = {
  LUNES: 'Lunes', MARTES: 'Martes', MIERCOLES: 'Miércoles', JUEVES: 'Jueves', SABADO: 'Sábado',
}

export const ROLES = {
  ADMIN: { etiqueta: 'Administrador', icono: 'Briefcase' },
  DOCENTE: { etiqueta: 'Docente', icono: 'GraduationCap' },
  ESTUDIANTE: { etiqueta: 'Estudiante', icono: 'BookUser' },
}

/** Días restantes en texto: "en 14 días", "hoy", "hace 5 días". */
export function diasTexto(dias) {
  if (dias === null || dias === undefined) return ''
  const n = Number(dias)
  if (n === 0) return 'hoy'
  if (n === 1) return 'mañana'
  if (n > 1) return `en ${n} días`
  if (n === -1) return 'ayer'
  return `hace ${Math.abs(n)} días`
}

/** Ordena por una clave de texto respetando acentos. */
export function porTexto(clave) {
  return (a, b) => String(a[clave] ?? '').localeCompare(String(b[clave] ?? ''), 'es')
}

/** Agrupa una lista por el valor de una clave. */
export function agrupar(lista, clave) {
  return (lista || []).reduce((acc, item) => {
    const k = typeof clave === 'function' ? clave(item) : item[clave]
    ;(acc[k] ||= []).push(item)
    return acc
  }, {})
}

/** Iniciales para el avatar del navbar. */
export function iniciales(nombre = '') {
  return nombre
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((p) => p[0]?.toUpperCase() || '')
    .join('')
}

/** Descarga en el navegador un CSV generado en cliente. */
export function descargarCsv(nombreArchivo, filas) {
  if (!filas?.length) return
  const columnas = Object.keys(filas[0])
  const escapar = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`
  const contenido = [
    columnas.join(';'),
    ...filas.map((f) => columnas.map((c) => escapar(f[c])).join(';')),
  ].join('\n')

  const blob = new Blob(['﻿' + contenido], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = nombreArchivo
  a.click()
  URL.revokeObjectURL(url)
}

/** Evita disparar una busqueda en cada tecla. */
export function retrasar(fn, ms = 350) {
  let t
  return (...args) => {
    clearTimeout(t)
    t = setTimeout(() => fn(...args), ms)
  }
}

/**
 * Adonde cae cada rol al entrar.
 *
 * El administrador arranca en el panel de periodos; el docente y el
 * alumno directamente en lo que vienen a ver, que es su horario.
 */
export const RUTA_INICIAL = {
  ADMIN: '/',
  DOCENTE: '/horarios',
  ESTUDIANTE: '/mi-horario',
}

/**
 * Rutas que puede abrir cada rol. `null` = sin restriccion.
 *
 * Un patron terminado en `/*` incluye lo que cuelga debajo; sin el, la
 * coincidencia es exacta. Por eso el alumno tiene `/periodos` (el panel
 * con las tarjetas) pero no `/periodos/*`: ve el listado, no el detalle.
 */
const RUTAS_POR_ROL = {
  ADMIN: null,
  DOCENTE: ['/', '/periodos', '/periodos/*', '/horarios/*'],
  ESTUDIANTE: ['/', '/periodos', '/mi-horario/*'],
}

export function rolPuedeVer(rol, ruta) {
  const permitidas = RUTAS_POR_ROL[rol]
  if (permitidas === null) return true
  if (!permitidas) return false

  return permitidas.some((patron) => {
    if (!patron.endsWith('/*')) return ruta === patron
    const base = patron.slice(0, -2)
    return ruta === base || ruta.startsWith(`${base}/`)
  })
}

/**
 * Destino tras iniciar sesion. Se respeta la pagina que el usuario
 * intentaba abrir solo si su rol puede verla; si no, se le manda a su
 * pantalla de siempre en vez de dejarlo en un "no tienes acceso".
 */
export function destinoTrasEntrar(rol, desde) {
  if (desde && rolPuedeVer(rol, desde)) return desde
  return RUTA_INICIAL[rol] ?? '/'
}
