import { useState, useEffect, useCallback } from 'react'
import { api } from '../services/api'
import Modal from '../components/Modal'

export default function Subjects() {
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(true)
  const [modal, setModal] = useState(null)
  const [form, setForm] = useState({ name: '', duration_hours: '', semester: '' })
  const [notification, setNotification] = useState(null)

  const notify = useCallback((msg, type = 'success') => {
    setNotification({ msg, type }); setTimeout(() => setNotification(null), 3000)
  }, [])

  const load = useCallback(async () => {
    try { const res = await api.get('materias'); setData(Array.isArray(res) ? res : []) }
    catch { setData([]) }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { load() }, [load])

  const openCreate = () => { setForm({ name: '', duration_hours: '', semester: '' }); setModal('create') }
  const openEdit = (item) => { setForm({ name: item.name, duration_hours: item.duration_hours, semester: item.semester }); setModal({ type: 'edit', id: item.subject_id }) }

  const handleSave = async (e) => {
    e.preventDefault()
    try {
      const payload = { ...form, duration_hours: Number(form.duration_hours), semester: Number(form.semester) }
      if (modal === 'create') { await api.post('materias', payload); notify('Materia creada') }
      else { await api.put('materias', { ...payload, subject_id: modal.id }); notify('Materia actualizada') }
      setModal(null); load()
    } catch (err) { notify(err.message, 'error') }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('¿Eliminar esta materia?')) return
    try { await api.delete(`materias?id=${id}`); notify('Materia eliminada'); load() }
    catch (err) { notify(err.message, 'error') }
  }

  const toggleAsignacion = async (item) => {
    try { await api.patch('materias', { subject_id: item.subject_id, is_assigned: item.is_assigned ? 0 : 1 }); notify('Estado actualizado'); load() }
    catch (err) { notify(err.message, 'error') }
  }

  return (
    <>
      <div className="page-header">
        <h1>Materias</h1>
        <button className="btn btn-primary" onClick={openCreate}>+ Nueva Materia</button>
      </div>
      <div className="page-body">
        {notification && <div className={`notification notification-${notification.type}`}>{notification.msg}<button className="notification-close" onClick={() => setNotification(null)}>&times;</button></div>}
        {loading ? <div className="loading"><div className="spinner" />Cargando...</div> : (
          <div className="card">
            <div className="table-container">
              <table>
                <thead><tr><th>ID</th><th>Nombre</th><th>Horas</th><th>Semestre</th><th>Asignada</th><th>Acciones</th></tr></thead>
                <tbody>
                  {data.length === 0 ? <tr><td colSpan={6} className="text-center text-muted">No hay materias registradas</td></tr> :
                    data.map(item => (
                      <tr key={item.subject_id}>
                        <td>{item.subject_id}</td>
                        <td className="font-medium">{item.name}</td>
                        <td>{item.duration_hours}h</td>
                        <td>{item.semester}</td>
                        <td><span className={`badge ${item.is_assigned ? 'badge-success' : 'badge-warning'}`}>{item.is_assigned ? 'Sí' : 'No'}</span></td>
                        <td>
                          <button className="btn btn-sm btn-outline" onClick={() => openEdit(item)}>Editar</button>{' '}
                          <button className="btn btn-sm btn-warning" onClick={() => toggleAsignacion(item)}>{item.is_assigned ? 'Desmarcar' : 'Marcar'}</button>{' '}
                          <button className="btn btn-sm btn-danger" onClick={() => handleDelete(item.subject_id)}>Eliminar</button>
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
        {modal && (
          <Modal title={modal === 'create' ? 'Nueva Materia' : 'Editar Materia'} onClose={() => setModal(null)}>
            <form onSubmit={handleSave}>
              <div className="form-group"><label>Nombre</label><input className="form-control" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required /></div>
              <div className="form-row">
                <div className="form-group"><label>Duración (horas)</label><input type="number" className="form-control" value={form.duration_hours} onChange={e => setForm({ ...form, duration_hours: e.target.value })} required /></div>
                <div className="form-group"><label>Semestre</label><input type="number" className="form-control" value={form.semester} onChange={e => setForm({ ...form, semester: e.target.value })} required /></div>
              </div>
              <div className="form-actions">
                <button type="button" className="btn btn-outline" onClick={() => setModal(null)}>Cancelar</button>
                <button type="submit" className="btn btn-primary">{modal === 'create' ? 'Crear' : 'Guardar'}</button>
              </div>
            </form>
          </Modal>
        )}
      </div>
    </>
  )
}
