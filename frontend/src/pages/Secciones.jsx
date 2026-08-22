import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { CalendarDays, DoorOpen, LayoutGrid, Pencil, Plus, Trash2, Users } from 'lucide-react'
import api from '../lib/api'
import { useAccion, useDatos } from '../lib/hooks'
import { useAvisos } from '../context/AvisosContext'
import Boton, { BotonIcono } from '../components/ui/Boton'
import { Campo, Select } from '../components/ui/Campos'
import Modal, { Confirmar } from '../components/ui/Modal'
import { Cargando, EstadoVacio, Etiqueta, Tabla, TituloSeccion } from '../components/ui/Datos'

/**
 * Secciones de un periodo. Aqui vive el codigo que el instituto ya usa
 * (SA26-3, PR26-3S...) y el salon base de cada grupo.
 */
export default function Secciones() {
  const { avisar } = useAvisos()
  const [params, setParams] = useSearchParams()

  const { datos: periodos } = useDatos('/periodos', null, { ttl: 60_000 })
  const periodoId = params.get('periodo') || periodos?.find((p) => p.estado !== 'FINALIZADO')?.periodo_id || ''

  const { datos: secciones, cargando, recargar } = useDatos(
    periodoId ? '/secciones' : null,
    { periodo_id: periodoId }
  )
  const { datos: catalogos } = useDatos('/catalogos', null, { ttl: 300_000 })

  const [modal, setModal] = useState(null)
  const [borrar, setBorrar] = useState(null)

  useEffect(() => {
    if (periodoId && !params.get('periodo')) {
      const nuevos = new URLSearchParams(params)
      nuevos.set('periodo', periodoId)
      setParams(nuevos, { replace: true })
    }
  }, [periodoId, params, setParams])

  const periodo = periodos?.find((p) => String(p.periodo_id) === String(periodoId))
  const editable = periodo?.estado === 'PLANIFICACION'

  const { ejecutar: eliminar, enviando: eliminando } = useAccion(
    async (s) => api.del(`/secciones/${s.seccion_id}`),
    {
      alTerminar: () => { avisar.exito('Seccion eliminada.'); setBorrar(null); recargar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const columnas = [
    {
      clave: 'codigo',
      titulo: 'Seccion',
      render: (s) => (
        <span className="inline-flex items-center gap-2">
          <span
            className="flex h-8 items-center rounded-lg px-2 text-xs font-bold text-white"
            style={{ backgroundColor: s.carrera_color }}
          >
            {s.codigo}
          </span>
        </span>
      ),
    },
    { clave: 'carrera', titulo: 'Carrera', render: (s) => <span className="text-slate-700">{s.carrera}</span> },
    { clave: 'semestre', titulo: 'Semestre', render: (s) => <Etiqueta tono="neutro">{s.semestre}° semestre</Etiqueta> },
    {
      clave: 'espacio',
      titulo: 'Salon base',
      render: (s) => (
        <span className="inline-flex items-center gap-1.5 text-slate-600">
          <DoorOpen className="h-3.5 w-3.5 text-slate-400" />
          {s.espacio || 'Sin asignar'}
        </span>
      ),
    },
    {
      clave: 'inscritos',
      titulo: 'Inscritos',
      render: (s) => (
        <span className="inline-flex items-center gap-1.5">
          <Users className="h-3.5 w-3.5 text-slate-400" />
          <span className={Number(s.inscritos) >= Number(s.cupo) ? 'font-semibold text-amber-700' : 'text-slate-700'}>
            {s.inscritos}/{s.cupo}
          </span>
        </span>
      ),
    },
    {
      clave: 'materias_asignadas',
      titulo: 'Materias',
      render: (s) => (
        <Etiqueta tono={Number(s.materias_asignadas) > 0 ? 'exito' : 'aviso'}>
          {s.materias_asignadas}
        </Etiqueta>
      ),
    },
    {
      clave: 'acciones',
      titulo: '',
      alineacion: 'derecha',
      render: (s) => (
        <div className="flex justify-end gap-1">
          <Link
            to={`/horarios?periodo=${periodoId}&seccion=${s.seccion_id}`}
            className="inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-600 transition hover:bg-slate-100"
            title="Ver horario"
          >
            <CalendarDays className="h-4 w-4" />
          </Link>
          <BotonIcono icono={Pencil} titulo="Editar" onClick={() => setModal(s)} />
          {editable && (
            <BotonIcono icono={Trash2} titulo="Eliminar" className="hover:bg-rose-50 hover:text-rose-700" onClick={() => setBorrar(s)} />
          )}
        </div>
      ),
    },
  ]

  return (
    <div>
      <TituloSeccion
        icono={LayoutGrid}
        titulo="Secciones"
        descripcion="Grupos de estudiantes de cada periodo. El codigo identifica modalidad, ano y semestre."
        acciones={
          editable && (
            <Boton icono={Plus} onClick={() => setModal({ periodo_id: periodoId, codigo: '', carrera_id: '', semestre: 1, cupo: 35, espacio_id: '' })}>
              Nueva seccion
            </Boton>
          )
        }
      />

      <div className="mb-4 max-w-sm">
        <Select
          etiqueta="Periodo"
          value={periodoId}
          onChange={(e) => {
            const nuevos = new URLSearchParams(params)
            nuevos.set('periodo', e.target.value)
            setParams(nuevos)
          }}
        >
          {(periodos || []).map((p) => (
            <option key={p.periodo_id} value={p.periodo_id}>
              {p.codigo} — {p.estado === 'EN_CURSO' ? 'en curso' : p.estado === 'PLANIFICACION' ? 'por iniciar' : 'finalizado'}
            </option>
          ))}
        </Select>
      </div>

      {!periodoId ? (
        <Cargando />
      ) : (
        <Tabla
          columnas={columnas}
          filas={secciones}
          claveFila="seccion_id"
          cargando={cargando}
          vacio={
            <EstadoVacio
              icono={LayoutGrid}
              titulo="Este periodo no tiene secciones"
              mensaje="Crea al menos una seccion antes de generar los horarios."
              accion={
                editable && (
                  <Boton icono={Plus} onClick={() => setModal({ periodo_id: periodoId, codigo: '', carrera_id: '', semestre: 1, cupo: 35, espacio_id: '' })}>
                    Nueva seccion
                  </Boton>
                )
              }
            />
          }
        />
      )}

      {modal && (
        <ModalSeccion
          seccion={modal}
          periodoId={periodoId}
          carreras={catalogos?.carreras ?? []}
          espacios={(catalogos?.espacios ?? []).filter((e) => e.tipo === 'SALON')}
          alCerrar={() => setModal(null)}
          alGuardar={() => { setModal(null); recargar() }}
        />
      )}

      <Confirmar
        abierto={Boolean(borrar)}
        alCerrar={() => setBorrar(null)}
        alConfirmar={() => eliminar(borrar)}
        cargando={eliminando}
        titulo={`¿Eliminar la seccion ${borrar?.codigo}?`}
        mensaje="Se borraran tambien sus asignaciones y su horario. No se puede si ya tiene estudiantes inscritos."
      />
    </div>
  )
}

function ModalSeccion({ seccion, periodoId, carreras, espacios, alCerrar, alGuardar }) {
  const { avisar } = useAvisos()
  const editando = Boolean(seccion.seccion_id)
  const [forma, setForma] = useState({
    codigo: seccion.codigo || '',
    carrera_id: seccion.carrera_id || '',
    semestre: seccion.semestre || 1,
    espacio_id: seccion.espacio_id || '',
    cupo: seccion.cupo || 35,
  })

  const { ejecutar, enviando, errores } = useAccion(
    async () => {
      const cuerpo = {
        codigo: forma.codigo.trim().toUpperCase(),
        semestre: Number(forma.semestre),
        cupo: Number(forma.cupo),
        espacio_id: forma.espacio_id ? Number(forma.espacio_id) : undefined,
      }

      if (editando) return api.put(`/secciones/${seccion.seccion_id}`, cuerpo)

      return api.post('/secciones', {
        ...cuerpo,
        periodo_id: Number(periodoId),
        carrera_id: Number(forma.carrera_id),
      })
    },
    {
      alTerminar: () => { avisar.exito(editando ? 'Seccion actualizada.' : 'Seccion creada.'); alGuardar() },
      alFallar: (e) => !e.esValidacion && avisar.error(e.message),
    }
  )

  return (
    <Modal
      abierto
      alCerrar={alCerrar}
      titulo={editando ? `Editar ${seccion.codigo}` : 'Nueva seccion'}
      descripcion="El codigo es el que veran los estudiantes en su constancia."
      pie={
        <>
          <Boton variante="secundario" onClick={alCerrar} disabled={enviando}>Cancelar</Boton>
          <Boton onClick={() => ejecutar()} cargando={enviando}>Guardar</Boton>
        </>
      }
    >
      <div className="space-y-4">
        <Campo
          etiqueta="Codigo de seccion" requerido placeholder="SA26-3"
          ayuda="Formato sugerido: modalidad + ano + semestre. Ejemplo: SA26-3 o PR26-3S."
          value={forma.codigo} error={errores.codigo}
          onChange={(e) => setForma((f) => ({ ...f, codigo: e.target.value.toUpperCase() }))}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <Select
            etiqueta="Carrera" requerido disabled={editando}
            value={forma.carrera_id} error={errores.carrera_id}
            onChange={(e) => setForma((f) => ({ ...f, carrera_id: e.target.value }))}
          >
            <option value="">Selecciona una carrera</option>
            {carreras.map((c) => <option key={c.carrera_id} value={c.carrera_id}>{c.nombre}</option>)}
          </Select>
          <Select
            etiqueta="Semestre" requerido value={forma.semestre} error={errores.semestre}
            onChange={(e) => setForma((f) => ({ ...f, semestre: e.target.value }))}
          >
            {[1, 2, 3, 4, 5, 6].map((s) => <option key={s} value={s}>{s}° semestre</option>)}
          </Select>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Select
            etiqueta="Salon base" ayuda="El generador lo usara siempre que este libre."
            value={forma.espacio_id} error={errores.espacio_id}
            onChange={(e) => setForma((f) => ({ ...f, espacio_id: e.target.value }))}
          >
            <option value="">Sin salon fijo</option>
            {espacios.map((e) => (
              <option key={e.espacio_id} value={e.espacio_id}>{e.codigo} — {e.nombre} ({e.capacidad})</option>
            ))}
          </Select>
          <Campo
            etiqueta="Cupo" type="number" min={5} max={80}
            value={forma.cupo} error={errores.cupo}
            onChange={(e) => setForma((f) => ({ ...f, cupo: e.target.value }))}
          />
        </div>
      </div>
    </Modal>
  )
}
