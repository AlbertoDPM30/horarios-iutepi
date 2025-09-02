document.addEventListener("DOMContentLoaded", () => {
  const apiBaseUrl = "http://localhost/horarios-iutepi/api";
  const scheduleForm = document.getElementById("scheduleForm");
  const profesorSelect = document.getElementById("profesorSelect");
  const generateButton = document.getElementById("generateButton");
  const generateSaturdayButton = document.getElementById(
    "generateSaturdayButton"
  ); // Nuevo botón
  const messageContainer = document.getElementById("message");
  const scheduleContainer = document.getElementById("scheduleContainer");
  const profesorSchedules = document.getElementById("profesor-schedules");
  const unassignedSubjectsContainer = document.getElementById(
    "unassigned-subjects-container"
  );
  const unassignedSubjectsList = document.getElementById(
    "unassigned-subjects-list"
  );

  const showMessage = (text, type) => {
    messageContainer.textContent = text;
    messageContainer.className = `message ${type}`;
    messageContainer.style.display = "block";
  };

  const fetchProfessors = async () => {
    const authToken = localStorage.getItem("authToken");
    if (!authToken) {
      showMessage(
        "Error: No se encontró el token de autenticación. Por favor, inicie sesión.",
        "error"
      );
      return;
    }

    try {
      const response = await fetch(`${apiBaseUrl}/profesores`, {
        method: "GET",
        headers: {
          Authorization: `Bearer ${authToken}`,
        },
      });

      const result = await response.json();
      if (response.ok && result.success) {
        profesorSelect.innerHTML =
          '<option value="">Seleccione un profesor...</option>';
        result.data.forEach((profesor) => {
          const option = document.createElement("option");
          option.value = profesor.teacher_id;
          option.textContent = profesor.name;
          profesorSelect.appendChild(option);
        });
      } else {
        showMessage(
          result.message || "No se pudo cargar la lista de profesores.",
          "error"
        );
      }
    } catch (error) {
      console.error("Error al cargar profesores:", error);
      showMessage(
        "Error de red al intentar cargar la lista de profesores.",
        "error"
      );
    }
  };

  const renderSchedule = (profesor) => {
    profesorSchedules.innerHTML = "";
    if (!profesor || Object.keys(profesor.horario_detallado).length === 0) {
      profesorSchedules.innerHTML =
        "<p>No se pudieron asignar materias a este profesor o no tiene horario de disponibilidad.</p>";
      return;
    }

    const profesorTitle = document.createElement("h3");
    profesorTitle.textContent = `Horario de ${profesor.nombre}`;
    profesorSchedules.appendChild(profesorTitle);

    const scheduleCardsContainer = document.createElement("div");
    scheduleCardsContainer.className = "schedule-cards-container";
    profesorSchedules.appendChild(scheduleCardsContainer);

    const daysOfWeek = Object.keys(profesor.horario_detallado);
    daysOfWeek.forEach((day) => {
      const dayCard = document.createElement("div");
      dayCard.className = "day-card";

      const dayTitle = document.createElement("h4");
      dayTitle.textContent = day;
      dayCard.appendChild(dayTitle);

      const table = document.createElement("table");
      table.className = "day-schedule-table";
      const tbody = document.createElement("tbody");

      if (
        profesor.horario_detallado[day] &&
        profesor.horario_detallado[day].length > 0
      ) {
        profesor.horario_detallado[day].forEach((clase) => {
          const row = document.createElement("tr");
          let materiaInfo = clase.materia_nombre;
          if (clase.semestre) {
            materiaInfo += ` (${clase.semestre})`;
          }
          row.innerHTML = `
                        <td>${clase.inicio} - ${clase.fin}</td>
                        <td>${materiaInfo}</td>
                    `;
          tbody.appendChild(row);
        });
      } else {
        const row = document.createElement("tr");
        row.innerHTML = `<td>No hay clases asignadas para este día.</td>`;
        tbody.appendChild(row);
      }

      table.appendChild(tbody);
      dayCard.appendChild(table);
      scheduleCardsContainer.appendChild(dayCard);
    });

    scheduleContainer.style.display = "block";
  };

  const renderUnassignedSubjects = (subjects) => {
    unassignedSubjectsList.innerHTML = "";
    if (subjects.length > 0) {
      unassignedSubjectsContainer.style.display = "block";
      subjects.forEach((subject) => {
        const li = document.createElement("li");
        li.textContent = `${subject.nombre} (Semestre: ${subject.semestre})`;
        unassignedSubjectsList.appendChild(li);
      });
    } else {
      unassignedSubjectsContainer.style.display = "none";
    }
  };

  // Función genérica para manejar la generación de horarios
  const generateSchedule = async (endpoint) => {
    const profesorId = profesorSelect.value;
    if (!profesorId) {
      showMessage("Por favor, selecciona un profesor.", "info");
      return;
    }

    showMessage("Generando horario, por favor espere...", "info");
    scheduleContainer.style.display = "none";
    unassignedSubjectsContainer.style.display = "none";

    const authToken = localStorage.getItem("authToken");
    if (!authToken) {
      showMessage(
        "Error: No se encontró el token de autenticación. Por favor, inicie sesión.",
        "error"
      );
      return;
    }

    try {
      const response = await fetch(`${apiBaseUrl}/${endpoint}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${authToken}`,
        },
        body: JSON.stringify({ profesor_id: profesorId }),
      });

      if (response.status === 401) {
        showMessage(
          "No autorizado. Su sesión ha expirado o no tiene permisos.",
          "error"
        );
        return;
      }

      const data = await response.json();

      if (data.success) {
        renderSchedule(data.data.profesor);
        renderUnassignedSubjects(data.data.materias_sin_asignar);
        showMessage("Horario generado exitosamente.", "success");
      } else {
        showMessage(`Error: ${data.message}`, "error");
      }
    } catch (error) {
      console.error("Error al generar el horario:", error);
      showMessage(
        "Error de red al intentar generar el horario. Por favor, inténtelo de nuevo.",
        "error"
      );
    }
  };

  // Event listener para el botón de Lunes a Viernes
  generateButton.addEventListener("click", (e) => {
    e.preventDefault();
    generateSchedule("profesor-horario");
  });

  // Event listener para el nuevo botón de Sábado
  generateSaturdayButton.addEventListener("click", (e) => {
    e.preventDefault();
    generateSchedule("profesor-horario-sabado");
  });

  fetchProfessors();
});
