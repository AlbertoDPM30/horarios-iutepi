import { lazy, Suspense } from 'react'
import { Navigate, Route, Routes } from 'react-router-dom'
import { AuthProvider } from './context/AuthContext'
import { AvisosProvider } from './context/AvisosContext'
import { NotificacionesProvider } from './context/NotificacionesContext'
import Layout from './components/layout/Layout'
import RutaProtegida from './components/layout/RutaProtegida'
import { Cargando } from './components/ui/Datos'
import Entrar from './pages/Entrar'
import Dashboard from './pages/Dashboard'

/*
 * Las pantallas pesadas se cargan bajo demanda: el primer render (login
 * y dashboard) queda en un paquete chico y el resto llega cuando hace
 * falta.
 */
const Periodo = lazy(() => import('./pages/Periodo'))
const Materias = lazy(() => import('./pages/Materias'))
const Habilidades = lazy(() => import('./pages/Habilidades'))
const Carreras = lazy(() => import('./pages/Carreras'))
const Profesores = lazy(() => import('./pages/Profesores'))
const ProfesorFormulario = lazy(() => import('./pages/ProfesorFormulario'))
const Estudiantes = lazy(() => import('./pages/Estudiantes'))
const Espacios = lazy(() => import('./pages/Espacios'))
const Secciones = lazy(() => import('./pages/Secciones'))
const Horarios = lazy(() => import('./pages/Horarios'))
const Conflictos = lazy(() => import('./pages/Conflictos'))
const MiHorario = lazy(() => import('./pages/MiHorario'))
const NoEncontrado = lazy(() => import('./pages/NoEncontrado'))

const ADMIN = ['ADMIN']

function Perezoso({ children }) {
  return <Suspense fallback={<Cargando texto="Abriendo el modulo..." />}>{children}</Suspense>
}

export default function App() {
  return (
    <AvisosProvider>
      <AuthProvider>
        <NotificacionesProvider>
          <Routes>
            <Route path="/entrar" element={<Entrar />} />

            <Route
              element={
                <RutaProtegida>
                  <Layout />
                </RutaProtegida>
              }
            >
              <Route index element={<Dashboard />} />
              <Route path="periodos" element={<Dashboard />} />
              <Route
                path="periodos/:id"
                element={
                  <RutaProtegida roles={['ADMIN', 'DOCENTE']}>
                    <Perezoso><Periodo /></Perezoso>
                  </RutaProtegida>
                }
              />

              <Route path="horarios" element={<Perezoso><Horarios /></Perezoso>} />
              <Route path="mi-horario" element={<Perezoso><MiHorario /></Perezoso>} />

              <Route
                path="materias"
                element={<RutaProtegida roles={ADMIN}><Perezoso><Materias /></Perezoso></RutaProtegida>}
              />
              <Route
                path="carreras"
                element={<RutaProtegida roles={ADMIN}><Perezoso><Carreras /></Perezoso></RutaProtegida>}
              />
              <Route
                path="habilidades"
                element={<RutaProtegida roles={ADMIN}><Perezoso><Habilidades /></Perezoso></RutaProtegida>}
              />
              <Route
                path="profesores"
                element={<RutaProtegida roles={ADMIN}><Perezoso><Profesores /></Perezoso></RutaProtegida>}
              />
              <Route
                path="profesores/nuevo"
                element={<RutaProtegida roles={ADMIN}><Perezoso><ProfesorFormulario /></Perezoso></RutaProtegida>}
              />
              <Route
                path="profesores/:id"
                element={<RutaProtegida roles={ADMIN}><Perezoso><ProfesorFormulario /></Perezoso></RutaProtegida>}
              />
              <Route
                path="estudiantes"
                element={<RutaProtegida roles={ADMIN}><Perezoso><Estudiantes /></Perezoso></RutaProtegida>}
              />
              <Route
                path="salones"
                element={<RutaProtegida roles={ADMIN}><Perezoso><Espacios tipo="salones" /></Perezoso></RutaProtegida>}
              />
              <Route
                path="laboratorios"
                element={<RutaProtegida roles={ADMIN}><Perezoso><Espacios tipo="laboratorios" /></Perezoso></RutaProtegida>}
              />
              <Route
                path="secciones"
                element={<RutaProtegida roles={ADMIN}><Perezoso><Secciones /></Perezoso></RutaProtegida>}
              />
              <Route
                path="conflictos"
                element={<RutaProtegida roles={ADMIN}><Perezoso><Conflictos /></Perezoso></RutaProtegida>}
              />

              <Route path="*" element={<Perezoso><NoEncontrado /></Perezoso>} />
            </Route>

            <Route path="*" element={<Navigate to="/" replace />} />
          </Routes>
        </NotificacionesProvider>
      </AuthProvider>
    </AvisosProvider>
  )
}
