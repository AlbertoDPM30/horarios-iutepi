import { useEffect, useMemo, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { BookOpen, FlaskConical, Pencil, Plus, Search, Trash2, Users } from 'lucide-react'
import api from '../lib/api'
import { useAccion, useDatos, useRetraso } from '../lib/hooks'
import { useAvisos } from '../context/AvisosContext'
import Boton, { BotonIcono } from '../components/ui/Boton'
import { Campo, Estrellas, Interruptor, Select } from '../components/ui/Campos'
import Modal, { Confirmar } from '../components/ui/Modal'
import { Etiqueta, EstadoVacio, Paginacion, Tabla, TituloSeccion } from '../components/ui/Datos'

const FORMA_VACIA = {
  codigo: '', nombre: '', carrera_id: '', semestre: 1, unidades_credito: 4,
  horas_semanales: 4, sesiones_semana: 2, bloques_sesion: 2,
  requiere_laboratorio: false, es_electiva: false, grupo_electiva: '',
}

/** CRUD del pensum, con el perfil de habilidades que exige cada materia. */
export default function Materias() {
  const { avisar } = useAvisos()
  const [params, setParams] = useSearchParams()

  const [pagina, setPagina] = useState(1)
  const [busqueda, setBusqueda] = useState('')
  const busquedaRetrasada = useRetraso(busqueda)

  const filtros = useMemo(
    () => ({
      pagina,
      por_pagina: 25,
      buscar: busquedaRetrasada || undefined,
      carrera_id: params.get('carrera_id') || undefined,
      semestre: params.get('semestre') || undefined,
    }),
    [pagina, busquedaRetrasada, params]
  )

  const { datos: materias, meta, cargando, recargar } = useDatos('/materias', filtros)
  const { datos: catalogos } = useDatos('/catalogos', null, { ttl: 300_000 })

  const [modal, setModal] = useState(null)
  const [borrar, setBorrar] = useState(null)

  const carreras = catalogos?.carreras ?? []

  const { ejecutar: eliminar, enviando: eliminando } = useAccion(
    async (materia) => api.del(`/materias/${materia.materia_id}`),
    {
      alTerminar: (r) => {
        avisar.exito(r.datos?.mensaje || 'Materia eliminada.')
        setBorrar(null)
        recargar()
      },
      alFallar: (e) => avisar.error(e.message),
    }
  )

  const columnas = [
    {
      clave: 'codigo',
      titulo: 'Codigo',
      render: (m) => <span className="font-mono text-xs font-semibold text-slate-900">{m.codigo}</span>,
    },
    {
      clave: 'nombre',
      titulo: 'Materia',
      nowrap: false,
      render: (m) => (
        <div className="min-w-0">
          <p className="font-medium text-slate-900">{m.nombre}</p>
          <p className="flex flex-wrap items-center gap-1.5 pt-1">
            <Etiqueta tono="neutro">{m.semestre}° semestre</Etiqueta>
            {Number(m.requiere_laboratorio) === 1 && (
              <Etiqueta tono="info" icono={FlaskConical}>Laboratorio</Etiqueta>
            )}
            {Number(m.es_electiva) === 1 && <Etiqueta tono="violeta">Electiva</Etiqueta>}
          </p>
        </div>
      ),
    },
    {
      clave: 'carrera',
      titulo: 'Carrera',
      render: (m) => (
        <span
          className="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium text-white"
          style={{ backgroundColor: m.carrera_color }}
        >
          {m.carrera_codigo}
        </span>
      ),
    },
    {
      clave: 'unidades_credito',
      titulo: 'UC / horas',
      render: (m) => (
        <span className="text-slate-600">
          {m.unidades_credito} UC · {m.horas_semanales} h
        </span>
      ),
    },
    {
      clave: 'docentes_habilitados',
      titulo: 'Docentes',
      render: (m) => (
        <Etiqueta tono={Number(m.docentes_habilitados) === 0 ? 'peligro' : 'exito'} icono={Users}>
          {m.docentes_habilitados}
        </Etiqueta>
      ),
    },
    {
      clave: 'acciones',
      titulo: '',
      alineacion: 'derecha',
      render: (m) => (
        <div className="flex justify-end gap-1">
          <BotonIcono icono={Pencil} titulo="Editar" onClick={(e) => { e.stopPropagation(); setModal(m) }} />
          <BotonIcono
            icono={Trash2}
            titulo="Eliminar"
            className="hover:bg-rose-50 hover:text-rose-700"
            onClick={(e) => { e.stopPropagation(); setBorrar(m) }}
          />
        </div>
      ),
    },
  ]

  function cambiarFiltro(clave, valor) {
    const nuevos = new URLSearchParams(params)
    if (valor) nuevos.set(clave, valor)
    else nuevos.delete(clave)
    setParams(nuevos)
    setPagina(1)
  }

  return (
    <div>
      <TituloSeccion
        icono={BookOpen}
        titulo="Materias"
        descripcion="Pensum por carrera y semestre. Cada materia declara las habilidades que exige a su docente."
        acciones={<Boton icono={Plus} onClick={() => setModal(FORMA_VACIA)}>Nueva materia</Boton>}
      />

      <div className="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Campo
          placeholder="Buscar por nombre o codigo"
          icono={Search}
          value={busqueda}
          onChange={(e) => { setBusqueda(e.target.value); setPagina(1) }}
        />
        <Select value={params.get('carrera_id') || ''} onChange={(e) => cambiarFiltro('carrera_id', e.target.value)}>
          <option value="">Todas las carreras</option>
          {carreras.map((c) => (
            <option key={c.carrera_id} value={c.carrera_id}>{c.nombre}</option>
          ))}
        </Select>
        <Select value={params.get('semestre') || ''} onChange={(e) => cambiarFiltro('semestre', e.target.value)}>
          <option value="">Todos los semestres</option>
          {[1, 2, 3, 4, 5, 6].map((s) => (
            <option key={s} value={s}>{s}° semestre</option>
          ))}
        </Select>
      </div>

      <Tabla
        columnas={columnas}
        filas={materias}
        claveFila="materia_id"
        cargando={cargando}
        vacio={
          <EstadoVacio
            icono={BookOpen}
            titulo="No hay materias con esos filtros"
            mensaje="Prueba con otra busqueda o registra una materia nueva."
            accion={<Boton icono={Plus} onClick={() => setModal(FORMA_VACIA)}>Nueva materia</Boton>}
          />
        }
      />

      <Paginacion pagina={meta?.pagina ?? 1} paginas={meta?.paginas ?? 1} total={meta?.total} alCambiar={setPagina} />

      {modal && (
        <ModalMateria
          materia={modal}
          carreras={carreras}
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
        mensaje={`Se quitara "${borrar?.nombre}" del pensum. Si ya se dicto en algun horario, la materia se desactivara en lugar de borrarse para no perder el historial.`}
      />
    </div>
  )
}

/* ------------------------------------------------------------------ */

function ModalMateria({ materia, carreras, alCerrar, alGuardar }) {
  const { avisar } = useAvisos()
  const editando = Boolean(materia.materia_id)

  const [forma, setForma] = useState({ ...FORMA_VACIA, ...materia })
  const [habilidades, setHabilidades] = useState(null)

  const { datos: detalle } = useDatos(editando ? `/materias/${materia.materia_id}` : null, null, { ttl: 0 })
  const { datos: catalogo } = useDatos('/catalogos', null, { ttl: 300_000 })

  // Perfil de habilidades: se precarga cuando llega el detalle
  useEffect(() => {
    if (!editando) {
      setHabilidades({})
      return
    }
    if (detalle) {
      setHabilidades(
        Object.fromEntries((detalle.habilidades || []).map((h) => [h.habilidad_id, Number(h.estrellas_minimas)]))
      )
    }
  }, [editando, detalle])

  const categorias = catalogo?.habilidades ?? []

  const { ejecutar, enviando, errores } = useAccion(
    async () => {
      const cuerpo = {
        codigo: forma.codigo.trim().toUpperCase(),
        nombre: forma.nombre.trim(),
        carrera_id: Number(forma.carrera_id),
        semestre: Number(forma.semestre),
        unidades_credito: Number(forma.unidades_credito),
        horas_semanales: Number(forma.horas_semanales),
        sesiones_semana: Number(forma.sesiones_semana),
        bloques_sesion: Number(forma.bloques_sesion),
        requiere_laboratorio: forma.requiere_laboratorio ? 1 : 0,
        es_electiva: forma.es_electiva ? 1 : 0,
        grupo_electiva: forma.es_electiva ? forma.grupo_electiva.trim().toUpperCase() : '',
        habilidades: Object.entries(habilidades || {})
          .filter(([, estrellas]) => estrellas > 0)
          .map(([habilidad_id, estrellas]) => ({
            habilidad_id: Number(habilidad_id),
            estrellas_minimas: estrellas,
            peso: estrellas >= 4 ? 3 : 1,
          })),
      }

      return editando ? api.put(`/materias/${materia.materia_id}`, cuerpo) : api.post('/materias', cuerpo)
    },
    {
      alTerminar: () => { avisar.exito(editando ? 'Materia actualizada.' : 'Materia creada.'); alGuardar() },
      alFallar: (e) => !e.esValidacion && avisar.error(e.message),
    }
  )

  const totalSeleccionadas = Object.values(habilidades || {}).filter((v) => v > 0).length

  return (
    <Modal
      abierto
      alCerrar={alCerrar}
      ancho="xl"
      titulo={editando ? `Editar ${materia.codigo}` : 'Nueva materia'}
      descripcion="Las habilidades que marques aqui son las que el sistema usara para proponer docentes."
      pie={
        <>
          <Boton variante="secundario" onClick={alCerrar} disabled={enviando}>Cancelar</Boton>
          <Boton onClick={() => ejecutar()} cargando={enviando}>
            {editando ? 'Guardar cambios' : 'Crear materia'}
          </Boton>
        </>
      }
    >
      <div className="space-y-5">
        <div className="grid gap-4 sm:grid-cols-2">
          <Campo
            etiqueta="Codigo" requerido placeholder="ASA303"
            value={forma.codigo} error={errores.codigo}
            onChange={(e) => setForma((f) => ({ ...f, codigo: e.target.value.toUpperCase() }))}
          />
          <Campo
            etiqueta="Nombre" requerido placeholder="Programacion II"
            value={forma.nombre} error={errores.nombre}
            onChange={(e) => setForma((f) => ({ ...f, nombre: e.target.value }))}
          />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <Select
            etiqueta="Carrera" requerido value={forma.carrera_id} error={errores.carrera_id}
            onChange={(e) => setForma((f) => ({ ...f, carrera_id: e.target.value }))}
          >
            <option value="">Selecciona una carrera</option>
            {carreras.map((c) => (
              <option key={c.carrera_id} value={c.carrera_id}>{c.nombre}</option>
            ))}
          </Select>
          <Select
            etiqueta="Semestre" requerido value={forma.semestre} error={errores.semestre}
            onChange={(e) => setForma((f) => ({ ...f, semestre: e.target.value }))}
          >
            {[1, 2, 3, 4, 5, 6].map((s) => <option key={s} value={s}>{s}° semestre</option>)}
          </Select>
        </div>

        <div className="grid gap-4 sm:grid-cols-4">
          <Campo
            etiqueta="Unidades de credito" type="number" min={1} max={10}
            value={forma.unidades_credito} error={errores.unidades_credito}
            onChange={(e) => setForma((f) => ({ ...f, unidades_credito: e.target.value }))}
          />
          <Campo
            etiqueta="Horas por semana" type="number" min={1} max={20}
            value={forma.horas_semanales} error={errores.horas_semanales}
            onChange={(e) => setForma((f) => ({ ...f, horas_semanales: e.target.value }))}
          />
          <Campo
            etiqueta="Dias por semana" type="number" min={1} max={4}
            ayuda="Cuantas veces se ve"
            value={forma.sesiones_semana} error={errores.sesiones_semana}
            onChange={(e) => setForma((f) => ({ ...f, sesiones_semana: e.target.value }))}
          />
          <Campo
            etiqueta="Bloques por clase" type="number" min={1} max={6}
            ayuda="Bloques seguidos"
            value={forma.bloques_sesion} error={errores.bloques_sesion}
            onChange={(e) => setForma((f) => ({ ...f, bloques_sesion: e.target.value }))}
          />
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          <Interruptor
            etiqueta="Se dicta en laboratorio"
            descripcion="El generador solo la ubicara en laboratorios libres."
            checked={forma.requiere_laboratorio}
            onChange={(v) => setForma((f) => ({ ...f, requiere_laboratorio: v }))}
          />
          <Interruptor
            etiqueta="Es electiva"
            descripcion="Las electivas del mismo grupo se dictan en paralelo."
            checked={forma.es_electiva}
            onChange={(v) => setForma((f) => ({ ...f, es_electiva: v }))}
          />
        </div>

        {forma.es_electiva && (
          <Campo
            etiqueta="Grupo de electiva" requerido placeholder="ELECTIVA_I"
            ayuda="Las materias con el mismo grupo comparten bloque horario."
            value={forma.grupo_electiva} error={errores.grupo_electiva}
            onChange={(e) => setForma((f) => ({ ...f, grupo_electiva: e.target.value.toUpperCase() }))}
          />
        )}

        {/* ---- Perfil de habilidades ---- */}
        <div className="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
          <div className="mb-3 flex items-center justify-between">
            <div>
              <p className="text-sm font-semibold text-slate-800">Habilidades que exige la materia</p>
              <p className="text-xs text-slate-500">
                Marca el nivel minimo que necesita el docente. Deja en cero lo que no aplique.
              </p>
            </div>
            <Etiqueta tono={totalSeleccionadas ? 'marca' : 'aviso'}>{totalSeleccionadas} elegidas</Etiqueta>
          </div>

          <div className="max-h-72 space-y-4 overflow-y-auto pr-1">
            {categorias.map((cat) => (
              <div key={cat.categoria_id}>
                <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-slate-500">{cat.nombre}</p>
                <div className="grid gap-1.5 sm:grid-cols-2">
                  {cat.habilidades.map((h) => (
                    <label
                      key={h.habilidad_id}
                      className="flex items-center justify-between gap-2 rounded-lg bg-white px-2.5 py-1.5 text-sm ring-1 ring-slate-200"
                    >
                      <span className="min-w-0 truncate text-slate-700">{h.nombre}</span>
                      <Estrellas
                        tamano="sm"
                        valor={habilidades?.[h.habilidad_id] || 0}
                        onChange={(v) => setHabilidades((prev) => ({ ...prev, [h.habilidad_id]: v }))}
                      />
                    </label>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>
    </Modal>
  )
}
