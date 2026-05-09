import { Outlet, NavLink, useNavigate } from 'react-router-dom'
import { useAuth } from '../contexts/AuthContext'

const navItems = [
  { group: 'Dashboard' },
  { label: 'Dashboard', path: '/dashboard', icon: '📊' },
  { group: 'Gestión' },
  { label: 'Profesores', path: '/profesores', icon: '👨‍🏫' },
  { label: 'Estudiantes', path: '/estudiantes', icon: '👨‍🎓' },
  { label: 'Materias', path: '/materias', icon: '📚' },
  { label: 'Habilidades', path: '/habilidades', icon: '⭐' },
  { label: 'Módulos', path: '/modulos', icon: '📦' },
  { label: 'Usuarios', path: '/usuarios', icon: '👤' },
  { group: 'Asignaciones' },
  { label: 'Habilidad → Profesor', path: '/profesores-habilidades', icon: '🔗' },
  { label: 'Materia → Profesor', path: '/profesores-materias', icon: '📋' },
  { label: 'Habilidad → Materia', path: '/materias-habilidades', icon: '🎯' },
  { label: 'Disponibilidad', path: '/profesores-disponibilidad', icon: '🕐' },
  { group: 'Horarios' },
  { label: 'Generar Horario', path: '/generar-horario', icon: '⚙️' },
  { label: 'Ver Horarios', path: '/ver-horarios', icon: '📅' },
]

export default function Layout() {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  return (
    <div className="app-layout">
      <aside className="sidebar">
        <div className="sidebar-logo">
          <h2>IUTEPI</h2>
          <p>Sistema de Horarios</p>
        </div>
        <nav className="sidebar-nav">
          {navItems.map((item, i) =>
            item.group ? (
              <div key={i} className="nav-group-title">{item.group}</div>
            ) : (
              <NavLink
                key={item.path}
                to={item.path}
                className={({ isActive }) => `nav-link ${isActive ? 'active' : ''}`}
              >
                <span className="nav-icon">{item.icon}</span>
                {item.label}
              </NavLink>
            )
          )}
        </nav>
        <div className="sidebar-footer">
          <div className="sidebar-user">
            {user?.first_name ? `${user.first_name} ${user.last_name}` : user?.username || 'Usuario'}
          </div>
          <button className="sidebar-logout" onClick={handleLogout}>
            ⏻ Cerrar sesión
          </button>
        </div>
      </aside>
      <main className="main-content">
        <Outlet />
      </main>
    </div>
  )
}
