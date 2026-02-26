const API_URL = "http://localhost/horarios-iutepi/api";

// Placeholder para el token de autenticación.
// En una aplicación real, lo obtendrías de una respuesta de inicio de sesión.
const authToken = localStorage.getItem("authToken");

document.addEventListener("DOMContentLoaded", () => {
  const teacherSelect = document.getElementById("teacher-select");
  const generateBtn = document.getElementById("generate-schedule-btn");
  const assignedSubjectsList = document.getElementById(
    "assigned-subjects-list"
  );
  const scheduleContainer = document.getElementById("schedule-container");
  const scheduleTableContainer = document.getElementById(
    "schedule-table-container"
  );
  const confirmBtn = document.getElementById("confirm-schedule-btn");
  const statusMessage = document.getElementById("status-message");

  let provisionalSchedule = [];

  // Función de ayuda para crear los encabezados con el token
  function getAuthHeaders() {
    return {
      Authorization: `Bearer ${authToken}`,
    };
  }

  // Fetch y carga de profesores
  async function fetchTeachers() {
    try {
      const response = await fetch(`${API_URL}/profesores`, {
        headers: getAuthHeaders(),
      });
      const result = await response.json();
      teacherSelect.innerHTML = "";
      if (result.success && result.data.length > 0) {
        result.data.forEach((teacher) => {
          const option = document.createElement("option");
          option.value = teacher.teacher_id;
          option.textContent = teacher.name + " " + teacher.ci_code;
          teacherSelect.appendChild(option);
        });
        teacherSelect.value = "";
        teacherSelect.prepend(
          Object.assign(document.createElement("option"), {
            value: "",
            disabled: true,
            selected: true,
            textContent: "Seleccione un profesor",
          })
        );
      } else {
        teacherSelect.innerHTML =
          '<option value="">No hay profesores disponibles</option>';
      }
    } catch (error) {
      console.error("Error fetching teachers:", error);
      statusMessage.textContent = "Error al cargar profesores.";
      statusMessage.className = "status-message text-red";
    }
  }

  // Fetch y muestra las materias asignadas
  async function fetchAssignedSubjects(teacherId) {
    assignedSubjectsList.innerHTML = "<p>Cargando...</p>";
    try {
      const response = await fetch(
        `${API_URL}/materias-asignadas?teacher_id=${teacherId}`,
        {
          headers: getAuthHeaders(),
        }
      );
      const result = await response.json();
      if (result.success && result.data.length > 0) {
        assignedSubjectsList.innerHTML = "";
        result.data.forEach((subject) => {
          const p = document.createElement("p");
          p.textContent = subject.subject_name;
          assignedSubjectsList.appendChild(p);
        });
      } else {
        assignedSubjectsList.innerHTML =
          "<p>Este profesor no tiene materias asignadas.</p>";
      }
    } catch (error) {
      console.error("Error fetching assigned subjects:", error);
      assignedSubjectsList.innerHTML =
        '<p class="text-red">Error al cargar materias.</p>';
    }
  }

  // Generar y renderizar el horario provisional
  async function generateSchedule() {
    const teacherId = teacherSelect.value;
    if (!teacherId) {
      statusMessage.textContent = "Por favor, seleccione un profesor.";
      statusMessage.className = "status-message text-red";
      return;
    }

    statusMessage.textContent = "Generando horario...";
    statusMessage.className = "status-message text-blue";
    scheduleContainer.classList.add("hidden");

    try {
      const response = await fetch(
        `${API_URL}/generar-horario?teacher_id=${teacherId}`,
        {
          headers: getAuthHeaders(),
        }
      );
      const result = await response.json();

      if (result.success && result.data.length > 0) {
        provisionalSchedule = result.data;
        renderScheduleTable(result.data);
        scheduleContainer.classList.remove("hidden");
        statusMessage.textContent =
          "Horario provisional generado con éxito. Revise la tabla a continuación.";
        statusMessage.className = "status-message text-green";
      } else {
        scheduleContainer.classList.add("hidden");
        statusMessage.textContent =
          result.message || "No se pudo generar un horario.";
        statusMessage.className = "status-message text-red";
      }
    } catch (error) {
      console.error("Error generating schedule:", error);
      statusMessage.textContent = "Error al generar el horario.";
      statusMessage.className = "status-message text-red";
      scheduleContainer.classList.add("hidden");
    }
  }

  // Renderizar la tabla del horario
  function renderScheduleTable(scheduleData) {
    const days = [
      "Lunes",
      "Martes",
      "Miércoles",
      "Jueves",
      "Viernes",
      "Sábado",
      "Domingo",
    ];
    const scheduleByDay = days.reduce((acc, day) => {
      acc[day] = scheduleData.filter((slot) => slot.day_of_week === day);
      return acc;
    }, {});

    // Obtener todas las horas únicas
    const allTimes = [
      ...new Set(scheduleData.map((slot) => slot.start_time)),
    ].sort();

    let tableHtml = `<table class="schedule-table"><thead><tr><th>Hora</th>`;
    days.forEach((day) => {
      tableHtml += `<th>${day}</th>`;
    });
    tableHtml += `</tr></thead><tbody>`;

    allTimes.forEach((time) => {
      tableHtml += `<tr><td>${time.slice(0, 5)} - ${new Date(
        new Date().setHours(time.slice(0, 2), time.slice(3, 5)) + 3600000
      )
        .toTimeString()
        .slice(0, 5)}</td>`;
      days.forEach((day) => {
        const slot = scheduleByDay[day].find((s) => s.start_time === time);
        tableHtml += `<td>${slot ? slot.name : ""}</td>`;
      });
      tableHtml += `</tr>`;
    });

    tableHtml += `</tbody></table>`;
    scheduleTableContainer.innerHTML = tableHtml;
  }

  // Confirmar y guardar el horario
  async function confirmSchedule() {
    if (provisionalSchedule.length === 0) {
      statusMessage.textContent = "No hay un horario provisional para guardar.";
      statusMessage.className = "status-message text-red";
      return;
    }

    statusMessage.textContent = "Guardando horario...";
    statusMessage.className = "status-message text-blue";

    try {
      const response = await fetch(`${API_URL}/confirmar-horario`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          ...getAuthHeaders(), // Fusiona los encabezados de autenticación
        },
        body: JSON.stringify({ horario: provisionalSchedule }),
      });
      const result = await response.json();

      if (result.success) {
        statusMessage.textContent = "¡Horario guardado con éxito!";
        statusMessage.className = "status-message text-green";
      } else {
        statusMessage.textContent =
          result.message || "Error al guardar el horario.";
        statusMessage.className = "status-message text-red";
      }
    } catch (error) {
      console.error("Error confirming schedule:", error);
      statusMessage.textContent = "Error de red al guardar el horario.";
      statusMessage.className = "status-message text-red";
    }
  }

  // Listeners
  teacherSelect.addEventListener("change", (e) => {
    const teacherId = e.target.value;
    if (teacherId) {
      fetchAssignedSubjects(teacherId);
    }
  });

  generateBtn.addEventListener("click", generateSchedule);
  confirmBtn.addEventListener("click", confirmSchedule);

  fetchTeachers();
});
