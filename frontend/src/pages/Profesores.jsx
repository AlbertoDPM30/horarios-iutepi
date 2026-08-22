import { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import {
  BookOpen, CalendarClock, CircleCheck, Clock, Phone, Plus, Search, Sparkles, Trash2,
  TriangleAlert, UserPlus, Users,
} from 'lucide-react'
import api from '../lib/api'
import { useAccion, useDatos, useRetraso } from '../lib/hooks'
import { useAvisos } from '../context/AvisosContext'
import Boton, { BotonIcono } from '../components/ui/Boton'
import { Campo, Select } from '../components/ui/Campos'
import { Confirmar } from '../components/ui/Modal'
import { EstadoVacio, Etiqueta, Paginacion, Tabla, TituloSeccion } from '../components/ui/Datos'
import { cx } from '../lib/utils'

const CONTRATOS = {
  TIEMPO_COMPLETO: 'Tiempo completo',
  MEDIO_TIEMPO: 'Medio tiempo',
  POR_HORAS: 'Por horas',
}

const PASOS = ['Datos', 'Disponibilidad', 'Skills', 'Materias', 'Horario']

/** Listado de docentes con el avance de su registro por pasos. */
export default function Profesores() {
  const { avisar } = useAvisos()
  const navegar = useNavigate()

  const [pagina, setPagina] = useState(1)
  const [busqueda, setBusqueda] = useState('')
  const [contrato, setContrato] = useState('')
  const [soloIncompletos, setSoloIncompletos] = useState(false)
  const busquedaRetrasada = useRetraso(busqueda)

  const filtros = useMemo(
    () => ({
      pagina,
      por_pagina: 25,
      buscar: busquedaRetrasada || undefined,
      tipo_contrato: contrato || undefined,
      incompletos: soloIncompletos ? 1 : undefined,
    }),
    [pagina, busquedaRetrasada, contrato, soloIncompletos]
  )

  const { datos: profesores, meta, cargando, recargar } = useDatos('/profesores', filtros)
  const [borrar, setBorrar] = useState(null)

  const { ejecutar: eliminar, enviando: eliminando } = useAccion(
    async (p) => api.del(`/profesores/${p.profesor_id}`),
    {
      alTerminar: (r) => { avisar.exito(r.datos?.mensaje || 'Docente eliminado.'); setBorrar(null); recargar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const columnas = [
    {
      clave: 'nombre_completo',
      titulo: 'Docente',
      nowrap: false,
      render: (p) => (
        <div className="min-w-0">
          <p className="font-medium text-slate-900">{p.nombre_completo}</p>
          <p className="text-xs text-slate-500">{p.cedula} · {p.titulo || 'sin titulo registrado'}</p>
        </div>
      ),
    },
    {
      clave: 'tipo_contrato',
      titulo: 'Contrato',
      render: (p) => (
        <span className="inline-flex flex-col gap-1">
          <Etiqueta tono={p.tipo_contrato === 'TIEMPO_COMPLETO' ? 'exito' : p.tipo_contrato === 'MEDIO_TIEMPO' ? 'info' : 'neutro'}>
            {CONTRATOS[p.tipo_contrato]}
          </Etiqueta>
          <span className="text-xs text-slate-500">hasta {p.max_bloques_semana} bloques</span>
        </span>
      ),
    },
    {
      clave: 'perfil',
      titulo: 'Perfil cargado',
      nowrap: false,
      render: (p) => (
        <div className="flex flex-wrap gap-1.5">
          <Etiqueta tono={Number(p.total_disponibilidad) ? 'exito' : 'aviso'} icono={Clock}>
            {p.total_disponibilidad} franjas
          </Etiqueta>
          <Etiqueta tono={Number(p.total_habilidades) ? 'exito' : 'aviso'} icono={Sparkles}>
            {p.total_habilidades} skills
          </Etiqueta>
          <Etiqueta tono={Number(p.total_materias) ? 'exito' : 'aviso'} icono={BookOpen}>
            {p.total_materias} materias
          </Etiqueta>
        </div>
      ),
    },
    {
      clave: 'paso_registro',
      titulo: 'Registro',
      render: (p) => <BarraPasos paso={Number(p.paso_registro)} />,
    },
    {
      clave: 'bloques_asignados',
      titulo: 'Carga',
      render: (p) => (
        <Etiqueta tono={Number(p.bloques_asignados) > 0 ? 'marca' : 'neutro'} icono={CalendarClock}>
          {p.bloques_asignados} bloques
        </Etiqueta>
      ),
    },
    {
      clave: 'telefono',
      titulo: 'Contacto',
      ocultarEnMovil: true,
      render: (p) =>
        p.telefono ? (
          <span className="inline-flex items-center gap-1.5 text-xs text-slate-600">
            <Phone className="h-3.5 w-3.5 text-slate-400" />
            {p.telefono}
          </span>
        ) : (
          <span className="text-xs text-slate-400">—</span>
        ),
    },
    {
      clave: 'acciones',
      titulo: '',
      alineacion: 'derecha',
      render: (p) => (
        <div className="flex justify-end gap-1">
          <BotonIcono
            icono={Trash2}
            titulo="Eliminar"
            className="hover:bg-rose-50 hover:text-rose-700"
            onClick={(e) => { e.stopPropagation(); setBorrar(p) }}
          />
        </div>
      ),
    },
  ]

  const incompletos = (profesores || []).filter((p) => Number(p.paso_registro) < 5).length

  return (
    <div>
      <TituloSeccion
        icono={Users}
        titulo="Docentes"
        descripcion="Datos, disponibilidad y habilidades. Con esto el sistema asigna materias y arma los horarios solo."
        acciones={
          <Link to="/profesores/nuevo">
            <Boton icono={UserPlus}>Nuevo docente</Boton>
          </Link>
        }
      />

      {incompletos > 0 && (
        <div className="mb-4 flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4">
          <TriangleAlert className="mt-0.5 h-5 w-5 shrink-0 text-amber-600" />
          <div>
            <p className="text-sm font-semibold text-amber-900">
              {incompletos} docente(s) con el registro a medias
            </p>
            <p className="text-sm text-amber-800">
              Sin disponibilidad o habilidades cargadas no se les puede asignar materias automaticamente.
            </p>
            <button
              type="button"
              onClick={() => { setSoloIncompletos(true); setPagina(1) }}
              className="mt-1.5 text-sm font-semibold text-amber-900 underline underline-offset-2"
            >
              Ver solo los incompletos
            </button>
          </div>
        </div>
      )}

      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Campo
          placeholder="Buscar por nombre, cedula o correo"
          icono={Search}
          value={busqueda}
          onChange={(e) => { setBusqueda(e.target.value); setPagina(1) }}
        />
        <Select value={contrato} onChange={(e) => { setContrato(e.target.value); setPagina(1) }}>
          <option value="">Todos los contratos</option>
          {Object.entries(CONTRATOS).map(([v, t]) => <option key={v} value={v}>{t}</option>)}
        </Select>
        {soloIncompletos && (
          <Boton variante="secundario" onClick={() => setSoloIncompletos(false)}>
            Quitar filtro de incompletos
          </Boton>
        )}
      </div>

      <Tabla
        columnas={columnas}
        filas={profesores}
        claveFila="profesor_id"
        cargando={cargando}
        alHacerClic={(p) => navegar(`/profesores/${p.profesor_id}`)}
        vacio={
          <EstadoVacio
            icono={Users}
            titulo="Aun no hay docentes"
            mensaje="Registra al primer docente para que el sistema pueda asignar materias."
            accion={
              <Link to="/profesores/nuevo">
                <Boton icono={Plus}>Nuevo docente</Boton>
              </Link>
            }
          />
        }
      />

      <Paginacion pagina={meta?.pagina ?? 1} paginas={meta?.paginas ?? 1} total={meta?.total} alCambiar={setPagina} />

      <Confirmar
        abierto={Boolean(borrar)}
        alCerrar={() => setBorrar(null)}
        alConfirmar={() => eliminar(borrar)}
        cargando={eliminando}
        titulo={`¿Eliminar a ${borrar?.nombre_completo}?`}
        mensaje="Si tiene materias asignadas en periodos vigentes, se desactivara en lugar de borrarse."
      />
    </div>
  )
}

/** Barrita con los 5 pasos del alta de docente. */
function BarraPasos({ paso }) {
  const completo = paso >= 5

  return (
    <div className="flex items-center gap-2">
      <div className="flex gap-0.5">
        {PASOS.map((nombre, i) => (
          <span
            key={nombre}
            title={`${i + 1}. ${nombre}`}
            className={cx(
              'h-1.5 w-4 rounded-full',
              i < paso ? (completo ? 'bg-emerald-500' : 'bg-marca-600') : 'bg-slate-200'
            )}
          />
        ))}
      </div>
      {completo ? (
        <CircleCheck className="h-4 w-4 text-emerald-600" />
      ) : (
        <span className="text-xs font-medium text-amber-700">{paso}/5</span>
      )}
    </div>
  )
}
