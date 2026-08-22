/**
 * Cliente HTTP de la API.
 *
 * Tres cosas que hace de mas y valen la pena:
 *
 *  1. Deduplicacion: dos componentes que piden lo mismo a la vez
 *     comparten una sola peticion de red.
 *  2. Cache corta para GET (stale-while-revalidate): al volver a una
 *     pantalla se pinta al instante y se refresca por detras. Ademas
 *     evita las rafagas de peticiones repetidas que el backend
 *     rechazaria con 429.
 *  3. Renovacion transparente del token: si un 401 llega con un
 *     refresh token guardado, se renueva y se reintenta una vez.
 */

const BASE = (import.meta.env.VITE_API_URL || 'http://localhost/horarios-iutepi/api').replace(/\/$/, '')

const LLAVE_TOKEN = 'iutepi.token'
const LLAVE_REFRESH = 'iutepi.refresh'
const LLAVE_USUARIO = 'iutepi.usuario'

/* ---------------------------------------------------------------- */
/* Almacenamiento de sesion                                          */
/* ---------------------------------------------------------------- */

export const sesion = {
  token: () => localStorage.getItem(LLAVE_TOKEN),
  refresh: () => localStorage.getItem(LLAVE_REFRESH),
  usuario: () => {
    try {
      return JSON.parse(localStorage.getItem(LLAVE_USUARIO) || 'null')
    } catch {
      return null
    }
  },
  guardar: ({ token, refresh_token, usuario }) => {
    if (token) localStorage.setItem(LLAVE_TOKEN, token)
    if (refresh_token) localStorage.setItem(LLAVE_REFRESH, refresh_token)
    if (usuario) localStorage.setItem(LLAVE_USUARIO, JSON.stringify(usuario))
  },
  limpiar: () => {
    localStorage.removeItem(LLAVE_TOKEN)
    localStorage.removeItem(LLAVE_REFRESH)
    localStorage.removeItem(LLAVE_USUARIO)
    cache.clear()
    enVuelo.clear()
  },
}

/* ---------------------------------------------------------------- */
/* Errores                                                           */
/* ---------------------------------------------------------------- */

export class ErrorApi extends Error {
  constructor(mensaje, { estado = 0, codigo = 'ERROR', detalles = null } = {}) {
    super(mensaje)
    this.name = 'ErrorApi'
    this.estado = estado
    this.codigo = codigo
    this.detalles = detalles
  }

  get esValidacion() {
    return this.codigo === 'VALIDACION'
  }

  get esConflicto() {
    return this.estado === 409
  }

  get esSinConexion() {
    return this.estado === 0 || this.estado === 503
  }
}

/* ---------------------------------------------------------------- */
/* Cache y deduplicacion                                             */
/* ---------------------------------------------------------------- */

const cache = new Map() // url -> { datos, meta, expira }
const enVuelo = new Map() // url -> Promise
const TTL_POR_DEFECTO = 30_000

export function invalidarCache(fragmento = null) {
  if (!fragmento) {
    cache.clear()
    return
  }
  for (const clave of cache.keys()) {
    if (clave.includes(fragmento)) cache.delete(clave)
  }
}

/* ---------------------------------------------------------------- */
/* Avisos globales de conexion                                       */
/* ---------------------------------------------------------------- */

const oyentesConexion = new Set()
let ultimoEstadoConexion = true

export function alCambiarConexion(fn) {
  oyentesConexion.add(fn)
  return () => oyentesConexion.delete(fn)
}

function anunciarConexion(ok, detalle) {
  if (ok === ultimoEstadoConexion) return
  ultimoEstadoConexion = ok
  oyentesConexion.forEach((fn) => fn(ok, detalle))
}

/* ---------------------------------------------------------------- */
/* Renovacion de token                                               */
/* ---------------------------------------------------------------- */

let renovacionEnCurso = null

async function renovarToken() {
  const refresh = sesion.refresh()
  if (!refresh) return false

  if (!renovacionEnCurso) {
    renovacionEnCurso = fetch(`${BASE}/auth/refresh`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ refresh_token: refresh }),
    })
      .then((r) => r.json())
      .then((json) => {
        if (json?.exito && json.datos?.token) {
          sesion.guardar(json.datos)
          return true
        }
        sesion.limpiar()
        return false
      })
      .catch(() => false)
      .finally(() => {
        renovacionEnCurso = null
      })
  }

  return renovacionEnCurso
}

/* ---------------------------------------------------------------- */
/* Peticion base                                                     */
/* ---------------------------------------------------------------- */

async function peticion(metodo, ruta, { cuerpo, params, señal, reintento = false } = {}) {
  const url = new URL(BASE + (ruta.startsWith('/') ? ruta : `/${ruta}`))

  if (params) {
    Object.entries(params).forEach(([clave, valor]) => {
      if (valor !== undefined && valor !== null && valor !== '') {
        url.searchParams.set(clave, valor)
      }
    })
  }

  const cabeceras = { Accept: 'application/json' }
  const token = sesion.token()
  if (token) cabeceras.Authorization = `Bearer ${token}`
  if (cuerpo !== undefined) cabeceras['Content-Type'] = 'application/json'

  let respuesta
  try {
    respuesta = await fetch(url.toString(), {
      method: metodo,
      headers: cabeceras,
      body: cuerpo !== undefined ? JSON.stringify(cuerpo) : undefined,
      signal: señal,
    })
  } catch (e) {
    if (e.name === 'AbortError') throw e
    anunciarConexion(false, 'No se pudo contactar con el servidor.')
    throw new ErrorApi(
      'No hay conexion con el servidor. Revisa tu red o si el servicio esta encendido.',
      { estado: 0, codigo: 'SIN_CONEXION' }
    )
  }

  if (respuesta.status === 204) {
    anunciarConexion(true)
    return { datos: null, meta: null }
  }

  let json = null
  try {
    json = await respuesta.json()
  } catch {
    json = null
  }

  // 401 -> intentar renovar la sesion una sola vez
  if (respuesta.status === 401 && !reintento && sesion.refresh()) {
    if (await renovarToken()) {
      return peticion(metodo, ruta, { cuerpo, params, señal, reintento: true })
    }
  }

  if (!respuesta.ok || json?.exito === false) {
    const error = json?.error || {}

    if (respuesta.status === 503 || respuesta.status === 0) {
      anunciarConexion(false, error.mensaje || 'El servidor no responde.')
    }

    throw new ErrorApi(error.mensaje || `Error ${respuesta.status}`, {
      estado: respuesta.status,
      codigo: error.codigo || 'ERROR',
      detalles: error.detalles || null,
    })
  }

  anunciarConexion(true)
  return { datos: json?.datos ?? null, meta: json?.meta ?? null }
}

/* ---------------------------------------------------------------- */
/* API publica                                                       */
/* ---------------------------------------------------------------- */

/**
 * GET con cache y deduplicacion.
 *
 * A proposito no acepta AbortSignal: la misma promesa se comparte entre
 * todos los componentes que piden la misma URL, y abortarla desde uno
 * cancelaria la peticion de los otros. Quien necesite descartar un
 * resultado tardio debe ignorarlo, no cancelar la red.
 */
async function get(ruta, params, { ttl = TTL_POR_DEFECTO, forzar = false } = {}) {
  const clave = ruta + (params ? '?' + new URLSearchParams(
    Object.entries(params).filter(([, v]) => v !== undefined && v !== null && v !== '')
  ).toString() : '')

  const ahora = Date.now()

  if (!forzar) {
    const guardado = cache.get(clave)
    if (guardado && guardado.expira > ahora) return guardado

    // Misma peticion ya viajando: reutilizarla en vez de duplicarla
    const viajando = enVuelo.get(clave)
    if (viajando) return viajando
  }

  const promesa = peticion('GET', ruta, { params })
    .then((resultado) => {
      const entrada = { ...resultado, expira: Date.now() + ttl }
      if (ttl > 0) cache.set(clave, entrada)
      return entrada
    })
    .finally(() => enVuelo.delete(clave))

  enVuelo.set(clave, promesa)
  return promesa
}

function mutar(metodo) {
  return async (ruta, cuerpo, params) => {
    const resultado = await peticion(metodo, ruta, { cuerpo, params })
    // Cualquier escritura deja la cache obsoleta
    invalidarCache()
    return resultado
  }
}

export const api = {
  get,
  post: mutar('POST'),
  put: mutar('PUT'),
  patch: mutar('PATCH'),
  del: mutar('DELETE'),
  invalidar: invalidarCache,
  base: BASE,
}

export default api
