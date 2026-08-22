import { Navigate, useLocation, useNavigate } from 'react-router-dom'
import { ShieldAlert } from 'lucide-react'
import { useAuth } from '../../context/AuthContext'
import { EstadoVacio, PantallaCarga } from '../ui/Datos'
import Boton from '../ui/Boton'
import { RUTA_INICIAL } from '../../lib/utils'

/**
 * Corta el paso a quien no tiene sesion o cuyo rol no cubre la ruta.
 * Es solo la primera barrera: la API vuelve a validar cada permiso.
 */
export default function RutaProtegida({ children, roles }) {
  const { autenticado, cargando, rol } = useAuth()
  const ubicacion = useLocation()
  const navegar = useNavigate()

  if (cargando) return <PantallaCarga />

  if (!autenticado) {
    return <Navigate to="/entrar" replace state={{ desde: ubicacion.pathname }} />
  }

  if (roles && !roles.includes(rol)) {
    return (
      <div className="py-10">
        <EstadoVacio
          icono={ShieldAlert}
          titulo="Esta seccion no esta disponible para tu perfil"
          mensaje="Si crees que deberias tener acceso, comunicalo a coordinacion academica."
          accion={
            <Boton onClick={() => navegar(RUTA_INICIAL[rol] ?? '/', { replace: true })}>
              Ir a mi pantalla de inicio
            </Boton>
          }
        />
      </div>
    )
  }

  return children
}
