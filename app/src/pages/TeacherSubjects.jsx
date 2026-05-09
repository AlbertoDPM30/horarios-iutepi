import { useState, useEffect, useCallback } from 'react'
import { api } from '../services/api'

export default function TeacherSubjects() {
  const [teachers, setTeachers] = useState([])
  const [selectedTeacher, setSelectedTeacher] = useState('')
  const [eligible, setEligible] = useState([])
  const [assigned, setAssigned] = useState([])
  const [selected, setSelected] = useState({})
  const [loading, setLoading] = useState(false)
  const [saving, setSaving] = useState(false)
  const [notification, setNotification] = useState(null)

  const notify = useCallback((msg, type = 'success') => {
    setNotification({ msg, type }); setTimeout(() => setNotification(null), 3000)
  }, [])

  useEffect(() => {
    api.get('profesores').then(r => setTeachers(Array.isArray(r) ? r : [])).catch(() => {})
  }, [])

  const loadData = useCallback(async () => {
    if (!selectedTeacher) { setEligible([]); setAssigned([]); return }
    setLoading(true)
    try {
      const [eligRes, assignRes] = await Promise.allSettled([
        api.get(`profesores-materias?teacher_id=${selectedTeacher}`),
        api.get(`materias-asignadas?teacher_id=${selectedTeacher}`),
      ])
      const eligList = eligRes.value && !eligRes.value?.error ? (Array.isArray(eligRes.value) ? eligRes.value : []) : []
      const assignList = assignRes.value && !assignRes.value?.error ? (Array.isArray(assignRes.value) ? assignRes.value : []) : []

      setEligible(eligList)
      setAssigned(assignList)
      const sel = {}
      assignList.forEach(a => { sel[a.subject_id] = true })
      eligList.forEach(e => { if (!sel[e.subject_id]) sel[e.subject_id] = false })
      setSelected(sel)
    } catch { setEligible([]); setAssigned([]) }
    finally { setLoading(false) }
  }, [selectedTeacher])

  useEffect(() => { loadData() }, [loadData])

  const toggleSelected = (id) => setSelected(prev => ({ ...prev, [id]: !prev[id] }))

  const handleSave = async () => {
    if (!selectedTeacher) return
    setSaving(true)
    try {
      const subjectIds = Object.entries(selected).filter(([, v]) => v).map(([k]) => Number(k))
      await api.post('profesores-materias', { teacher_id: Number(selectedTeacher), subject_ids: subjectIds })
      notify('Asignaciones guardadas exitosamente')
      loadData()
    } catch (err) { notify(err.message, 'error') }
    finally { setSaving(false) }
  }

  const allItems = [...new Map([...eligible, ...assigned].map(i => [i.subject_id, i])).values()]

  return (
    <>
      <div className="page-header"><h1>Asignar Materias a Profesor</h1></div>
      <div className="page-body">
        {notification && <div className={`notification notification-${notification.type}`}>{notification.msg}<button className="notification-close" onClick={() => setNotification(null)}>&times;</button></div>}
        <div className="toolbar">
          <label className="font-medium text-sm">Profesor:</label>
          <select className="form-control" value={selectedTeacher} onChange={e => setSelectedTeacher(e.target.value)}>
            <option value="">Seleccione un profesor</option>
            {teachers.map(t => <option key={t.teacher_id} value={t.teacher_id}>{t.name}</option>)}
          </select>
        </div>

        {!selectedTeacher ? <div className="empty-state"><p>Seleccione un profesor para asignar materias elegibles</p></div> :
          loading ? <div className="loading"><div className="spinner" />Cargando...</div> : (
            <div className="card">
              <div className="card-header">
                <h3>Materias {allItems.length > 0 && <span className="text-muted text-sm">({Object.values(selected).filter(Boolean).length} seleccionadas)</span>}</h3>
                <button className="btn btn-primary" onClick={handleSave} disabled={saving}>{saving ? 'Guardando...' : 'Guardar Asignaciones'}</button>
              </div>
              <div className="card-body" style={{ padding: 0 }}>
                {allItems.length === 0 ? <div className="empty-state"><p>No hay materias elegibles para este profesor</p></div> : (
                  <div className="checkbox-list">
                    {allItems.map(item => (
                      <label key={item.subject_id} className="checkbox-item">
                        <input type="checkbox" checked={!!selected[item.subject_id]} onChange={() => toggleSelected(item.subject_id)} />
                        <div>
                          <div className="font-medium">{item.subject_name || item.name}</div>
                          <div className="text-sm text-muted">{item.duration_hours}h | Semestre {item.semester}</div>
                        </div>
                      </label>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}
      </div>
    </>
  )
}
