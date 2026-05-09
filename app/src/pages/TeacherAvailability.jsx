import { useState, useEffect, useCallback } from 'react'
import { api } from '../services/api'
import Modal from '../components/Modal'

const daysOfWeek = ['LUNES', 'MARTES', 'MIÉRCOLES', 'JUEVES', 'VIERNES']

export default function TeacherAvailability() {
  const [teachers, setTeachers] = useState([])
  const [selectedTeacher, setSelectedTeacher] = useState('')
  const [availability, setAvailability] = useState([])
  const [loading, setLoading] = useState(false)
  const [modal, setModal] = useState(null)
  const [form, setForm] = useState({ day_of_week: '', start_time: '07:00', end_time: '08:00' })
  const [notification, setNotification] = useState(null)

  const notify = useCallback((msg, type = 'success') => {
    setNotification({ msg, type }); setTimeout(() => setNotification(null), 3000)
  }, [])

  useEffect(() => {
    api.get('profesores').then(r => setTeachers(Array.isArray(r) ? r : [])).catch(() => {})
  }, [])

  const loadAvailability = useCallback(async () => {
    if (!selectedTeacher) { setAvailability([]); return }
    setLoading(true)
    try {
      const res = await api.get(`profesores-disponibilidad?teacher_id=${selectedTeacher}`)
      setAvailability(Array.isArray(res) ? res : [])
    } catch { setAvailability([]) }
    finally { setLoading(false) }
  }, [selectedTeacher])

  useEffect(() => { loadAvailability() }, [loadAvailability])

  const openCreate = () => { setForm({ day_of_week: 'LUNES', start_time: '07:00', end_time: '08:00' }); setModal('create') }
  const openEdit = (item) => { setForm({ day_of_week: item.day_of_week, start_time: item.start_time, end_time: item.end_time }); setModal({ type: 'edit', id: item.availability_id }) }

  const handleSave = async (e) => {
    e.preventDefault()
    if (!selectedTeacher) { notify('Seleccione un profesor', 'error'); return }
    try {
      const payload = { ...form, teacher_id: Number(selectedTeacher) }
      if (modal === 'create') { await api.post('profesores-disponibilidad', payload); notify('Disponibilidad agregada') }
      else { await api.put('profesores-disponibilidad', { ...payload, availability_id: modal.id }); notify('Disponibilidad actualizada') }
      setModal(null); loadAvailability()
    } catch (err) { notify(err.message, 'error') }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('¿Eliminar esta disponibilidad?')) return
    try { await api.delete(`profesores-disponibilidad?availability_id=${id}`); notify('Disponibilidad eliminada'); loadAvailability() }
    catch (err) { notify(err.message, 'error') }
  }

  return (
    <>
      <div className="page-header"><h1>Disponibilidad de Profesores</h1></div>
      <div className="page-body">
        {notification && <div className={`notification notification-${notification.type}`}>{notification.msg}<button className="notification-close" onClick={() => setNotification(null)}>&times;</button></div>}
        <div className="toolbar">
          <label className="font-medium text-sm">Profesor:</label>
          <select className="form-control" value={selectedTeacher} onChange={e => setSelectedTeacher(e.target.value)}>
            <option value="">Seleccione un profesor</option>
            {teachers.map(t => <option key={t.teacher_id} value={t.teacher_id}>{t.name}</option>)}
          </select>
          {selectedTeacher && <button className="btn btn-primary" onClick={openCreate}>+ Agregar Disponibilidad</button>}
        </div>

        {!selectedTeacher ? <div className="empty-state"><p>Seleccione un profesor para gestionar su disponibilidad</p></div> :
          loading ? <div className="loading"><div className="spinner" />Cargando...</div> : (
            <div className="card">
              <div className="table-container">
                <table>
                  <thead><tr><th>Día</th><th>Hora Inicio</th><th>Hora Fin</th><th>Acciones</th></tr></thead>
                  <tbody>
                    {availability.length === 0 ? <tr><td colSpan={4} className="text-center text-muted">Sin disponibilidad registrada</td></tr> :
                      availability.map(item => (
                        <tr key={item.availability_id}>
                          <td className="font-medium">{item.day_of_week}</td>
                          <td>{item.start_time}</td>
                          <td>{item.end_time}</td>
                          <td>
                            <button className="btn btn-sm btn-outline" onClick={() => openEdit(item)}>Editar</button>{' '}
                            <button className="btn btn-sm btn-danger" onClick={() => handleDelete(item.availability_id)}>Eliminar</button>
                          </td>
                        </tr>
                      ))}
                  </tbody>
                </table>
              </div>
            </div>
          )}

        {modal && (
          <Modal title={modal === 'create' ? 'Agregar Disponibilidad' : 'Editar Disponibilidad'} onClose={() => setModal(null)}>
            <form onSubmit={handleSave}>
              <div className="form-group">
                <label>Día de la semana</label>
                <div className="day-buttons">
                  {daysOfWeek.map(d => (
                    <button key={d} type="button" className={`day-btn ${form.day_of_week === d ? 'active' : ''}`} onClick={() => setForm({ ...form, day_of_week: d })}>{d}</button>
                  ))}
                </div>
              </div>
              <div className="form-row">
                <div className="form-group"><label>Hora inicio</label><input type="time" className="form-control" value={form.start_time} onChange={e => setForm({ ...form, start_time: e.target.value })} required /></div>
                <div className="form-group"><label>Hora fin</label><input type="time" className="form-control" value={form.end_time} onChange={e => setForm({ ...form, end_time: e.target.value })} required /></div>
              </div>
              <div className="form-actions">
                <button type="button" className="btn btn-outline" onClick={() => setModal(null)}>Cancelar</button>
                <button type="submit" className="btn btn-primary">{modal === 'create' ? 'Agregar' : 'Guardar'}</button>
              </div>
            </form>
          </Modal>
        )}
      </div>
    </>
  )
}
