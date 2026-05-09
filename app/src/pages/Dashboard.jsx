import { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { api } from '../services/api'

const quickLinks = [
  { label: 'Profesores', path: '/profesores', icon: '👨‍🏫', color: '#4361ee' },
  { label: 'Estudiantes', path: '/estudiantes', icon: '👨‍🎓', color: '#06d6a0' },
  { label: 'Materias', path: '/materias', icon: '📚', color: '#118ab2' },
  { label: 'Habilidades', path: '/habilidades', icon: '⭐', color: '#ffd166' },
  { label: 'Disponibilidad', path: '/profesores-disponibilidad', icon: '🕐', color: '#ef476f' },
  { label: 'Generar Horario', path: '/generar-horario', icon: '⚙️', color: '#073b4c' },
  { label: 'Ver Horarios', path: '/ver-horarios', icon: '📅', color: '#4361ee' },
  { label: 'Asignar Materias', path: '/profesores-materias', icon: '📋', color: '#06d6a0' },
]

export default function Dashboard() {
  const navigate = useNavigate()
  const [stats, setStats] = useState(null)
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    Promise.allSettled([
      api.get('profesores').catch(() => []),
      api.get('estudiantes').catch(() => []),
      api.get('materias').catch(() => []),
      api.get('habilidades').catch(() => []),
    ]).then(([p, e, m, h]) => {
      setStats({
        profesores: Array.isArray(p.value) ? p.value.length : 0,
        estudiantes: Array.isArray(e.value) ? e.value.length : 0,
        materias: Array.isArray(m.value) ? m.value.length : 0,
        habilidades: Array.isArray(h.value) ? h.value.length : 0,
      })
      setLoading(false)
    })
  }, [])

  return (
    <>
      <div className="page-header">
        <h1>Dashboard</h1>
      </div>
      <div className="page-body">
        {loading ? (
          <div className="loading"><div className="spinner" />Cargando...</div>
        ) : stats ? (
          <div className="stats-grid">
            <div className="stat-card">
              <div className="stat-label">Profesores</div>
              <div className="stat-value">{stats.profesores}</div>
            </div>
            <div className="stat-card">
              <div className="stat-label">Estudiantes</div>
              <div className="stat-value">{stats.estudiantes}</div>
            </div>
            <div className="stat-card">
              <div className="stat-label">Materias</div>
              <div className="stat-value">{stats.materias}</div>
            </div>
            <div className="stat-card">
              <div className="stat-label">Habilidades</div>
              <div className="stat-value">{stats.habilidades}</div>
            </div>
          </div>
        ) : null}

        <h3 className="font-semibold mb-2" style={{ fontSize: '1rem' }}>Acceso Rápido</h3>
        <div className="stats-grid">
          {quickLinks.map((link) => (
            <div
              key={link.path}
              className="stat-card"
              style={{ cursor: 'pointer', borderLeft: `4px solid ${link.color}` }}
              onClick={() => navigate(link.path)}
            >
              <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>{link.icon}</div>
              <div className="stat-label">{link.label}</div>
            </div>
          ))}
        </div>
      </div>
    </>
  )
}
