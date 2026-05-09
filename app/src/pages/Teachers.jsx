import { useState, useEffect, useCallback } from 'react'
import { api } from '../services/api'
import Modal from '../components/Modal'

export default function Teachers() {
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(true)
  const [modal, setModal] = useState(null)
  const [form, setForm] = useState({ name: '', ci_code: '', phone_number: '', email: '' })
  const [notification, setNotification] = useState(null)

  const notify = useCallback((msg, type = 'success') => {
    setNotification({ msg, type })
    setTimeout(() => setNotification(null), 3000)
  }, [])

  const load = useCallback(async () => {
    try {
      const res = await api.get('profesores')
      setData(Array.isArray(res) ? res : [])
    } catch { setData([]) }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { load() }, [load])

  const openCreate = () => {
    setForm({ name: '', ci_code: '', phone_number: '', email: '' })
    setModal('create')
  }

  const openEdit = (item) => {
    setForm({ name: item.name, ci_code: item.ci_code, phone_number: item.phone_number || '', email: item.email || '' })
    setModal({ type: 'edit', id: item.teacher_id })
  }

  const handleSave = async (e) => {
    e.preventDefault()
    try {
      if (modal === 'create') {
        await api.post('profesores', form)
        notify('Profesor creado exitosamente')
      } else {
        await api.put('profesores', { ...form, teacher_id: modal.id })
        notify('Profesor actualizado exitosamente')
      }
      setModal(null)
      load()
    } catch (err) { notify(err.message, 'error') }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('¿Eliminar este profesor?')) return
    try {
      await api.delete(`profesores?id=${id}`)
      notify('Profesor eliminado')
      load()
    } catch (err) { notify(err.message, 'error') }
  }

  return (
    <>
      <div className="page-header">
        <h1>Profesores</h1>
        <button className="btn btn-primary" onClick={openCreate}>+ Nuevo Profesor</button>
      </div>
      <div className="page-body">
        {notification && <div className={`notification notification-${notification.type}`}>{notification.msg}<button className="notification-close" onClick={() => setNotification(null)}>&times;</button></div>}
        {loading ? <div className="loading"><div className="spinner" />Cargando...</div> : (
          <div className="card">
            <div className="table-container">
              <table>
                <thead><tr><th>ID</th><th>Nombre</th><th>Cédula</th><th>Teléfono</th><th>Email</th><th>Acciones</th></tr></thead>
                <tbody>
                  {data.length === 0 ? <tr><td colSpan={6} className="text-center text-muted">No hay profesores registrados</td></tr> :
                    data.map(item => (
                      <tr key={item.teacher_id}>
                        <td>{item.teacher_id}</td>
                        <td className="font-medium">{item.name}</td>
                        <td>{item.ci_code}</td>
                        <td>{item.phone_number || '—'}</td>
                        <td>{item.email || '—'}</td>
                        <td>
                          <button className="btn btn-sm btn-outline" onClick={() => openEdit(item)}>Editar</button>{' '}
                          <button className="btn btn-sm btn-danger" onClick={() => handleDelete(item.teacher_id)}>Eliminar</button>
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
        {modal && (
          <Modal title={modal === 'create' ? 'Nuevo Profesor' : 'Editar Profesor'} onClose={() => setModal(null)}>
            <form onSubmit={handleSave}>
              <div className="form-group"><label>Nombre completo</label><input className="form-control" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required /></div>
              <div className="form-row">
                <div className="form-group"><label>Cédula</label><input className="form-control" value={form.ci_code} onChange={e => setForm({ ...form, ci_code: e.target.value })} required /></div>
                <div className="form-group"><label>Teléfono</label><input className="form-control" value={form.phone_number} onChange={e => setForm({ ...form, phone_number: e.target.value })} /></div>
              </div>
              <div className="form-group"><label>Email</label><input type="email" className="form-control" value={form.email} onChange={e => setForm({ ...form, email: e.target.value })} /></div>
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
