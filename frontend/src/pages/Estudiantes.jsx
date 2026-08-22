import { useMemo, useState } from 'react'
import {
  CalendarCheck, Download, GraduationCap, IdCard, Pencil, Plus, Search, Trash2, UserRound, Wand2,
} from 'lucide-react'
import api from '../lib/api'
import { useAccion, useDatos, useRetraso } from '../lib/hooks'
import { useAvisos } from '../context/AvisosContext'
import Boton, { BotonIcono } from '../components/ui/Boton'
import { Campo, Select } from '../components/ui/Campos'
import Modal, { Confirmar } from '../components/ui/Modal'
import { EstadoVacio, Etiqueta, Paginacion, Tabla, TituloSeccion } from '../components/ui/Datos'
import { descargarCsv, fechaCorta } from '../lib/utils'

const FORMA_VACIA = {
  codigo: '', cedula: '', nombres: '', apellidos: '', correo: '', telefono: '',
  fecha_nacimiento: '', genero: '', direccion: '', carrera_id: '', semestre_actual: 1,
  modalidad: 'SEMANA', fecha_ingreso: '', representante: '', telefono_emergencia: '', seccion_id: '',
}

/** Registro de estudiantes con su inscripcion y seccion del periodo. */
export default function Estudiantes() {
  const { avisar } = useAvisos()

  const [pagina, setPagina] = useState(1)
  const [busqueda, setBusqueda] = useState('')
  const [filtroCarrera, setFiltroCarrera] = useState('')
  const [filtroModalidad, setFiltroModalidad] = useState('')
  const busquedaRetrasada = useRetraso(busqueda)

  const filtros = useMemo(
    () => ({
      pagina,
      por_pagina: 25,
      buscar: busquedaRetrasada || undefined,
      carrera_id: filtroCarrera || undefined,
      modalidad: filtroModalidad || undefined,
    }),
    [pagina, busquedaRetrasada, filtroCarrera, filtroModalidad]
  )

  const { datos: estudiantes, meta, cargando, recargar } = useDatos('/estudiantes', filtros)
  const { datos: catalogos } = useDatos('/catalogos', null, { ttl: 300_000 })

  const [modal, setModal] = useState(null)
  const [borrar, setBorrar] = useState(null)

  const { ejecutar: eliminar, enviando: eliminando } = useAccion(
    async (e) => api.del(`/estudiantes/${e.estudiante_id}`),
    {
      alTerminar: (r) => { avisar.exito(r.datos?.mensaje || 'Estudiante eliminado.'); setBorrar(null); recargar() },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const columnas = [
    {
      clave: 'codigo',
      titulo: 'Codigo',
      render: (e) => <span className="font-mono text-xs font-semibold text-marca-900">{e.codigo}</span>,
    },
    {
      clave: 'nombre_completo',
      titulo: 'Estudiante',
      nowrap: false,
      render: (e) => (
        <div className="min-w-0">
          <p className="font-medium text-slate-900">{e.nombre_completo}</p>
          <p className="text-xs text-slate-500">{e.cedula} · {e.correo || 'sin correo'}</p>
        </div>
      ),
    },
    {
      clave: 'carrera',
      titulo: 'Carrera',
      render: (e) => (
        <span className="inline-flex flex-col gap-1">
          <span
            className="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-medium text-white"
            style={{ backgroundColor: e.carrera_color }}
          >
            {e.carrera_codigo}
          </span>
          <span className="text-xs text-slate-500">{e.semestre_actual}° semestre</span>
        </span>
      ),
    },
    {
      clave: 'seccion',
      titulo: 'Seccion',
      render: (e) =>
        e.seccion ? (
          <Etiqueta tono="marca">{e.seccion}</Etiqueta>
        ) : (
          <Etiqueta tono="aviso">Sin inscribir</Etiqueta>
        ),
    },
    {
      clave: 'modalidad',
      titulo: 'Modalidad',
      render: (e) => (
        <span className="text-slate-600">{e.modalidad === 'SABATINO' ? 'Sabatino' : 'Entre semana'}</span>
      ),
    },
    {
      clave: 'horario_confirmado',
      titulo: 'Horario',
      render: (e) =>
        Number(e.horario_confirmado) === 1 ? (
          <Etiqueta tono="exito" icono={CalendarCheck}>Confirmado</Etiqueta>
        ) : (
          <Etiqueta tono="neutro">Pendiente</Etiqueta>
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
        icono={UserRound}
        titulo="Estudiantes"
        descripcion="Cada estudiante accede al sistema con su codigo; solo los inscritos pueden entrar."
        acciones={
          <>
            <Boton
              variante="secundario"
              icono={Download}
              onClick={() =>
                descargarCsv(
                  `estudiantes-${new Date().toISOString().slice(0, 10)}.csv`,
                  (estudiantes || []).map((e) => ({
                    codigo: e.codigo, cedula: e.cedula, nombres: e.nombres, apellidos: e.apellidos,
                    carrera: e.carrera, semestre: e.semestre_actual, seccion: e.seccion || '',
                    modalidad: e.modalidad, correo: e.correo, telefono: e.telefono,
                  }))
                )
              }
            >
              Exportar
            </Boton>
            <Boton icono={Plus} onClick={() => setModal({ ...FORMA_VACIA })}>Nuevo estudiante</Boton>
          </>
        }
      />

      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Campo
          placeholder="Buscar por nombre, codigo o cedula"
          icono={Search}
          value={busqueda}
          onChange={(e) => { setBusqueda(e.target.value); setPagina(1) }}
        />
        <Select value={filtroCarrera} onChange={(e) => { setFiltroCarrera(e.target.value); setPagina(1) }}>
          <option value="">Todas las carreras</option>
          {(catalogos?.carreras ?? []).map((c) => (
            <option key={c.carrera_id} value={c.carrera_id}>{c.nombre}</option>
          ))}
        </Select>
        <Select value={filtroModalidad} onChange={(e) => { setFiltroModalidad(e.target.value); setPagina(1) }}>
          <option value="">Todas las modalidades</option>
          <option value="SEMANA">Entre semana</option>
          <option value="SABATINO">Sabatino</option>
        </Select>
      </div>

      <Tabla
        columnas={columnas}
        filas={estudiantes}
        claveFila="estudiante_id"
        cargando={cargando}
        vacio={
          <EstadoVacio
            icono={UserRound}
            titulo="No hay estudiantes con esos filtros"
            mensaje="Registra un estudiante o cambia la busqueda."
            accion={<Boton icono={Plus} onClick={() => setModal({ ...FORMA_VACIA })}>Nuevo estudiante</Boton>}
          />
        }
      />

      <Paginacion pagina={meta?.pagina ?? 1} paginas={meta?.paginas ?? 1} total={meta?.total} alCambiar={setPagina} />

      {modal && (
        <ModalEstudiante
          estudiante={modal}
          carreras={catalogos?.carreras ?? []}
          alCerrar={() => setModal(null)}
          alGuardar={() => { setModal(null); recargar() }}
        />
      )}

      <Confirmar
        abierto={Boolean(borrar)}
        alCerrar={() => setBorrar(null)}
        alConfirmar={() => eliminar(borrar)}
        cargando={eliminando}
        titulo={`¿Eliminar a ${borrar?.nombre_completo}?`}
        mensaje="Si el estudiante ya tiene historial academico, se marcara como RETIRADO en lugar de borrarse."
      />
    </div>
  )
}

/* ------------------------------------------------------------------ */

function ModalEstudiante({ estudiante, carreras, alCerrar, alGuardar }) {
  const { avisar } = useAvisos()
  const editando = Boolean(estudiante.estudiante_id)
  const [forma, setForma] = useState({ ...FORMA_VACIA, ...estudiante })

  const { datos: periodos } = useDatos(editando ? null : '/periodos', null, { ttl: 60_000 })
  const [periodoId, setPeriodoId] = useState('')
  const [sugiriendo, setSugiriendo] = useState(false)

  // Desglose del codigo mientras se escribe, para que el administrador
  // vea que esta componiendo y no se equivoque de ano o de referencia.
  const desgloseCodigo = /^\d{6}$/.test(forma.codigo || '')
    ? { anio: forma.codigo.slice(0, 2), referencia: forma.codigo.slice(2, 4), correlativo: forma.codigo.slice(4, 6) }
    : null

  async function sugerirCodigo() {
    setSugiriendo(true)
    try {
      const { datos } = await api.get('/estudiantes/siguiente-codigo', { anio: new Date().getFullYear() }, { ttl: 0, forzar: true })
      setForma((f) => ({ ...f, codigo: datos.codigo }))
    } catch (e) {
      avisar.error(e.message)
    } finally {
      setSugiriendo(false)
    }
  }

  const { datos: secciones } = useDatos(
    periodoId ? '/secciones' : null,
    { periodo_id: periodoId, carrera_id: forma.carrera_id || undefined }
  )

  const { ejecutar, enviando, errores } = useAccion(
    async () => {
      const cuerpo = {
        cedula: forma.cedula.trim(),
        nombres: forma.nombres.trim(),
        apellidos: forma.apellidos.trim(),
        correo: forma.correo?.trim() || undefined,
        telefono: forma.telefono?.trim() || undefined,
        fecha_nacimiento: forma.fecha_nacimiento || undefined,
        genero: forma.genero || undefined,
        direccion: forma.direccion?.trim() || undefined,
        carrera_id: Number(forma.carrera_id),
        semestre_actual: Number(forma.semestre_actual),
        modalidad: forma.modalidad,
        fecha_ingreso: forma.fecha_ingreso || undefined,
        representante: forma.representante?.trim() || undefined,
        telefono_emergencia: forma.telefono_emergencia?.trim() || undefined,
      }

      if (forma.codigo?.trim()) cuerpo.codigo = forma.codigo.trim().toUpperCase()

      if (editando) return api.put(`/estudiantes/${estudiante.estudiante_id}`, cuerpo)

      if (forma.seccion_id) cuerpo.seccion_id = Number(forma.seccion_id)
      return api.post('/estudiantes', cuerpo)
    },
    {
      alTerminar: (r) => {
        avisar.exito(
          editando
            ? 'Datos actualizados.'
            : `Estudiante registrado con el codigo ${r.datos?.codigo ?? ''}.`
        )
        alGuardar()
      },
      alFallar: (e) => !e.esValidacion && avisar.error(e.message),
    }
  )

  return (
    <Modal
      abierto
      alCerrar={alCerrar}
      ancho="xl"
      titulo={editando ? `Editar a ${estudiante.nombres} ${estudiante.apellidos}` : 'Nuevo estudiante'}
      descripcion={editando ? undefined : 'El codigo lo asigna control de estudios; el boton solo sugiere el siguiente.'}
      pie={
        <>
          <Boton variante="secundario" onClick={alCerrar} disabled={enviando}>Cancelar</Boton>
          <Boton onClick={() => ejecutar()} cargando={enviando}>Guardar</Boton>
        </>
      }
    >
      <div className="space-y-5">
        <section>
          <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
            <IdCard className="h-4 w-4 text-slate-400" /> Datos personales
          </h3>
          <div className="grid gap-4 sm:grid-cols-2">
            <div className="sm:col-span-2">
              <div className="flex items-end gap-2">
                <Campo
                  etiqueta="Codigo de estudiante"
                  requerido={!editando}
                  placeholder="264206"
                  inputMode="numeric"
                  maxLength={6}
                  ayuda="6 digitos: ano (2) + referencia (2) + correlativo (2)."
                  value={forma.codigo}
                  error={errores.codigo}
                  onChange={(e) => setForma((f) => ({ ...f, codigo: e.target.value.replace(/\D/g, '').slice(0, 6) }))}
                />
                {!editando && (
                  <Boton
                    variante="secundario"
                    icono={Wand2}
                    cargando={sugiriendo}
                    className="mb-[1.6rem] shrink-0"
                    onClick={sugerirCodigo}
                  >
                    Sugerir
                  </Boton>
                )}
              </div>

              {desgloseCodigo && (
                <p className="mt-1.5 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                  <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono font-semibold text-slate-700">
                    {desgloseCodigo.anio}
                  </span>
                  ano 20{desgloseCodigo.anio}
                  <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono font-semibold text-slate-700">
                    {desgloseCodigo.referencia}
                  </span>
                  referencia
                  <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono font-semibold text-slate-700">
                    {desgloseCodigo.correlativo}
                  </span>
                  correlativo
                </p>
              )}
            </div>
            <Campo
              etiqueta="Cedula" requerido placeholder="V-25123456"
              value={forma.cedula} error={errores.cedula}
              onChange={(e) => setForma((f) => ({ ...f, cedula: e.target.value }))}
            />
            <Campo
              etiqueta="Nombres" requerido value={forma.nombres} error={errores.nombres}
              onChange={(e) => setForma((f) => ({ ...f, nombres: e.target.value }))}
            />
            <Campo
              etiqueta="Apellidos" requerido value={forma.apellidos} error={errores.apellidos}
              onChange={(e) => setForma((f) => ({ ...f, apellidos: e.target.value }))}
            />
            <Campo
              etiqueta="Correo" type="email" value={forma.correo} error={errores.correo}
              onChange={(e) => setForma((f) => ({ ...f, correo: e.target.value }))}
            />
            <Campo
              etiqueta="Telefono" value={forma.telefono} error={errores.telefono}
              onChange={(e) => setForma((f) => ({ ...f, telefono: e.target.value }))}
            />
            <Campo
              etiqueta="Fecha de nacimiento" type="date"
              value={forma.fecha_nacimiento ? String(forma.fecha_nacimiento).slice(0, 10) : ''}
              error={errores.fecha_nacimiento}
              onChange={(e) => setForma((f) => ({ ...f, fecha_nacimiento: e.target.value }))}
            />
            <Select
              etiqueta="Genero" value={forma.genero || ''} error={errores.genero}
              onChange={(e) => setForma((f) => ({ ...f, genero: e.target.value }))}
            >
              <option value="">Prefiere no decirlo</option>
              <option value="F">Femenino</option>
              <option value="M">Masculino</option>
              <option value="OTRO">Otro</option>
            </Select>
            <Campo
              etiqueta="Direccion" className="sm:col-span-2"
              value={forma.direccion} error={errores.direccion}
              onChange={(e) => setForma((f) => ({ ...f, direccion: e.target.value }))}
            />
          </div>
        </section>

        <section>
          <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-800">
            <GraduationCap className="h-4 w-4 text-slate-400" /> Datos academicos
          </h3>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Select
              etiqueta="Carrera" requerido value={forma.carrera_id} error={errores.carrera_id}
              onChange={(e) => setForma((f) => ({ ...f, carrera_id: e.target.value }))}
            >
              <option value="">Selecciona</option>
              {carreras.map((c) => <option key={c.carrera_id} value={c.carrera_id}>{c.nombre}</option>)}
            </Select>
            <Select
              etiqueta="Semestre" value={forma.semestre_actual} error={errores.semestre_actual}
              onChange={(e) => setForma((f) => ({ ...f, semestre_actual: e.target.value }))}
            >
              {[1, 2, 3, 4, 5, 6].map((s) => <option key={s} value={s}>{s}° semestre</option>)}
            </Select>
            <Select
              etiqueta="Modalidad" value={forma.modalidad} error={errores.modalidad}
              onChange={(e) => setForma((f) => ({ ...f, modalidad: e.target.value }))}
            >
              <option value="SEMANA">Entre semana</option>
              <option value="SABATINO">Sabatino</option>
            </Select>
            <Campo
              etiqueta="Fecha de ingreso" type="date"
              value={forma.fecha_ingreso ? String(forma.fecha_ingreso).slice(0, 10) : ''}
              error={errores.fecha_ingreso}
              onChange={(e) => setForma((f) => ({ ...f, fecha_ingreso: e.target.value }))}
            />
          </div>
        </section>

        {!editando && (
          <section className="rounded-2xl border border-marca-200 bg-marca-50/50 p-4">
            <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold text-marca-900">
              <Wand2 className="h-4 w-4" /> Inscripcion (opcional)
            </h3>
            <div className="grid gap-4 sm:grid-cols-2">
              <Select etiqueta="Periodo" value={periodoId} onChange={(e) => setPeriodoId(e.target.value)}>
                <option value="">No inscribir todavia</option>
                {(periodos || [])
                  .filter((p) => p.estado !== 'FINALIZADO')
                  .map((p) => (
                    <option key={p.periodo_id} value={p.periodo_id}>
                      {p.codigo} ({fechaCorta(p.fecha_inicio)})
                    </option>
                  ))}
              </Select>
              <Select
                etiqueta="Seccion" disabled={!periodoId}
                value={forma.seccion_id} error={errores.seccion_id}
                onChange={(e) => setForma((f) => ({ ...f, seccion_id: e.target.value }))}
              >
                <option value="">Selecciona una seccion</option>
                {(secciones || []).map((s) => (
                  <option key={s.seccion_id} value={s.seccion_id}>
                    {s.codigo} — {s.semestre}° sem ({s.inscritos}/{s.cupo})
                  </option>
                ))}
              </Select>
            </div>
            <p className="mt-2 text-xs text-marca-800">
              Con el periodo ya iniciado, esta es la unica forma de sumar un estudiante y su horario se genera una sola vez.
            </p>
          </section>
        )}
      </div>
    </Modal>
  )
}
