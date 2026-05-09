import { useState, useEffect, useCallback } from 'react'
import { api } from '../services/api'
import Modal from '../components/Modal'

export default function Students() {
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(true)
  const [modal, setModal] = useState(null)
  const [form, setForm] = useState({ name: '', ci_code: '' })
  const [notification, setNotification] = useState(null)

  const notify = useCallback((msg, type = 'success') => {
    setNotification({ msg, type })
    setTimeout(() => setNotification(null), 3000)
  }, [])

  const load = useCallback(async () => {
    try { const res = await api.get('estudiantes'); setData(Array.isArray(res) ? res : []) }
    catch { setData([]) }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { load() }, [load])

  const openCreate = () => { setForm({ name: '', ci_code: '' }); setModal('create') }
  const openEdit = (item) => { setForm({ name: item.name, ci_code: item.ci_code }); setModal({ type: 'edit', id: item.student_id }) }

  const handleSave = async (e) => {
    e.preventDefault()
    try {
      if (modal === 'create') { await api.post('estudiantes', form); notify('Estudiante creado') }
      else { await api.put('estudiantes', { ...form, student_id: modal.id }); notify('Estudiante actualizado') }
      setModal(null); load()
    } catch (err) { notify(err.message, 'error') }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('¿Eliminar este estudiante?')) return
    try { await api.delete(`estudiantes?id=${id}`); notify('Estudiante eliminado'); load() }
    catch (err) { notify(err.message, 'error') }
  }

  return (
    <>
      <div className="page-header">
        <h1>Estudiantes</h1>
        <button className="btn btn-primary" onClick={openCreate}>+ Nuevo Estudiante</button>
      </div>
      <div className="page-body">
        {notification && <div className={`notification notification-${notification.type}`}>{notification.msg}<button className="notification-close" onClick={() => setNotification(null)}>&times;</button></div>}
        {loading ? <div className="loading"><div className="spinner" />Cargando...</div> : (
          <div className="card">
            <div className="table-container">
              <table>
                <thead><tr><th>ID</th><th>Nombre</th><th>Cédula</th><th>Acciones</th></tr></thead>
                <tbody>
                  {data.length === 0 ? <tr><td colSpan={4} className="text-center text-muted">No hay estudiantes registrados</td></tr> :
                    data.map(item => (
                      <tr key={item.student_id}>
                        <td>{item.student_id}</td>
                        <td className="font-medium">{item.name}</td>
                        <td>{item.ci_code}</td>
                        <td>
                          <button className="btn btn-sm btn-outline" onClick={() => openEdit(item)}>Editar</button>{' '}
                          <button className="btn btn-sm btn-danger" onClick={() => handleDelete(item.student_id)}>Eliminar</button>
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
        {modal && (
          <Modal title={modal === 'create' ? 'Nuevo Estudiante' : 'Editar Estudiante'} onClose={() => setModal(null)}>
            <form onSubmit={handleSave}>
              <div className="form-group"><label>Nombre completo</label><input className="form-control" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required /></div>
              <div className="form-group"><label>Cédula</label><input className="form-control" value={form.ci_code} onChange={e => setForm({ ...form, ci_code: e.target.value })} required /></div>
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
