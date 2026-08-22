import { BookOpen, GraduationCap, Layers } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useDatos } from '../lib/hooks'
import { Cargando, Etiqueta, Tarjeta, TituloSeccion } from '../components/ui/Datos'

/**
 * Carreras del instituto. Es un catalogo estable (no se crean carreras
 * todos los dias), asi que la pantalla es de consulta y da el salto al
 * pensum de cada una.
 */
export default function Carreras() {
  const { datos: carreras, cargando } = useDatos('/carreras', null, { ttl: 300_000 })

  if (cargando) return <Cargando texto="Cargando carreras..." />

  return (
    <div>
      <TituloSeccion
        icono={GraduationCap}
        titulo="Carreras"
        descripcion="Especialidades que ofrece el instituto. Estudios Generales agrupa las materias comunes de los dos primeros semestres."
      />

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {(carreras || []).map((c) => (
          <Tarjeta key={c.carrera_id} className="flex flex-col">
            <div className="flex items-start justify-between gap-3">
              <span
                className="flex h-11 w-11 items-center justify-center rounded-xl text-white"
                style={{ backgroundColor: c.color }}
              >
                <span className="text-sm font-bold">{c.codigo}</span>
              </span>
              <Etiqueta tono={c.codigo === 'EGE' ? 'aviso' : 'marca'}>
                {c.codigo === 'EGE' ? 'Comun' : `${c.semestres} semestres`}
              </Etiqueta>
            </div>

            <h3 className="mt-3 font-titulo text-base font-semibold text-slate-900">{c.nombre}</h3>

            <dl className="mt-3 space-y-1.5 text-sm">
              <div className="flex items-center justify-between">
                <dt className="flex items-center gap-1.5 text-slate-500">
                  <BookOpen className="h-3.5 w-3.5" /> Materias
                </dt>
                <dd className="font-semibold text-slate-800">{c.total_materias}</dd>
              </div>
              <div className="flex items-center justify-between">
                <dt className="flex items-center gap-1.5 text-slate-500">
                  <Layers className="h-3.5 w-3.5" /> Letra de seccion
                </dt>
                <dd className="font-semibold text-slate-800">{c.letra_seccion}</dd>
              </div>
            </dl>

            <Link
              to={`/materias?carrera_id=${c.carrera_id}`}
              className="enlace mt-4 text-sm"
            >
              Ver su pensum
            </Link>
          </Tarjeta>
        ))}
      </div>
    </div>
  )
}
