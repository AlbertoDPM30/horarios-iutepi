import { Link } from 'react-router-dom'
import { Compass } from 'lucide-react'
import { EstadoVacio } from '../components/ui/Datos'

export default function NoEncontrado() {
  return (
    <EstadoVacio
      icono={Compass}
      titulo="Esta pagina no existe"
      mensaje="Puede que el enlace este viejo o que la seccion se haya movido."
      accion={
        <Link
          to="/"
          className="rounded-xl bg-marca-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-marca-800"
        >
          Volver al panel
        </Link>
      }
    />
  )
}
