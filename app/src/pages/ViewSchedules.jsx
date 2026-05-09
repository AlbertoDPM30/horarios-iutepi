import { useState, useEffect, useCallback, Fragment } from "react";
import { api } from "../services/api";

const days = ["LUNES", "MARTES", "MIÉRCOLES", "JUEVES", "SÁBADO"];
const hours = Array.from(
  { length: 12 },
  (_, i) => `${String(i + 7).padStart(2, "0")}:00`,
);

export default function ViewSchedules() {
  const [teachers, setTeachers] = useState([]);
  const [selectedTeacher, setSelectedTeacher] = useState("");
  const [schedules, setSchedules] = useState([]);
  const [loading, setLoading] = useState(false);
  const [loaded, setLoaded] = useState(false);
  const [notification, setNotification] = useState(null);
  const [subjectMap, setSubjectMap] = useState({});

  const notify = useCallback((msg, type = "success") => {
    setNotification({ msg, type });
    setTimeout(() => setNotification(null), 3000);
  }, []);

  useEffect(() => {
    api
      .get("profesores")
      .then((r) => setTeachers(Array.isArray(r) ? r : []))
      .catch(() => {});
  }, []);

  const loadSchedules = useCallback(async () => {
    if (!selectedTeacher) {
      setSchedules([]);
      setLoaded(false);
      return;
    }
    setLoading(true);
    setLoaded(false);
    try {
      const [schedRes, assignsRes] = await Promise.all([
        api.get(`horarios?teacher_id=${selectedTeacher}`),
        api
          .get(`materias-asignadas?teacher_id=${selectedTeacher}`)
          .catch(() => null),
      ]);
      const list = Array.isArray(schedRes) ? schedRes : [];
      const assigns = Array.isArray(assignsRes) ? assignsRes : [];
      const map = {};
      assigns.forEach((a) => {
        map[a.assignment_id] = a.name;
      });
      setSubjectMap(map);
      list.forEach((item) => {
        if (!item.subject_name && !item.name) {
          item.subject_name = map[item.teacher_subject_assignment_id];
        }
      });
      setSchedules(list);
    } catch {
      setSchedules([]);
      notify("Error al cargar horarios", "error");
    } finally {
      setLoading(false);
      setLoaded(true);
    }
  }, [selectedTeacher, notify]);

  useEffect(() => {
    loadSchedules();
  }, [loadSchedules]);

  const toH = (t) => (t ? String(t).slice(0, 5) : "");

  const handleDelete = async (id) => {
    if (!window.confirm("¿Eliminar esta entrada del horario?")) return;
    try {
      await api.delete(`horarios?schedule_id=${id}`);
      notify("Entrada eliminada");
      loadSchedules();
    } catch (err) {
      notify(err.message, "error");
    }
  };

  const getCell = (day, hour) => {
    const [h] = hour.split(":").map(Number);
    const entry = schedules.find((e) => {
      if (e.day_of_week !== day) return false;
      const [sh] = String(e.start_time).split(":").map(Number);
      const [eh] = String(e.end_time).split(":").map(Number);
      return sh <= h && eh > h;
    });
    if (!entry) return null;
    return {
      ...entry,
      subject_name:
        entry.subject_name ||
        entry.name ||
        subjectMap[entry.teacher_subject_assignment_id],
    };
  };

  return (
    <>
      <div className="page-header">
        <h1>Ver Horarios</h1>
      </div>
      <div className="page-body">
        {notification && (
          <div className={`notification notification-${notification.type}`}>
            {notification.msg}
            <button
              className="notification-close"
              onClick={() => setNotification(null)}
            >
              &times;
            </button>
          </div>
        )}
        <div className="toolbar">
          <label className="font-medium text-sm">Profesor:</label>
          <select
            className="form-control"
            value={selectedTeacher}
            onChange={(e) => setSelectedTeacher(e.target.value)}
          >
            <option value="">Seleccione un profesor</option>
            {teachers.map((t) => (
              <option key={t.teacher_id} value={t.teacher_id}>
                {t.name}
              </option>
            ))}
          </select>
        </div>

        {!selectedTeacher ? (
          <div className="empty-state">
            <p>Seleccione un profesor para ver su horario</p>
          </div>
        ) : loading ? (
          <div className="loading">
            <div className="spinner" />
            Cargando...
          </div>
        ) : !loaded || schedules.length === 0 ? (
          <div className="empty-state">
            <p>No hay horarios guardados para este profesor</p>
          </div>
        ) : (
          <>
            {/* <div className="card mb-2">
              <div className="card-header">
                <h3>Vista Semanal</h3>
              </div>
              <div className="card-body" style={{ overflowX: "auto" }}>
                <div
                  className="schedule-grid"
                  style={{
                    gridTemplateColumns: `80px repeat(${days.length}, 1fr)`,
                  }}
                >
                  <div className="schedule-header">Hora</div>
                  {days.map((d) => (
                    <div key={d} className="schedule-header">
                      {d}
                    </div>
                  ))}
                  {hours.map((hour) => (
                    <Fragment key={hour}>
                      <div className="schedule-time">{hour}</div>
                      {days.map((day) => {
                        const cell = getCell(day, hour);
                        return (
                          <div
                            key={`${day}-${hour}`}
                            className={`schedule-cell ${cell ? "has-class" : ""}`}
                          >
                            {cell && (
                              <div>
                                <div className="class-name">
                                  {cell.subject_name || cell.name}
                                </div>
                                <div className="text-sm text-muted">
                                  {toH(cell.start_time)} - {toH(cell.end_time)}
                                </div>
                              </div>
                            )}
                          </div>
                        );
                      })}
                    </Fragment>
                  ))}
                </div>
              </div>
            </div> */}

            <div className="card">
              <div className="card-header">
                <h3>Detalle de Horarios ({schedules.length} entradas)</h3>
              </div>
              <div className="table-container">
                <table>
                  <thead>
                    <tr>
                      <th>Día</th>
                      <th>Materia</th>
                      <th>Inicio</th>
                      <th>Fin</th>
                      <th>Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    {schedules.map((item, i) => (
                      <tr key={item.schedule_id || i}>
                        <td className="font-medium">{item.day_of_week}</td>
                        <td>{item.subject_name || item.name || "—"}</td>
                        <td>{toH(item.start_time)}</td>
                        <td>{toH(item.end_time)}</td>
                        <td>
                          <button
                            className="btn btn-sm btn-danger"
                            onClick={() => handleDelete(item.schedule_id)}
                          >
                            Eliminar
                          </button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
          </>
        )}
      </div>
    </>
  );
}
