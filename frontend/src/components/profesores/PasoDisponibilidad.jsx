import { useEffect, useState } from 'react'
import { Check, Clock, Copy, Info, Plus, Trash2, X } from 'lucide-react'
import { cx, DIAS_LARGOS, hora } from '../../lib/utils'
import Boton, { BotonIcono } from '../ui/Boton'
import { Campo } from '../ui/Campos'
import { Etiqueta } from '../ui/Datos'

/* Viernes y domingo estan bloqueados: el instituto no dicta clases esos dias. */
const DIAS = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'SABADO']
const DIAS_BLOQUEADOS = ['VIERNES', 'DOMINGO']

const PLANTILLAS = [
  { nombre: 'Manana completa', inicio: '07:40', fin: '12:45' },
  { nombre: 'Tarde completa', inicio: '12:05', fin: '16:05' },
  { nombre: 'Jornada completa', inicio: '07:40', fin: '16:05' },
  { nombre: 'Sabado manana', inicio: '07:20', fin: '12:10', soloSabado: true },
  { nombre: 'Sabado completo', inicio: '07:20', fin: '16:50', soloSabado: true },
]

/**
 * Paso 2 · Disponibilidad.
 *
 * El docente marca los dias en que puede dar clase y, por cada uno, su
 * hora de entrada y de salida. Se pueden agregar varias franjas al mismo
 * dia (por ejemplo manana y tarde con un hueco al mediodia).
 */
export default function PasoDisponibilidad({ valorInicial = [], alGuardar, guardando }) {
  const [franjas, setFranjas] = useState([])

  useEffect(() => {
    setFranjas(
      (valorInicial || []).map((f) => ({
        dia: f.dia,
        hora_inicio: String(f.hora_inicio).slice(0, 5),
        hora_fin: String(f.hora_fin).slice(0, 5),
      }))
    )
  }, [valorInicial])

  const porDia = DIAS.reduce((acc, dia) => {
    acc[dia] = franjas.filter((f) => f.dia === dia)
    return acc
  }, {})

  function activarDia(dia) {
    const esSabado = dia === 'SABADO'
    setFranjas((prev) => [
      ...prev,
      { dia, hora_inicio: esSabado ? '07:20' : '07:40', hora_fin: esSabado ? '12:10' : '12:45' },
    ])
  }

  function quitarDia(dia) {
    setFranjas((prev) => prev.filter((f) => f.dia !== dia))
  }

  function actualizar(dia, indice, campo, valor) {
    setFranjas((prev) => {
      let vistos = -1
      return prev.map((f) => {
        if (f.dia !== dia) return f
        vistos += 1
        return vistos === indice ? { ...f, [campo]: valor } : f
      })
    })
  }

  function quitarFranja(dia, indice) {
    setFranjas((prev) => {
      let vistos = -1
      return prev.filter((f) => {
        if (f.dia !== dia) return true
        vistos += 1
        return vistos !== indice
      })
    })
  }

  function aplicarPlantilla(plantilla) {
    const dias = plantilla.soloSabado ? ['SABADO'] : DIAS.filter((d) => d !== 'SABADO')
    setFranjas((prev) => [
      ...prev.filter((f) => !dias.includes(f.dia)),
      ...dias.map((dia) => ({ dia, hora_inicio: plantilla.inicio, hora_fin: plantilla.fin })),
    ])
  }

  function copiarPrimero(dia) {
    const origen = franjas.find((f) => f.dia !== dia)
    if (!origen) return
    setFranjas((prev) => [
      ...prev.filter((f) => f.dia !== dia),
      { dia, hora_inicio: origen.hora_inicio, hora_fin: origen.hora_fin },
    ])
  }

  const invalidas = franjas.filter((f) => f.hora_fin <= f.hora_inicio)
  const totalHoras = franjas.reduce((suma, f) => {
    const [hi, mi] = f.hora_inicio.split(':').map(Number)
    const [hf, mf] = f.hora_fin.split(':').map(Number)
    return suma + Math.max(0, hf * 60 + mf - (hi * 60 + mi))
  }, 0) / 60

  return (
    <div className="space-y-5">
      <div className="flex gap-3 rounded-xl bg-marca-50 p-4 text-sm text-marca-900">
        <Info className="mt-0.5 h-4 w-4 shrink-0 text-marca-700" />
        <p className="leading-relaxed">
          Marca los dias en que el docente puede dar clase y su horario de entrada y salida.
          El sistema nunca lo asignara fuera de estas franjas.
          <span className="mt-1 block text-xs text-marca-800">
            Viernes y domingo estan bloqueados porque el instituto no dicta clases esos dias.
          </span>
        </p>
      </div>

      {/* Atajos */}
      <div>
        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Aplicar de una vez</p>
        <div className="flex flex-wrap gap-2">
          {PLANTILLAS.map((p) => (
            <button
              key={p.nombre}
              type="button"
              onClick={() => aplicarPlantilla(p)}
              className="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-700 transition hover:border-marca-300 hover:bg-marca-50"
            >
              {p.nombre}
              <span className="ml-1.5 text-slate-400">{p.inicio}–{p.fin}</span>
            </button>
          ))}
          {franjas.length > 0 && (
            <button
              type="button"
              onClick={() => setFranjas([])}
              className="rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-xs font-medium text-rose-700 transition hover:bg-rose-50"
            >
              Limpiar todo
            </button>
          )}
        </div>
      </div>

      {/* Dias */}
      <div className="grid gap-3 lg:grid-cols-2">
        {DIAS.map((dia) => {
          const activo = porDia[dia].length > 0
          return (
            <div
              key={dia}
              className={cx(
                'rounded-2xl border p-4 transition',
                activo ? 'border-marca-300 bg-marca-50/40' : 'border-slate-200 bg-white'
              )}
            >
              <div className="flex items-center justify-between gap-2">
                <label className="flex cursor-pointer items-center gap-2.5">
                  <span
                    className={cx(
                      'flex h-6 w-6 items-center justify-center rounded-md border-2 transition',
                      activo ? 'border-marca-700 bg-marca-700 text-white' : 'border-slate-300 bg-white'
                    )}
                    onClick={() => (activo ? quitarDia(dia) : activarDia(dia))}
                  >
                    {activo && <Check className="h-3.5 w-3.5" />}
                  </span>
                  <span
                    className={cx('font-semibold', activo ? 'text-marca-900' : 'text-slate-600')}
                    onClick={() => (activo ? quitarDia(dia) : activarDia(dia))}
                  >
                    {DIAS_LARGOS[dia]}
                  </span>
                </label>

                {activo && (
                  <div className="flex gap-1">
                    {franjas.some((f) => f.dia !== dia) && porDia[dia].length === 1 && (
                      <BotonIcono icono={Copy} titulo="Copiar horario de otro dia" onClick={() => copiarPrimero(dia)} />
                    )}
                    <BotonIcono icono={Plus} titulo="Agregar otra franja" onClick={() => activarDia(dia)} />
                  </div>
                )}
              </div>

              {activo && (
                <div className="mt-3 space-y-2">
                  {porDia[dia].map((franja, i) => {
                    const mala = franja.hora_fin <= franja.hora_inicio
                    return (
                      <div key={i} className="flex items-end gap-2">
                        <Campo
                          etiqueta={i === 0 ? 'Entrada' : undefined}
                          type="time"
                          value={franja.hora_inicio}
                          onChange={(e) => actualizar(dia, i, 'hora_inicio', e.target.value)}
                        />
                        <Campo
                          etiqueta={i === 0 ? 'Salida' : undefined}
                          type="time"
                          value={franja.hora_fin}
                          error={mala ? 'Debe ser mayor' : undefined}
                          onChange={(e) => actualizar(dia, i, 'hora_fin', e.target.value)}
                        />
                        <BotonIcono
                          icono={Trash2}
                          titulo="Quitar franja"
                          className="mb-0.5 hover:bg-rose-50 hover:text-rose-700"
                          onClick={() => quitarFranja(dia, i)}
                        />
                      </div>
                    )
                  })}
                </div>
              )}
            </div>
          )
        })}

        {DIAS_BLOQUEADOS.map((dia) => (
          <div key={dia} className="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-4 opacity-70">
            <div className="flex items-center gap-2.5">
              <span className="flex h-6 w-6 items-center justify-center rounded-md border-2 border-slate-200 bg-slate-100 text-slate-400">
                <X className="h-3.5 w-3.5" />
              </span>
              <span className="font-semibold text-slate-400">{DIAS_LARGOS[dia] || dia.charAt(0) + dia.slice(1).toLowerCase()}</span>
              <Etiqueta tono="neutro" className="ml-auto">Sin clases</Etiqueta>
            </div>
          </div>
        ))}
      </div>

      {/* Resumen */}
      <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-wrap items-center gap-2 text-sm">
          <Clock className="h-4 w-4 text-slate-400" />
          <span className="text-slate-600">
            {franjas.length === 0
              ? 'Aun no has marcado ningun dia.'
              : <>Disponible <strong>{totalHoras.toFixed(1)} horas</strong> repartidas en {new Set(franjas.map((f) => f.dia)).size} dia(s).</>}
          </span>
          {franjas.filter((f) => f.dia !== 'SABADO').length > 0 && <Etiqueta tono="marca">Entre semana</Etiqueta>}
          {franjas.some((f) => f.dia === 'SABADO') && <Etiqueta tono="violeta">Sabatino</Etiqueta>}
        </div>

        <Boton
          onClick={() => alGuardar(franjas.map((f) => ({ ...f, hora_inicio: `${f.hora_inicio}:00`, hora_fin: `${f.hora_fin}:00` })))}
          cargando={guardando}
          disabled={franjas.length === 0 || invalidas.length > 0}
        >
          Guardar y continuar
        </Boton>
      </div>

      {invalidas.length > 0 && (
        <p className="text-sm font-medium text-rose-600">
          Hay {invalidas.length} franja(s) donde la salida no es posterior a la entrada.
        </p>
      )}
    </div>
  )
}
