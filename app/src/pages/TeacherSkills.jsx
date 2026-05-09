import { useState, useEffect, useCallback } from 'react'
import { api } from '../services/api'
import Modal from '../components/Modal'

export default function TeacherSkills() {
  const [teachers, setTeachers] = useState([])
  const [skills, setSkills] = useState([])
  const [selectedTeacher, setSelectedTeacher] = useState('')
  const [assignments, setAssignments] = useState([])
  const [loading, setLoading] = useState(false)
  const [modal, setModal] = useState(null)
  const [form, setForm] = useState({ skill_id: '', stars: 3 })
  const [notification, setNotification] = useState(null)

  const notify = useCallback((msg, type = 'success') => {
    setNotification({ msg, type }); setTimeout(() => setNotification(null), 3000)
  }, [])

  useEffect(() => {
    api.get('profesores').then(r => setTeachers(Array.isArray(r) ? r : [])).catch(() => {})
    api.get('habilidades').then(r => setSkills(Array.isArray(r) ? r : [])).catch(() => {})
  }, [])

  const loadAssignments = useCallback(async () => {
    if (!selectedTeacher) { setAssignments([]); return }
    setLoading(true)
    try {
      const res = await api.get(`profesores-habilidades?teacher_id=${selectedTeacher}`)
      setAssignments(Array.isArray(res) ? res : [])
    } catch { setAssignments([]) }
    finally { setLoading(false) }
  }, [selectedTeacher])

  useEffect(() => { loadAssignments() }, [loadAssignments])

  const openCreate = () => { setForm({ skill_id: skills[0]?.skill_id || '', stars: 3 }); setModal('create') }
  const openEdit = (item) => { setForm({ skill_id: item.skill_id, stars: item.stars }); setModal({ type: 'edit', id: item.teacher_skill_id }) }

  const handleSave = async (e) => {
    e.preventDefault()
    if (!selectedTeacher) { notify('Seleccione un profesor', 'error'); return }
    try {
      const payload = { teacher_id: Number(selectedTeacher), skill_id: Number(form.skill_id), stars: Number(form.stars) }
      if (modal === 'create') { await api.post('profesores-habilidades', payload); notify('Habilidad asignada') }
      else { await api.put('profesores-habilidades', { ...payload, teacher_skill_id: modal.id }); notify('Asignación actualizada') }
      setModal(null); loadAssignments()
    } catch (err) { notify(err.message, 'error') }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('¿Eliminar esta asignación?')) return
    try { await api.delete(`profesores-habilidades?teacher_skill_id=${id}`); notify('Asignación eliminada'); loadAssignments() }
    catch (err) { notify(err.message, 'error') }
  }

  const getSkillName = (id) => skills.find(s => s.skill_id === id)?.skill_name || `ID: ${id}`

  return (
    <>
      <div className="page-header"><h1>Habilidades por Profesor</h1></div>
      <div className="page-body">
        {notification && <div className={`notification notification-${notification.type}`}>{notification.msg}<button className="notification-close" onClick={() => setNotification(null)}>&times;</button></div>}
        <div className="toolbar">
          <label className="font-medium text-sm">Profesor:</label>
          <select className="form-control" value={selectedTeacher} onChange={e => setSelectedTeacher(e.target.value)}>
            <option value="">Seleccione un profesor</option>
            {teachers.map(t => <option key={t.teacher_id} value={t.teacher_id}>{t.name}</option>)}
          </select>
          {selectedTeacher && <button className="btn btn-primary" onClick={openCreate}>+ Asignar Habilidad</button>}
        </div>

        {!selectedTeacher ? <div className="empty-state"><p>Seleccione un profesor para asignar habilidades</p></div> :
          loading ? <div className="loading"><div className="spinner" />Cargando...</div> : (
            <div className="card">
              <div className="table-container">
                <table>
                  <thead><tr><th>Habilidad</th><th>Nivel</th><th>Acciones</th></tr></thead>
                  <tbody>
                    {assignments.length === 0 ? <tr><td colSpan={3} className="text-center text-muted">Sin habilidades asignadas</td></tr> :
                      assignments.map(item => (
                        <tr key={item.teacher_skill_id}>
                          <td className="font-medium">{getSkillName(item.skill_id)}</td>
                          <td>
                            <div className="stars">
                              {[1,2,3,4,5].map(s => <span key={s} className={`star ${s <= item.stars ? 'active' : ''}`}>★</span>)}
                            </div>
                          </td>
                          <td>
                            <button className="btn btn-sm btn-outline" onClick={() => openEdit(item)}>Editar</button>{' '}
                            <button className="btn btn-sm btn-danger" onClick={() => handleDelete(item.teacher_skill_id)}>Eliminar</button>
                          </td>
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

        {modal && (
          <Modal title={modal === 'create' ? 'Asignar Habilidad' : 'Editar Asignación'} onClose={() => setModal(null)}>
            <form onSubmit={handleSave}>
              <div className="form-group">
                <label>Habilidad</label>
                <select className="form-control" value={form.skill_id} onChange={e => setForm({ ...form, skill_id: e.target.value })} required>
                  <option value="">Seleccione</option>
                  {skills.map(s => <option key={s.skill_id} value={s.skill_id}>{s.skill_name}</option>)}
                </select>
              </div>
              <div className="form-group">
                <label>Nivel (estrellas)</label>
                <div className="stars" style={{ marginTop: '0.5rem' }}>
                  {[1,2,3,4,5].map(s => (
                    <span key={s} className={`star ${s <= form.stars ? 'active' : ''}`} onClick={() => setForm({ ...form, stars: s })}>★</span>
                  ))}
                </div>
              </div>
              <div className="form-actions">
                <button type="button" className="btn btn-outline" onClick={() => setModal(null)}>Cancelar</button>
                <button type="submit" className="btn btn-primary">{modal === 'create' ? 'Asignar' : 'Guardar'}</button>
              </div>
            </form>
          </Modal>
        )}
      </div>
    </>
  )
}
