import { useState, useEffect, useCallback } from 'react'
import { api } from '../services/api'
import Modal from '../components/Modal'

export default function Users() {
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(true)
  const [modal, setModal] = useState(null)
  const [form, setForm] = useState({ first_name: '', last_name: '', ci: '', username: '', password: '' })
  const [notification, setNotification] = useState(null)

  const notify = useCallback((msg, type = 'success') => {
    setNotification({ msg, type }); setTimeout(() => setNotification(null), 3000)
  }, [])

  const load = useCallback(async () => {
    try { const res = await api.get('usuarios'); setData(Array.isArray(res) ? res : []) }
    catch { setData([]) }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { load() }, [load])

  const openCreate = () => { setForm({ first_name: '', last_name: '', ci: '', username: '', password: '' }); setModal('create') }
  const openEdit = (item) => { setForm({ first_name: item.first_name, last_name: item.last_name, ci: item.ci, username: item.username, password: '' }); setModal({ type: 'edit', id: item.user_id }) }

  const handleSave = async (e) => {
    e.preventDefault()
    try {
      const payload = { ...form }
      if (!payload.password) delete payload.password
      if (modal === 'create') { await api.post('usuarios', payload); notify('Usuario creado') }
      else { await api.put('usuarios', { ...payload, user_id: modal.id }); notify('Usuario actualizado') }
      setModal(null); load()
    } catch (err) { notify(err.message, 'error') }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('¿Eliminar este usuario?')) return
    try { await api.delete(`usuarios?id=${id}`); notify('Usuario eliminado'); load() }
    catch (err) { notify(err.message, 'error') }
  }

  const toggleStatus = async (item) => {
    try { await api.patch('usuarios', { user_id: item.user_id, status: item.status ? 0 : 1 }); notify('Estado actualizado'); load() }
    catch (err) { notify(err.message, 'error') }
  }

  return (
    <>
      <div className="page-header">
        <h1>Usuarios</h1>
        <button className="btn btn-primary" onClick={openCreate}>+ Nuevo Usuario</button>
      </div>
      <div className="page-body">
        {notification && <div className={`notification notification-${notification.type}`}>{notification.msg}<button className="notification-close" onClick={() => setNotification(null)}>&times;</button></div>}
        {loading ? <div className="loading"><div className="spinner" />Cargando...</div> : (
          <div className="card">
            <div className="table-container">
              <table>
                <thead><tr><th>ID</th><th>Nombre</th><th>Cédula</th><th>Usuario</th><th>Estado</th><th>Acciones</th></tr></thead>
                <tbody>
                  {data.length === 0 ? <tr><td colSpan={6} className="text-center text-muted">No hay usuarios registrados</td></tr> :
                    data.map(item => (
                      <tr key={item.user_id}>
                        <td>{item.user_id}</td>
                        <td className="font-medium">{item.first_name} {item.last_name}</td>
                        <td>{item.ci}</td>
                        <td>{item.username}</td>
                        <td><span className={`badge ${item.status ? 'badge-success' : 'badge-danger'}`}>{item.status ? 'Activo' : 'Inactivo'}</span></td>
                        <td>
                          <button className="btn btn-sm btn-outline" onClick={() => openEdit(item)}>Editar</button>{' '}
                          <button className="btn btn-sm btn-warning" onClick={() => toggleStatus(item)}>{item.status ? 'Desactivar' : 'Activar'}</button>{' '}
                          <button className="btn btn-sm btn-danger" onClick={() => handleDelete(item.user_id)}>Eliminar</button>
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
        {modal && (
          <Modal title={modal === 'create' ? 'Nuevo Usuario' : 'Editar Usuario'} onClose={() => setModal(null)}>
            <form onSubmit={handleSave}>
              <div className="form-row">
                <div className="form-group"><label>Nombre</label><input className="form-control" value={form.first_name} onChange={e => setForm({ ...form, first_name: e.target.value })} required /></div>
                <div className="form-group"><label>Apellido</label><input className="form-control" value={form.last_name} onChange={e => setForm({ ...form, last_name: e.target.value })} required /></div>
              </div>
              <div className="form-row">
                <div className="form-group"><label>Cédula</label><input className="form-control" value={form.ci} onChange={e => setForm({ ...form, ci: e.target.value })} required /></div>
                <div className="form-group"><label>Usuario</label><input className="form-control" value={form.username} onChange={e => setForm({ ...form, username: e.target.value })} required /></div>
              </div>
              <div className="form-group"><label>Contraseña {modal !== 'create' && <span className="text-muted text-sm">(dejar vacío para mantener)</span>}</label><input type="password" className="form-control" value={form.password} onChange={e => setForm({ ...form, password: e.target.value })} required={modal === 'create'} /></div>
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
