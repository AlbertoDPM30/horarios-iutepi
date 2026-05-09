import { useState, useEffect, useCallback, Fragment } from "react";
import { api } from "../services/api";

const days = ["LUNES", "MARTES", "MIÉRCOLES", "JUEVES", "SÁBADO"];
const hours = Array.from(
  { length: 12 },
  (_, i) => `${String(i + 7).padStart(2, "0")}:00`,
);

export default function GenerateSchedule() {
  const [teachers, setTeachers] = useState([]);
  const [selectedTeacher, setSelectedTeacher] = useState("");
  const [schedule, setSchedule] = useState(null);
  const [loading, setLoading] = useState(false);
  const [generating, setGenerating] = useState(false);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [notification, setNotification] = useState(null);

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

  const toH = (t) => (t ? String(t).slice(0, 5) : "");

  const handleGenerate = async () => {
    if (!selectedTeacher) {
      notify("Seleccione un profesor", "error");
      return;
    }
    setGenerating(true);
    setSaved(false);
    setSchedule(null);
    try {
      const res = await api.get(
        `generar-horario?teacher_id=${selectedTeacher}`,
      );
      if (Array.isArray(res)) {
        setSchedule(res);
      } else {
        notify("No se pudo generar el horario", "error");
      }
    } catch (err) {
      notify(err.message, "error");
    } finally {
      setGenerating(false);
    }
  };

  const handleConfirm = async () => {
    if (!schedule || !selectedTeacher) return;
    setSaving(true);
    try {
      await api.post("confirmar-horario", {
        teacher_id: Number(selectedTeacher),
        horario: schedule,
      });
      setSaved(true);
      notify("Horario confirmado y guardado exitosamente");
    } catch (err) {
      notify(err.message, "error");
    } finally {
      setSaving(false);
    }
  };

  const getCell = (day, hour) => {
    if (!schedule) return null;
    const entries = Array.isArray(schedule) ? schedule : [];
    const hourNum = parseInt(hour);
    return entries.find(
      (e) =>
        e.day_of_week === day &&
        parseInt(e.start_time) <= hourNum &&
        parseInt(e.end_time) > hourNum,
    );
  };

  const getDuration = (entries, day, startHour) => {
    let count = 0;
    for (let h = startHour; h < 19; h++) {
      const has = entries.some(
        (e) =>
          e.day_of_week === day &&
          parseInt(e.start_time) <= h &&
          parseInt(e.end_time) > h,
      );
      if (has) count++;
      else break;
    }
    return count;
  };

  return (
    <>
      <div className="page-header">
        <h1>Generar Horario</h1>
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
            onChange={(e) => {
              setSelectedTeacher(e.target.value);
              setSchedule(null);
              setSaved(false);
            }}
          >
            <option value="">Seleccione un profesor</option>
            {teachers.map((t) => (
              <option key={t.teacher_id} value={t.teacher_id}>
                {t.name}
              </option>
            ))}
          </select>
          <button
            className="btn btn-primary"
            onClick={handleGenerate}
            disabled={!selectedTeacher || generating}
          >
            {generating ? (
              <>
                <span
                  className="spinner"
                  style={{ width: 16, height: 16, marginRight: 6 }}
                />{" "}
                Generando...
              </>
            ) : (
              "⚙️ Generar Horario"
            )}
          </button>
          {schedule && !saved && (
            <button
              className="btn btn-success"
              onClick={handleConfirm}
              disabled={saving}
            >
              {saving ? "Guardando..." : "✅ Confirmar Horario"}
            </button>
          )}
        </div>

        {!selectedTeacher ? (
          <div className="empty-state">
            <p>Seleccione un profesor y genere su horario</p>
          </div>
        ) : !schedule && !generating ? (
          <div className="empty-state">
            <p>
              Presione "Generar Horario" para crear un horario provisional para
              este profesor
            </p>
          </div>
        ) : null}

        {/* {schedule && Array.isArray(schedule) && schedule.length > 0 && (
          <div className="card">
            <div className="card-header">
              <h3>Horario Generado</h3>
              {saved && <span className="badge badge-success">✓ Confirmado</span>}
            </div>
            <div className="card-body" style={{ overflowX: 'auto' }}>
              <div className="schedule-grid" style={{ gridTemplateColumns: `80px repeat(${days.length}, 1fr)` }}>
                <div className="schedule-header">Hora</div>
                {days.map(d => <div key={d} className="schedule-header">{d}</div>)}
                {hours.map(hour => (
                  <Fragment key={hour}>
                    <div className="schedule-time">{hour}</div>
                    {days.map(day => {
                      const cell = getCell(day, hour)
                      return (
                        <div key={`${day}-${hour}`} className={`schedule-cell ${cell ? 'has-class' : ''}`}>
                          {cell && (
                            <div>
                              <div className="class-name">{cell.subject_name || cell.name}</div>
                              <div className="text-sm text-muted">{toH(cell.start_time)} - {toH(cell.end_time)}</div>
                            </div>
                          )}
                        </div>
                      )
                    })}
                  </Fragment>
                ))}
              </div>
            </div>
          </div>
        )} */}

        {schedule && Array.isArray(schedule) && schedule.length > 0 && (
          <div className="card mt-2">
            <div className="card-header">
              <h3>Detalle del Horario</h3>
            </div>
            <div className="table-container">
              <table>
                <thead>
                  <tr>
                    <th>Día</th>
                    <th>Materia</th>
                    <th>Inicio</th>
                    <th>Fin</th>
                  </tr>
                </thead>
                <tbody>
                  {schedule.map((item, i) => (
                    <tr key={i}>
                      <td className="font-medium">{item.day_of_week}</td>
                      <td>{item.subject_name || item.name}</td>
                      <td>{toH(item.start_time)}</td>
                      <td>{toH(item.end_time)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {generating && (
          <div className="loading">
            <div className="spinner" />
            Generando horario...
          </div>
        )}
      </div>
    </>
  );
}
