import { useCallback, useEffect, useRef, useState } from 'react'
import api from './api'

/**
 * Carga de datos con estados de carga/error y recarga manual.
 *
 *   const { datos, meta, cargando, error, recargar } = useDatos('/materias', { semestre: 3 })
 *
 * Pasar `null` como ruta pospone la peticion (util cuando aun falta un
 * parametro, por ejemplo el periodo seleccionado).
 */
export function useDatos(ruta, params = null, opciones = {}) {
  const { ttl = 30_000, activo = true } = opciones

  const [datos, setDatos] = useState(null)
  const [meta, setMeta] = useState(null)
  const [cargando, setCargando] = useState(Boolean(ruta) && activo)
  const [error, setError] = useState(null)

  const clave = JSON.stringify([ruta, params])

  // Contador de generacion: si llega la respuesta de una peticion que ya
  // quedo obsoleta (cambio de filtro, desmontaje), se descarta en vez de
  // abortarla, porque la peticion se comparte con otros componentes.
  const generacion = useRef(0)

  const cargar = useCallback(
    async ({ forzar = false } = {}) => {
      if (!ruta || !activo) {
        setCargando(false)
        return
      }

      const propia = ++generacion.current

      setCargando(true)
      setError(null)

      try {
        const resultado = await api.get(ruta, params, { ttl, forzar })
        if (propia !== generacion.current) return
        setDatos(resultado.datos)
        setMeta(resultado.meta)
      } catch (e) {
        if (propia !== generacion.current) return
        setError(e)
      } finally {
        if (propia === generacion.current) setCargando(false)
      }
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [clave, ttl, activo]
  )

  useEffect(() => {
    cargar()
  }, [cargar])

  // Al desmontar, invalidar cualquier respuesta que siga en camino
  useEffect(() => () => {
    generacion.current += 1
  }, [])

  const recargar = useCallback(() => cargar({ forzar: true }), [cargar])

  return { datos, meta, cargando, error, recargar, setDatos }
}

/**
 * Ejecuta una accion de escritura controlando el estado de envio y los
 * errores de validacion que devuelve la API.
 */
export function useAccion(fn, { alTerminar, alFallar } = {}) {
  const [enviando, setEnviando] = useState(false)
  const [errores, setErrores] = useState({})

  const ejecutar = useCallback(
    async (...args) => {
      setEnviando(true)
      setErrores({})
      try {
        const resultado = await fn(...args)
        alTerminar?.(resultado)
        return resultado
      } catch (e) {
        if (e.esValidacion && e.detalles) setErrores(e.detalles)
        alFallar?.(e)
        throw e
      } finally {
        setEnviando(false)
      }
    },
    [fn, alTerminar, alFallar]
  )

  return { ejecutar, enviando, errores, setErrores }
}

/** Valor que se actualiza solo cuando el usuario deja de escribir. */
export function useRetraso(valor, ms = 350) {
  const [retrasado, setRetrasado] = useState(valor)

  useEffect(() => {
    const t = setTimeout(() => setRetrasado(valor), ms)
    return () => clearTimeout(t)
  }, [valor, ms])

  return retrasado
}

/** Estado persistido en localStorage (periodo elegido, filtros, etc.). */
export function useLocal(clave, inicial) {
  const [valor, setValor] = useState(() => {
    try {
      const guardado = localStorage.getItem(clave)
      return guardado !== null ? JSON.parse(guardado) : inicial
    } catch {
      return inicial
    }
  })

  useEffect(() => {
    try {
      localStorage.setItem(clave, JSON.stringify(valor))
    } catch {
      // Modo privado o cuota llena: seguimos con el valor en memoria
    }
  }, [clave, valor])

  return [valor, setValor]
}
