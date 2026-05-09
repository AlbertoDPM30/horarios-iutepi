import { useState, useEffect, useCallback } from 'react'
import { api } from '../services/api'
import Modal from '../components/Modal'

export default function SubjectSkills() {
  const [subjects, setSubjects] = useState([])
  const [skills, setSkills] = useState([])
  const [selectedSubject, setSelectedSubject] = useState('')
  const [mappings, setMappings] = useState([])
  const [loading, setLoading] = useState(false)
  const [modal, setModal] = useState(null)
  const [form, setForm] = useState({ skill_id: '', min_stars: 1 })
  const [notification, setNotification] = useState(null)

  const notify = useCallback((msg, type = 'success') => {
    setNotification({ msg, type }); setTimeout(() => setNotification(null), 3000)
  }, [])

  useEffect(() => {
    api.get('materias').then(r => setSubjects(Array.isArray(r) ? r : [])).catch(() => {})
    api.get('habilidades').then(r => setSkills(Array.isArray(r) ? r : [])).catch(() => {})
  }, [])

  const loadMappings = useCallback(async () => {
    if (!selectedSubject) { setMappings([]); return }
    setLoading(true)
    try {
      const res = await api.get(`materias-habilidades?subject_id=${selectedSubject}`)
      setMappings(Array.isArray(res) ? res : [])
    } catch { setMappings([]) }
    finally { setLoading(false) }
  }, [selectedSubject])

  useEffect(() => { loadMappings() }, [loadMappings])

  const openCreate = () => { setForm({ skill_id: skills[0]?.skill_id || '', min_stars: 1 }); setModal('create') }
  const openEdit = (item) => { setForm({ skill_id: item.skill_id, min_stars: item.min_stars }); setModal({ type: 'edit', id: item.subject_skill_id }) }

  const handleSave = async (e) => {
    e.preventDefault()
    if (!selectedSubject) { notify('Seleccione una materia', 'error'); return }
    try {
      const payload = { subject_id: Number(selectedSubject), skill_id: Number(form.skill_id), min_stars: Number(form.min_stars) }
      if (modal === 'create') { await api.post('materias-habilidades', payload); notify('Habilidad asignada a la materia') }
      else { await api.put('materias-habilidades', { ...payload, subject_skill_id: modal.id }); notify('Asignación actualizada') }
      setModal(null); loadMappings()
    } catch (err) { notify(err.message, 'error') }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('¿Eliminar esta asignación?')) return
    try { await api.delete(`materias-habilidades?subject_skill_id=${id}`); notify('Asignación eliminada'); loadMappings() }
    catch (err) { notify(err.message, 'error') }
  }

  const getSkillName = (id) => skills.find(s => s.skill_id === id)?.skill_name || `ID: ${id}`

  return (
    <>
      <div className="page-header"><h1>Habilidades Requeridas por Materia</h1></div>
      <div className="page-body">
        {notification && <div className={`notification notification-${notification.type}`}>{notification.msg}<button className="notification-close" onClick={() => setNotification(null)}>&times;</button></div>}
        <div className="toolbar">
          <label className="font-medium text-sm">Materia:</label>
          <select className="form-control" value={selectedSubject} onChange={e => setSelectedSubject(e.target.value)}>
            <option value="">Seleccione una materia</option>
            {subjects.map(s => <option key={s.subject_id} value={s.subject_id}>{s.name}</option>)}
          </select>
          {selectedSubject && <button className="btn btn-primary" onClick={openCreate}>+ Agregar Habilidad</button>}
        </div>

        {!selectedSubject ? <div className="empty-state"><p>Seleccione una materia para gestionar sus habilidades requeridas</p></div> :
          loading ? <div className="loading"><div className="spinner" />Cargando...</div> : (
            <div className="card">
              <div className="table-container">
                <table>
                  <thead><tr><th>Habilidad</th><th>Mín. Estrellas</th><th>Acciones</th></tr></thead>
                  <tbody>
                    {mappings.length === 0 ? <tr><td colSpan={3} className="text-center text-muted">Sin habilidades requeridas</td></tr> :
                      mappings.map(item => (
                        <tr key={item.subject_skill_id}>
                          <td className="font-medium">{getSkillName(item.skill_id)}</td>
                          <td>
                            <div className="stars">
                              {[1,2,3,4,5].map(s => <span key={s} className={`star ${s <= item.min_stars ? 'active' : ''}`}>★</span>)}
                            </div>
                          </td>
                          <td>
                            <button className="btn btn-sm btn-outline" onClick={() => openEdit(item)}>Editar</button>{' '}
                            <button className="btn btn-sm btn-danger" onClick={() => handleDelete(item.subject_skill_id)}>Eliminar</button>
                          </td>
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

        {modal && (
          <Modal title={modal === 'create' ? 'Asignar Habilidad a Materia' : 'Editar Asignación'} onClose={() => setModal(null)}>
            <form onSubmit={handleSave}>
              <div className="form-group">
                <label>Habilidad</label>
                <select className="form-control" value={form.skill_id} onChange={e => setForm({ ...form, skill_id: e.target.value })} required>
                  <option value="">Seleccione</option>
                  {skills.map(s => <option key={s.skill_id} value={s.skill_id}>{s.skill_name}</option>)}
                </select>
              </div>
              <div className="form-group">
                <label>Estrellas mínimas requeridas</label>
                <div className="stars" style={{ marginTop: '0.5rem' }}>
                  {[1,2,3,4,5].map(s => (
                    <span key={s} className={`star ${s <= form.min_stars ? 'active' : ''}`} onClick={() => setForm({ ...form, min_stars: s })}>★</span>
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
