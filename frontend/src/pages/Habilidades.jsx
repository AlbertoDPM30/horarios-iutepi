import { useState } from 'react'
import {
  Briefcase, CircuitBoard, Code2, Database, MonitorSmartphone, Network, Pencil, Plus,
  Sigma, Sparkles, Trash2,
} from 'lucide-react'
import api from '../lib/api'
import { useAccion, useDatos } from '../lib/hooks'
import { useAvisos } from '../context/AvisosContext'
import Boton, { BotonIcono } from '../components/ui/Boton'
import { Campo, Select } from '../components/ui/Campos'
import Modal, { Confirmar } from '../components/ui/Modal'
import { Cargando, EstadoVacio, Etiqueta, Tarjeta, TituloSeccion } from '../components/ui/Datos'

const ICONOS = { Code2, Database, Network, Sigma, Briefcase, CircuitBoard, BookOpen: Sparkles, MonitorSmartphone }

/**
 * Catalogo de habilidades agrupadas por categoria.
 *
 * Es la pieza que hace posible la asignacion automatica: las materias
 * exigen habilidades con un minimo de estrellas y los docentes declaran
 * las suyas; el cruce de ambas produce la afinidad.
 */
export default function Habilidades() {
  const { avisar } = useAvisos()
  const { datos: categorias, cargando, recargar } = useDatos('/habilidades', null, { ttl: 60_000 })

  const [modal, setModal] = useState(null)
  const [borrar, setBorrar] = useState(null)

  const { ejecutar: eliminar, enviando: eliminando } = useAccion(
    async (h) => api.del(`/habilidades/${h.habilidad_id}`),
    {
      alTerminar: (r) => { avisar.exito(r.datos?.mensaje || 'Habilidad eliminada.'); setBorrar(null); recargar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  if (cargando) return <Cargando texto="Cargando habilidades..." />

  const total = (categorias || []).reduce((n, c) => n + c.habilidades.length, 0)

  return (
    <div>
      <TituloSeccion
        icono={Sparkles}
        titulo="Habilidades"
        descripcion={`${total} competencias en ${categorias?.length ?? 0} categorias. Son la base para asignar docentes a materias.`}
        acciones={<Boton icono={Plus} onClick={() => setModal({ categoria_id: '', nombre: '', descripcion: '' })}>Nueva habilidad</Boton>}
      />

      {!categorias?.length ? (
        <EstadoVacio
          icono={Sparkles}
          titulo="Aun no hay habilidades"
          mensaje="Sin habilidades el sistema no puede proponer docentes automaticamente."
        />
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          {categorias.map((cat) => {
            const Icono = ICONOS[cat.icono] || Sparkles
            return (
              <Tarjeta key={cat.categoria_id}>
                <div className="mb-3 flex items-center gap-3">
                  <span className="flex h-10 w-10 items-center justify-center rounded-xl bg-marca-50 text-marca-800">
                    <Icono className="h-5 w-5" />
                  </span>
                  <div className="min-w-0 flex-1">
                    <h3 className="font-titulo text-base font-semibold text-slate-900">{cat.nombre}</h3>
                    <p className="text-xs text-slate-500">{cat.habilidades.length} habilidades</p>
                  </div>
                </div>

                <ul className="divide-y divide-slate-100">
                  {cat.habilidades.map((h) => (
                    <li key={h.habilidad_id} className="flex items-center gap-2 py-2">
                      <span className="min-w-0 flex-1">
                        <span className="block truncate text-sm font-medium text-slate-800">{h.nombre}</span>
                        <span className="mt-0.5 flex flex-wrap gap-1.5">
                          <Etiqueta tono={Number(h.docentes) ? 'exito' : 'neutro'}>{h.docentes} docentes</Etiqueta>
                          <Etiqueta tono={Number(h.materias) ? 'marca' : 'neutro'}>{h.materias} materias</Etiqueta>
                        </span>
                      </span>
                      <BotonIcono icono={Pencil} titulo="Editar" onClick={() => setModal({ ...h })} />
                      <BotonIcono
                        icono={Trash2}
                        titulo="Eliminar"
                        className="hover:bg-rose-50 hover:text-rose-700"
                        onClick={() => setBorrar(h)}
                      />
                    </li>
                  ))}
                </ul>
              </Tarjeta>
            )
          })}
        </div>
      )}

      {modal && (
        <ModalHabilidad
          habilidad={modal}
          categorias={categorias || []}
          alCerrar={() => setModal(null)}
          alGuardar={() => { setModal(null); recargar() }}
        />
      )}

      <Confirmar
        abierto={Boolean(borrar)}
        alCerrar={() => setBorrar(null)}
        alConfirmar={() => eliminar(borrar)}
        cargando={eliminando}
        titulo={`¿Eliminar "${borrar?.nombre}"?`}
        mensaje="Si la habilidad esta en uso por alguna materia o docente, se desactivara en lugar de borrarse."
      />
    </div>
  )
}

function ModalHabilidad({ habilidad, categorias, alCerrar, alGuardar }) {
  const { avisar } = useAvisos()
  const editando = Boolean(habilidad.habilidad_id)
  const [forma, setForma] = useState({
    categoria_id: habilidad.categoria_id || '',
    nombre: habilidad.nombre || '',
    descripcion: habilidad.descripcion || '',
  })

  const { ejecutar, enviando, errores } = useAccion(
    async () => {
      const cuerpo = {
        categoria_id: Number(forma.categoria_id),
        nombre: forma.nombre.trim(),
        descripcion: forma.descripcion.trim(),
      }
      return editando
        ? api.put(`/habilidades/${habilidad.habilidad_id}`, cuerpo)
        : api.post('/habilidades', cuerpo)
    },
    {
      alTerminar: () => { avisar.exito(editando ? 'Habilidad actualizada.' : 'Habilidad creada.'); alGuardar() },
      alFallar: (e) => !e.esValidacion && avisar.error(e.message),
    }
  )

  return (
    <Modal
      abierto
      alCerrar={alCerrar}
      titulo={editando ? 'Editar habilidad' : 'Nueva habilidad'}
      pie={
        <>
          <Boton variante="secundario" onClick={alCerrar} disabled={enviando}>Cancelar</Boton>
          <Boton onClick={() => ejecutar()} cargando={enviando}>Guardar</Boton>
        </>
      }
    >
      <div className="space-y-4">
        <Select
          etiqueta="Categoria" requerido value={forma.categoria_id} error={errores.categoria_id}
          onChange={(e) => setForma((f) => ({ ...f, categoria_id: e.target.value }))}
        >
          <option value="">Selecciona una categoria</option>
          {categorias.map((c) => (
            <option key={c.categoria_id} value={c.categoria_id}>{c.nombre}</option>
          ))}
        </Select>
        <Campo
          etiqueta="Nombre" requerido placeholder="Bases de datos relacionales"
          value={forma.nombre} error={errores.nombre}
          onChange={(e) => setForma((f) => ({ ...f, nombre: e.target.value }))}
        />
        <Campo
          etiqueta="Descripcion (opcional)"
          value={forma.descripcion} error={errores.descripcion}
          onChange={(e) => setForma((f) => ({ ...f, descripcion: e.target.value }))}
        />
      </div>
    </Modal>
  )
}
