import { useState, useEffect, useCallback } from 'react'
import { api } from '../services/api'
import Modal from '../components/Modal'

export default function Skills() {
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(true)
  const [modal, setModal] = useState(null)
  const [form, setForm] = useState({ skill_name: '' })
  const [notification, setNotification] = useState(null)

  const notify = useCallback((msg, type = 'success') => {
    setNotification({ msg, type }); setTimeout(() => setNotification(null), 3000)
  }, [])

  const load = useCallback(async () => {
    try { const res = await api.get('habilidades'); setData(Array.isArray(res) ? res : []) }
    catch { setData([]) }
    finally { setLoading(false) }
  }, [])

  useEffect(() => { load() }, [load])

  const openCreate = () => { setForm({ skill_name: '' }); setModal('create') }
  const openEdit = (item) => { setForm({ skill_name: item.skill_name }); setModal({ type: 'edit', id: item.skill_id }) }

  const handleSave = async (e) => {
    e.preventDefault()
    try {
      if (modal === 'create') { await api.post('habilidades', form); notify('Habilidad creada') }
      else { await api.put('habilidades', { ...form, skill_id: modal.id }); notify('Habilidad actualizada') }
      setModal(null); load()
    } catch (err) { notify(err.message, 'error') }
  }

  const handleDelete = async (id) => {
    if (!window.confirm('¿Eliminar esta habilidad?')) return
    try { await api.delete(`habilidades?id=${id}`); notify('Habilidad eliminada'); load() }
    catch (err) { notify(err.message, 'error') }
  }

  return (
    <>
      <div className="page-header">
        <h1>Habilidades</h1>
        <button className="btn btn-primary" onClick={openCreate}>+ Nueva Habilidad</button>
      </div>
      <div className="page-body">
        {notification && <div className={`notification notification-${notification.type}`}>{notification.msg}<button className="notification-close" onClick={() => setNotification(null)}>&times;</button></div>}
        {loading ? <div className="loading"><div className="spinner" />Cargando...</div> : (
          <div className="card">
            <div className="table-container">
              <table>
                <thead><tr><th>ID</th><th>Nombre</th><th>Acciones</th></tr></thead>
                <tbody>
                  {data.length === 0 ? <tr><td colSpan={3} className="text-center text-muted">No hay habilidades registradas</td></tr> :
                    data.map(item => (
                      <tr key={item.skill_id}>
                        <td>{item.skill_id}</td>
                        <td className="font-medium">{item.skill_name}</td>
                        <td>
                          <button className="btn btn-sm btn-outline" onClick={() => openEdit(item)}>Editar</button>{' '}
                          <button className="btn btn-sm btn-danger" onClick={() => handleDelete(item.skill_id)}>Eliminar</button>
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
        {modal && (
          <Modal title={modal === 'create' ? 'Nueva Habilidad' : 'Editar Habilidad'} onClose={() => setModal(null)}>
            <form onSubmit={handleSave}>
              <div className="form-group"><label>Nombre de la habilidad</label><input className="form-control" value={form.skill_name} onChange={e => setForm({ ...form, skill_name: e.target.value })} required /></div>
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
