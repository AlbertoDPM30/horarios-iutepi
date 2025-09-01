document.addEventListener("DOMContentLoaded", () => {
  const BASE_URL = "http://localhost/horarios-iutepi/api";
  const generateWeekdayButton = document.getElementById(
    "generateWeekdayButton"
  );
  const generateSaturdayButton = document.getElementById(
    "generateSaturdayButton"
  );
  const messageElement = document.getElementById("message");

  const unassignedSubjectsContainer = document.getElementById(
    "unassigned-subjects-container"
  );
  const unassignedSubjectsList = document.getElementById(
    "unassigned-subjects-list"
  );
  const weekdayScheduleContainer = document.getElementById(
    "weekdayScheduleContainer"
  );
  const saturdayScheduleContainer = document.getElementById(
    "saturdayScheduleContainer"
  );
  const weekdaySchedules = document.getElementById(
    "profesor-schedules-weekday"
  );
  const saturdaySchedules = document.getElementById(
    "profesor-schedules-saturday"
  );

  const token = localStorage.getItem("authToken");

  if (!token) {
    window.location.href = "login.html";
    return;
  }

  const authHeaders = {
    Authorization: `Bearer ${token}`,
    "Content-Type": "application/json",
  };

  const showMessage = (msg, type) => {
    messageElement.textContent = msg;
    messageElement.className = `message ${type}`;
    messageElement.style.display = "block";
    setTimeout(() => {
      messageElement.style.display = "none";
    }, 5000);
  };

  const generateSchedule = async (is_saturday, targetContainer) => {
    showMessage("Generando horario...", "info");
    unassignedSubjectsContainer.style.display = "none";
    unassignedSubjectsList.innerHTML = "";
    weekdayScheduleContainer.style.display = "none";
    saturdayScheduleContainer.style.display = "none";
    targetContainer.innerHTML = "";

    try {
      const response = await fetch(`${BASE_URL}/profesores-materias`, {
        method: "POST",
        headers: authHeaders,
        body: JSON.stringify({ is_saturday: is_saturday ? "true" : "false" }),
      });

      const result = await response.json();

      if (response.ok && result.success) {
        showMessage("Horario generado con éxito. ✅", "success");
        renderUnassignedSubjects(result.data.materias_sin_asignar);
        renderTeacherSchedules(
          result.data.profesores,
          is_saturday,
          targetContainer
        );
        if (is_saturday) {
          saturdayScheduleContainer.style.display = "block";
        } else {
          weekdayScheduleContainer.style.display = "block";
        }
      } else {
        showMessage(
          result.message || "Error al generar el horario. ❌",
          "error"
        );
      }
    } catch (error) {
      console.error("Generation error:", error);
      showMessage("Error de red al intentar generar el horario. 🌐", "error");
    }
  };

  const renderUnassignedSubjects = (subjects) => {
    if (!subjects || subjects.length === 0) {
      unassignedSubjectsContainer.style.display = "none";
      return;
    }

    unassignedSubjectsList.innerHTML = "";
    subjects.forEach((subject) => {
      const li = document.createElement("li");
      li.textContent = `${subject.materia} (${subject.seccion}) - ${subject.horario}`;
      unassignedSubjectsList.appendChild(li);
    });
    unassignedSubjectsContainer.style.display = "block";
  };

  const renderTeacherSchedules = (
    profesores,
    is_saturday,
    schedulesContainer
  ) => {
    schedulesContainer.innerHTML = "";
    if (!profesores || profesores.length === 0) {
      schedulesContainer.innerHTML =
        "<p>No se pudo generar un horario para ningún profesor.</p>";
      return;
    }

    const days = is_saturday
      ? ["Sábado"]
      : ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes"];
    const hours = [
      "08:00 - 09:30",
      "09:30 - 11:00",
      "11:00 - 12:30",
      "12:30 - 14:00",
      "14:00 - 15:30",
      "15:30 - 17:00",
    ];

    profesores.forEach((profesor) => {
      const teacherDiv = document.createElement("div");
      teacherDiv.className = "teacher-schedule";
      teacherDiv.innerHTML = `<h3>Horario de ${profesor.nombre}</h3>`;

      const table = document.createElement("table");
      table.className = "schedule-table";
      table.innerHTML = `
                <thead>
                    <tr>
                        <th>Hora</th>
                        ${days.map((day) => `<th>${day}</th>`).join("")}
                    </tr>
                </thead>
                <tbody></tbody>
            `;
      const tbody = table.querySelector("tbody");

      hours.forEach((hourSlot) => {
        const [start, end] = hourSlot.split(" - ");
        const row = document.createElement("tr");
        row.innerHTML = `<td>${hourSlot}</td>`;

        days.forEach((day) => {
          const cell = document.createElement("td");
          const assignedClass = profesor.horario_detallado[day]?.find(
            (item) => item.inicio === start.replace(/\s/g, ":00")
          );

          if (assignedClass) {
            cell.innerHTML = `<strong>${assignedClass.materia_nombre}</strong>`;
          } else {
            cell.textContent = "-";
          }
          row.appendChild(cell);
        });
        tbody.appendChild(row);
      });

      teacherDiv.appendChild(table);
      schedulesContainer.appendChild(teacherDiv);
    });
  };

  generateWeekdayButton.addEventListener("click", () =>
    generateSchedule(false, weekdaySchedules)
  );
  generateSaturdayButton.addEventListener("click", () =>
    generateSchedule(true, saturdaySchedules)
  );
});
