import { useState, useEffect, useCallback } from 'react'
import { api } from '../services/api'
import Modal from '../components/Modal'

export default function Modules() {
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(true)
  const [modal, setModal] = useState(null)
  const [form, setForm] = useState({ name: '', description: '', route: '' })
  const [notification, setNotification] = useState(null)

  const notify = useCallback((msg, type = 'success') => {
    setNotification({ msg, type }); setTimeout(() => setNotification(null), 3000)
  }, [])

  const load = useCallback(async () => {
    try { const res = await api.get('modulos'); setData(Array.isArray(res) ? res : []) }
    catch { setData([]) }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { load() }, [load])

  const openCreate = () => { setForm({ name: '', description: '', route: '' }); setModal('create') }
  const openEdit = (item) => { setForm({ name: item.name, description: item.description || '', route: item.route || '' }); setModal({ type: 'edit', id: item.module_id }) }

  const handleSave = async (e) => {
    e.preventDefault()
    try {
      if (modal === 'create') { await api.post('modulos', form); notify('Módulo creado') }
      else { await api.put('modulos', { ...form, module_id: modal.id }); notify('Módulo actualizado') }
      setModal(null); load()
    } catch (err) { notify(err.message, 'error') }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('¿Eliminar este módulo?')) return
    try { await api.delete(`modulos?id=${id}`); notify('Módulo eliminado'); load() }
    catch (err) { notify(err.message, 'error') }
  }

  return (
    <>
      <div className="page-header">
        <h1>Módulos</h1>
        <button className="btn btn-primary" onClick={openCreate}>+ Nuevo Módulo</button>
      </div>
      <div className="page-body">
        {notification && <div className={`notification notification-${notification.type}`}>{notification.msg}<button className="notification-close" onClick={() => setNotification(null)}>&times;</button></div>}
        {loading ? <div className="loading"><div className="spinner" />Cargando...</div> : (
          <div className="card">
            <div className="table-container">
              <table>
                <thead><tr><th>ID</th><th>Nombre</th><th>Descripción</th><th>Ruta</th><th>Acciones</th></tr></thead>
                <tbody>
                  {data.length === 0 ? <tr><td colSpan={5} className="text-center text-muted">No hay módulos registrados</td></tr> :
                    data.map(item => (
                      <tr key={item.module_id}>
                        <td>{item.module_id}</td>
                        <td className="font-medium">{item.name}</td>
                        <td className="text-muted text-sm">{item.description || '—'}</td>
                        <td className="text-sm">{item.route || '—'}</td>
                        <td>
                          <button className="btn btn-sm btn-outline" onClick={() => openEdit(item)}>Editar</button>{' '}
                          <button className="btn btn-sm btn-danger" onClick={() => handleDelete(item.module_id)}>Eliminar</button>
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
        {modal && (
          <Modal title={modal === 'create' ? 'Nuevo Módulo' : 'Editar Módulo'} onClose={() => setModal(null)}>
            <form onSubmit={handleSave}>
              <div className="form-group"><label>Nombre</label><input className="form-control" value={form.name} onChange={e => setForm({ ...form, name: e.target.value })} required /></div>
              <div className="form-group"><label>Descripción</label><textarea className="form-control" value={form.description} onChange={e => setForm({ ...form, description: e.target.value })} rows={2} /></div>
              <div className="form-group"><label>Ruta</label><input className="form-control" value={form.route} onChange={e => setForm({ ...form, route: e.target.value })} /></div>
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
