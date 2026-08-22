import { useEffect, useState } from 'react'
import { CalendarDays, CalendarRange, Info } from 'lucide-react'
import api from '../lib/api'
import { useAvisos } from '../context/AvisosContext'
import { useAccion } from '../lib/hooks'
import Boton from './ui/Boton'
import { Campo, OpcionesTarjeta, Select } from './ui/Campos'
import Modal from './ui/Modal'
import { fechaLarga } from '../lib/utils'

const MODALIDADES = [
  { valor: 'SEMANA', etiqueta: 'Entre semana', descripcion: 'Lunes a jueves', icono: CalendarRange },
  { valor: 'SABATINO', etiqueta: 'Sabatino', descripcion: 'Solo sabados', icono: CalendarDays },
]

/** Suma semanas a una fecha ISO y devuelve la fecha de cierre. */
function calcularCierre(inicio, semanas) {
  if (!inicio) return ''
  const f = new Date(`${inicio}T12:00:00`)
  f.setDate(f.getDate() + semanas * 7 - 1)
  return f.toISOString().slice(0, 10)
}

/**
 * Alta y edicion de periodos.
 *
 * La fecha de cierre se calcula sola a partir de la duracion (20 semanas
 * por defecto, en 2 modulos de 10) pero queda editable, porque en la
 * practica los periodos se extienden.
 */
export default function ModalPeriodo({ abierto, alCerrar, alGuardar, periodo = null }) {
  const { avisar } = useAvisos()
  const editando = Boolean(periodo)

  const [forma, setForma] = useState({
    codigo: '', modalidad: 'SEMANA', fecha_inicio: '', semanas: 20, modulos: 2, fecha_fin: '', nombre: '',
  })
  const [cierreManual, setCierreManual] = useState(false)

  useEffect(() => {
    if (!abierto) return

    if (periodo) {
      setForma({
        codigo: periodo.codigo,
        modalidad: periodo.modalidad,
        fecha_inicio: String(periodo.fecha_inicio).slice(0, 10),
        semanas: Number(periodo.semanas),
        modulos: periodo.modulos?.length || 2,
        fecha_fin: String(periodo.fecha_fin).slice(0, 10),
        nombre: periodo.nombre,
      })
      setCierreManual(true)
    } else {
      const hoy = new Date().toISOString().slice(0, 10)
      setForma({
        codigo: '', modalidad: 'SEMANA', fecha_inicio: hoy, semanas: 20, modulos: 2,
        fecha_fin: calcularCierre(hoy, 20), nombre: '',
      })
      setCierreManual(false)
    }
  }, [abierto, periodo])

  // Recalcular el cierre mientras el usuario no lo toque a mano
  useEffect(() => {
    if (cierreManual) return
    setForma((f) => ({ ...f, fecha_fin: calcularCierre(f.fecha_inicio, f.semanas) }))
  }, [forma.fecha_inicio, forma.semanas, cierreManual])

  const { ejecutar, enviando, errores } = useAccion(
    async () => {
      const cuerpo = {
        codigo: forma.codigo.trim().toUpperCase(),
        modalidad: forma.modalidad,
        fecha_inicio: forma.fecha_inicio,
        fecha_fin: forma.fecha_fin,
        semanas: Number(forma.semanas),
        modulos: Number(forma.modulos),
      }
      if (forma.nombre.trim()) cuerpo.nombre = forma.nombre.trim()

      if (editando) {
        delete cuerpo.codigo
        delete cuerpo.modalidad
        delete cuerpo.modulos
        return api.put(`/periodos/${periodo.periodo_id}`, cuerpo)
      }
      return api.post('/periodos', cuerpo)
    },
    {
      alTerminar: () => {
        avisar.exito(editando ? 'Periodo actualizado.' : 'Periodo creado. Ya puedes cargar sus secciones.')
        alGuardar?.()
      },
      alFallar: (e) => !e.esValidacion && avisar.error(e.message),
    }
  )

  const semanasPorModulo = Math.round(Number(forma.semanas) / Math.max(1, Number(forma.modulos)))

  return (
    <Modal
      abierto={abierto}
      alCerrar={alCerrar}
      titulo={editando ? `Editar ${periodo.codigo}` : 'Nuevo periodo academico'}
      descripcion={
        editando
          ? 'Los periodos en curso solo admiten extender la fecha de cierre.'
          : 'Define las fechas y el sistema arma los modulos automaticamente.'
      }
      ancho="lg"
      pie={
        <>
          <Boton variante="secundario" onClick={alCerrar} disabled={enviando}>Cancelar</Boton>
          <Boton onClick={() => ejecutar()} cargando={enviando}>
            {editando ? 'Guardar cambios' : 'Crear periodo'}
          </Boton>
        </>
      }
    >
      <div className="space-y-5">
        {!editando && (
          <>
            <div className="grid gap-4 sm:grid-cols-2">
              <Campo
                etiqueta="Codigo del periodo"
                requerido
                placeholder="PR26-3"
                ayuda="PR para entre semana, SA para sabatino, mas el ano y el numero."
                value={forma.codigo}
                error={errores.codigo}
                onChange={(e) => setForma((f) => ({ ...f, codigo: e.target.value.toUpperCase() }))}
              />
              <Campo
                etiqueta="Nombre (opcional)"
                placeholder="Se genera solo si lo dejas vacio"
                value={forma.nombre}
                error={errores.nombre}
                onChange={(e) => setForma((f) => ({ ...f, nombre: e.target.value }))}
              />
            </div>

            <div>
              <p className="mb-2 text-sm font-medium text-slate-700">Modalidad</p>
              <OpcionesTarjeta
                columnas={2}
                opciones={MODALIDADES}
                valor={forma.modalidad}
                onChange={(v) => setForma((f) => ({ ...f, modalidad: v }))}
              />
            </div>
          </>
        )}

        <div className="grid gap-4 sm:grid-cols-3">
          <Campo
            etiqueta="Fecha de inicio"
            type="date"
            requerido
            disabled={editando && periodo?.estado === 'EN_CURSO'}
            value={forma.fecha_inicio}
            error={errores.fecha_inicio}
            onChange={(e) => setForma((f) => ({ ...f, fecha_inicio: e.target.value }))}
          />
          <Campo
            etiqueta="Duracion (semanas)"
            type="number"
            min={4}
            max={40}
            value={forma.semanas}
            error={errores.semanas}
            onChange={(e) => {
              setCierreManual(false)
              setForma((f) => ({ ...f, semanas: e.target.value }))
            }}
          />
          <Campo
            etiqueta="Fecha de cierre"
            type="date"
            ayuda={editando ? 'Solo se puede extender.' : 'Se calcula sola; puedes ajustarla.'}
            value={forma.fecha_fin}
            error={errores.fecha_fin}
            onChange={(e) => {
              setCierreManual(true)
              setForma((f) => ({ ...f, fecha_fin: e.target.value }))
            }}
          />
        </div>

        {!editando && (
          <Select
            etiqueta="Modulos del periodo"
            value={forma.modulos}
            error={errores.modulos}
            onChange={(e) => setForma((f) => ({ ...f, modulos: e.target.value }))}
          >
            <option value={1}>1 modulo (el periodo completo)</option>
            <option value={2}>2 modulos (lo habitual)</option>
            <option value={3}>3 modulos</option>
            <option value={4}>4 modulos</option>
          </Select>
        )}

        <div className="flex gap-3 rounded-xl bg-marca-50 p-3.5 text-sm text-marca-900">
          <Info className="mt-0.5 h-4 w-4 shrink-0 text-marca-700" />
          <p className="leading-relaxed">
            {forma.fecha_inicio && forma.fecha_fin ? (
              <>
                El periodo ira del <strong>{fechaLarga(forma.fecha_inicio)}</strong> al{' '}
                <strong>{fechaLarga(forma.fecha_fin)}</strong>
                {!editando && (
                  <>
                    , repartido en <strong>{forma.modulos} modulo(s)</strong> de unas {semanasPorModulo} semanas cada uno
                  </>
                )}
                .
              </>
            ) : (
              'Elige la fecha de inicio para ver el resumen del periodo.'
            )}
          </p>
        </div>
      </div>
    </Modal>
  )
}
