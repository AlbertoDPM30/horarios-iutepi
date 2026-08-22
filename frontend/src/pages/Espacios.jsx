import { useMemo, useState } from 'react'
import {
  Airplay, Building2, CalendarDays, DoorOpen, FlaskConical, Monitor, Pencil, Plus, Search,
  Server, Trash2, Users, Wifi,
} from 'lucide-react'
import api from '../lib/api'
import { useAccion, useDatos, useRetraso } from '../lib/hooks'
import { useAvisos } from '../context/AvisosContext'
import Boton, { BotonIcono } from '../components/ui/Boton'
import { Campo, Interruptor, Select } from '../components/ui/Campos'
import Modal, { Confirmar } from '../components/ui/Modal'
import { EstadoVacio, Etiqueta, Paginacion, Tabla, TituloSeccion } from '../components/ui/Datos'

const CONFIG = {
  salones: {
    titulo: 'Salones',
    singular: 'salon',
    descripcion: 'Aulas de clase. Cada seccion tiene un salon base y el generador lo respeta siempre que este libre.',
    icono: DoorOpen,
    ruta: '/salones',
    forma: { codigo: '', nombre: '', capacidad: 35, edificio: '', piso: 1, pupitres: 35, tiene_proyector: false, tiene_aire: true, tiene_pizarra_digital: false },
  },
  laboratorios: {
    titulo: 'Laboratorios',
    singular: 'laboratorio',
    descripcion: 'Las materias marcadas como de laboratorio solo se ubican aqui.',
    icono: FlaskConical,
    ruta: '/laboratorios',
    forma: { codigo: '', nombre: '', capacidad: 25, edificio: '', piso: 1, puestos: 20, sistema_operativo: 'Windows 10', software: '', tiene_servidor: false, tiene_internet: true, especialidad: 'SISTEMAS' },
  },
}

const ESPECIALIDADES = {
  SISTEMAS: 'Sistemas', REDES: 'Redes', ELECTRONICA: 'Electronica', MIXTO: 'Mixto',
}

/** CRUD de salones y laboratorios; comparten pantalla y cambian los campos propios. */
export default function Espacios({ tipo }) {
  const cfg = CONFIG[tipo]
  const { avisar } = useAvisos()

  const [pagina, setPagina] = useState(1)
  const [busqueda, setBusqueda] = useState('')
  const busquedaRetrasada = useRetraso(busqueda)

  const filtros = useMemo(
    () => ({ pagina, por_pagina: 24, buscar: busquedaRetrasada || undefined }),
    [pagina, busquedaRetrasada]
  )

  const { datos: espacios, meta, cargando, recargar } = useDatos(cfg.ruta, filtros)
  const [modal, setModal] = useState(null)
  const [borrar, setBorrar] = useState(null)

  const { ejecutar: eliminar, enviando: eliminando } = useAccion(
    async (e) => api.del(`${cfg.ruta}/${e.espacio_id}`),
    {
      alTerminar: (r) => { avisar.exito(r.datos?.mensaje || 'Espacio eliminado.'); setBorrar(null); recargar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const columnas = [
    {
      clave: 'codigo',
      titulo: 'Codigo',
      render: (e) => (
        <span className="inline-flex items-center gap-2">
          <span className="inline-flex h-7 min-w-[2.75rem] items-center justify-center rounded-md bg-marca-50 px-2 text-[0.7rem] font-bold tracking-wide text-marca-800">
            {e.codigo}
          </span>
          {Number(e.activo) === 0 && <Etiqueta tono="neutro">Inactivo</Etiqueta>}
        </span>
      ),
    },
    { clave: 'nombre', titulo: 'Nombre', nowrap: false, render: (e) => <span className="font-medium text-slate-800">{e.nombre}</span> },
    {
      clave: 'ubicacion',
      titulo: 'Ubicacion',
      render: (e) => (
        <span className="inline-flex items-center gap-1.5 text-slate-600">
          <Building2 className="h-3.5 w-3.5 text-slate-400" />
          {e.edificio || '—'}{e.piso ? `, piso ${e.piso}` : ''}
        </span>
      ),
    },
    {
      clave: 'capacidad',
      titulo: tipo === 'salones' ? 'Capacidad' : 'Puestos',
      render: (e) => (
        <span className="inline-flex items-center gap-1.5 text-slate-600">
          <Users className="h-3.5 w-3.5 text-slate-400" />
          {tipo === 'salones' ? e.capacidad : e.puestos}
        </span>
      ),
    },
    {
      clave: 'detalle',
      titulo: 'Caracteristicas',
      nowrap: false,
      render: (e) =>
        tipo === 'salones' ? (
          <span className="flex flex-wrap gap-1.5">
            {Number(e.tiene_proyector) === 1 && <Etiqueta tono="info" icono={Airplay}>Proyector</Etiqueta>}
            {Number(e.tiene_aire) === 1 && <Etiqueta tono="neutro">Aire</Etiqueta>}
            {Number(e.tiene_pizarra_digital) === 1 && <Etiqueta tono="violeta">Pizarra digital</Etiqueta>}
          </span>
        ) : (
          <span className="flex flex-wrap gap-1.5">
            <Etiqueta tono="marca">{ESPECIALIDADES[e.especialidad]}</Etiqueta>
            {Number(e.tiene_servidor) === 1 && <Etiqueta tono="violeta" icono={Server}>Servidor</Etiqueta>}
            {Number(e.tiene_internet) === 1 && <Etiqueta tono="exito" icono={Wifi}>Internet</Etiqueta>}
          </span>
        ),
    },
    {
      clave: 'bloques_ocupados',
      titulo: 'Uso',
      render: (e) => (
        <Etiqueta tono={Number(e.bloques_ocupados) > 0 ? 'exito' : 'neutro'} icono={CalendarDays}>
          {e.bloques_ocupados} bloques
        </Etiqueta>
      ),
    },
    {
      clave: 'acciones',
      titulo: '',
      alineacion: 'derecha',
      render: (e) => (
        <div className="flex justify-end gap-1">
          <BotonIcono icono={Pencil} titulo="Editar" onClick={() => setModal(e)} />
          <BotonIcono icono={Trash2} titulo="Eliminar" className="hover:bg-rose-50 hover:text-rose-700" onClick={() => setBorrar(e)} />
        </div>
      ),
    },
  ]

  return (
    <div>
      <TituloSeccion
        icono={cfg.icono}
        titulo={cfg.titulo}
        descripcion={cfg.descripcion}
        acciones={<Boton icono={Plus} onClick={() => setModal({ ...cfg.forma })}>Nuevo {cfg.singular}</Boton>}
      />

      <div className="mb-4 max-w-sm">
        <Campo
          placeholder={`Buscar ${cfg.singular}`}
          icono={Search}
          value={busqueda}
          onChange={(e) => { setBusqueda(e.target.value); setPagina(1) }}
        />
      </div>

      <Tabla
        columnas={columnas}
        filas={espacios}
        claveFila="espacio_id"
        cargando={cargando}
        vacio={
          <EstadoVacio
            icono={cfg.icono}
            titulo={`Aun no hay ${cfg.titulo.toLowerCase()}`}
            mensaje="Sin espacios registrados el generador no puede ubicar las clases."
            accion={<Boton icono={Plus} onClick={() => setModal({ ...cfg.forma })}>Nuevo {cfg.singular}</Boton>}
          />
        }
      />

      <Paginacion pagina={meta?.pagina ?? 1} paginas={meta?.paginas ?? 1} total={meta?.total} alCambiar={setPagina} />

      {modal && (
        <ModalEspacio
          tipo={tipo}
          espacio={modal}
          alCerrar={() => setModal(null)}
          alGuardar={() => { setModal(null); recargar() }}
        />
      )}

      <Confirmar
        abierto={Boolean(borrar)}
        alCerrar={() => setBorrar(null)}
        alConfirmar={() => eliminar(borrar)}
        cargando={eliminando}
        titulo={`¿Eliminar ${borrar?.codigo}?`}
        mensaje="Si el espacio tiene clases asignadas en periodos vigentes, se desactivara en lugar de borrarse."
      />
    </div>
  )
}

/* ------------------------------------------------------------------ */

function ModalEspacio({ tipo, espacio, alCerrar, alGuardar }) {
  const cfg = CONFIG[tipo]
  const { avisar } = useAvisos()
  const editando = Boolean(espacio.espacio_id)
  const [forma, setForma] = useState({ ...cfg.forma, ...espacio })

  const { ejecutar, enviando, errores } = useAccion(
    async () => {
      const comun = {
        codigo: String(forma.codigo).trim().toUpperCase(),
        nombre: String(forma.nombre).trim(),
        capacidad: Number(forma.capacidad),
        edificio: String(forma.edificio || '').trim(),
        piso: Number(forma.piso),
      }

      const propio = tipo === 'salones'
        ? {
            pupitres: Number(forma.pupitres),
            tiene_proyector: forma.tiene_proyector ? 1 : 0,
            tiene_aire: forma.tiene_aire ? 1 : 0,
            tiene_pizarra_digital: forma.tiene_pizarra_digital ? 1 : 0,
          }
        : {
            puestos: Number(forma.puestos),
            sistema_operativo: String(forma.sistema_operativo || '').trim(),
            software: String(forma.software || '').trim(),
            tiene_servidor: forma.tiene_servidor ? 1 : 0,
            tiene_internet: forma.tiene_internet ? 1 : 0,
            especialidad: forma.especialidad,
          }

      const cuerpo = { ...comun, ...propio }
      return editando ? api.put(`${cfg.ruta}/${espacio.espacio_id}`, cuerpo) : api.post(cfg.ruta, cuerpo)
    },
    {
      alTerminar: () => { avisar.exito(editando ? 'Espacio actualizado.' : 'Espacio creado.'); alGuardar() },
      alFallar: (e) => !e.esValidacion && avisar.error(e.message),
    }
  )

  return (
    <Modal
      abierto
      alCerrar={alCerrar}
      ancho="lg"
      titulo={editando ? `Editar ${espacio.codigo}` : `Nuevo ${cfg.singular}`}
      pie={
        <>
          <Boton variante="secundario" onClick={alCerrar} disabled={enviando}>Cancelar</Boton>
          <Boton onClick={() => ejecutar()} cargando={enviando}>Guardar</Boton>
        </>
      }
    >
      <div className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Campo
            etiqueta="Codigo" requerido placeholder={tipo === 'salones' ? 'B19' : 'LAB1'}
            value={forma.codigo} error={errores.codigo}
            onChange={(e) => setForma((f) => ({ ...f, codigo: e.target.value.toUpperCase() }))}
          />
          <Campo
            etiqueta="Nombre" requerido placeholder={tipo === 'salones' ? 'Aula B-19' : 'Laboratorio de Sistemas 1'}
            value={forma.nombre} error={errores.nombre}
            onChange={(e) => setForma((f) => ({ ...f, nombre: e.target.value }))}
          />
        </div>

        <div className="grid gap-4 sm:grid-cols-3">
          <Campo
            etiqueta="Edificio" placeholder="Edificio B"
            value={forma.edificio} error={errores.edificio}
            onChange={(e) => setForma((f) => ({ ...f, edificio: e.target.value }))}
          />
          <Campo
            etiqueta="Piso" type="number" min={0} max={10}
            value={forma.piso} error={errores.piso}
            onChange={(e) => setForma((f) => ({ ...f, piso: e.target.value }))}
          />
          <Campo
            etiqueta="Capacidad" type="number" min={5} max={200}
            value={forma.capacidad} error={errores.capacidad}
            onChange={(e) => setForma((f) => ({ ...f, capacidad: e.target.value }))}
          />
        </div>

        {tipo === 'salones' ? (
          <>
            <Campo
              etiqueta="Pupitres" type="number" min={1} max={120}
              value={forma.pupitres} error={errores.pupitres}
              onChange={(e) => setForma((f) => ({ ...f, pupitres: e.target.value }))}
            />
            <div className="grid gap-3 sm:grid-cols-3">
              <Interruptor etiqueta="Proyector" checked={forma.tiene_proyector} onChange={(v) => setForma((f) => ({ ...f, tiene_proyector: v }))} />
              <Interruptor etiqueta="Aire acondicionado" checked={forma.tiene_aire} onChange={(v) => setForma((f) => ({ ...f, tiene_aire: v }))} />
              <Interruptor etiqueta="Pizarra digital" checked={forma.tiene_pizarra_digital} onChange={(v) => setForma((f) => ({ ...f, tiene_pizarra_digital: v }))} />
            </div>
          </>
        ) : (
          <>
            <div className="grid gap-4 sm:grid-cols-3">
              <Campo
                etiqueta="Puestos de trabajo" type="number" min={1} max={100}
                value={forma.puestos} error={errores.puestos}
                onChange={(e) => setForma((f) => ({ ...f, puestos: e.target.value }))}
              />
              <Campo
                etiqueta="Sistema operativo" icono={Monitor} placeholder="Windows 11"
                value={forma.sistema_operativo} error={errores.sistema_operativo}
                onChange={(e) => setForma((f) => ({ ...f, sistema_operativo: e.target.value }))}
              />
              <Select
                etiqueta="Especialidad" value={forma.especialidad} error={errores.especialidad}
                onChange={(e) => setForma((f) => ({ ...f, especialidad: e.target.value }))}
              >
                {Object.entries(ESPECIALIDADES).map(([v, t]) => <option key={v} value={v}>{t}</option>)}
              </Select>
            </div>
            <Campo
              etiqueta="Software instalado" placeholder="Visual Studio Code, XAMPP, MySQL Workbench"
              ayuda="Separa cada programa con una coma."
              value={forma.software} error={errores.software}
              onChange={(e) => setForma((f) => ({ ...f, software: e.target.value }))}
            />
            <div className="grid gap-3 sm:grid-cols-2">
              <Interruptor etiqueta="Tiene servidor" checked={forma.tiene_servidor} onChange={(v) => setForma((f) => ({ ...f, tiene_servidor: v }))} />
              <Interruptor etiqueta="Tiene internet" checked={forma.tiene_internet} onChange={(v) => setForma((f) => ({ ...f, tiene_internet: v }))} />
            </div>
          </>
        )}
      </div>
    </Modal>
  )
}
