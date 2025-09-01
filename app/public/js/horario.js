document.addEventListener("DOMContentLoaded", () => {
  const generateWeekdayButton = document.getElementById(
    "generateWeekdayButton"
  );
  const generateSaturdayButton = document.getElementById(
    "generateSaturdayButton"
  );
  const weekdayScheduleContainer = document.getElementById(
    "weekdayScheduleContainer"
  );
  const saturdayScheduleContainer = document.getElementById(
    "saturdayScheduleContainer"
  );
  const unassignedSubjectsContainer = document.getElementById(
    "unassigned-subjects-container"
  );
  const messageElement = document.getElementById("message");

  const API_ENDPOINT =
    "http://localhost/horarios-iutepi/api/profesores-materias";

  // Maneja el clic en el botón de generar horario semanal
  generateWeekdayButton.addEventListener("click", () => {
    generateSchedule(false);
  });

  // Maneja el clic en el botón de generar horario del sábado
  generateSaturdayButton.addEventListener("click", () => {
    generateSchedule(true);
  });

  /**
   * Muestra un mensaje en la interfaz de usuario.
   * @param {string} text - El texto del mensaje.
   * @param {string} type - El tipo de mensaje ('success', 'error', 'info').
   */
  function showMessage(text, type) {
    messageElement.textContent = text;
    messageElement.className = `message ${type}`;
    messageElement.style.display = "block";
  }

  /**
   * Limpia los contenedores de horario y mensajes.
   */
  function clearResults() {
    messageElement.style.display = "none";
    weekdayScheduleContainer.style.display = "none";
    saturdayScheduleContainer.style.display = "none";
    unassignedSubjectsContainer.style.display = "none";
    document.getElementById("profesor-schedules-weekday").innerHTML = "";
    document.getElementById("profesor-schedules-saturday").innerHTML = "";
    document.getElementById("unassigned-subjects-list").innerHTML = "";
  }

  /**
   * Realiza la llamada a la API para generar el horario.
   * @param {boolean} isSaturday - Indica si se debe generar el horario del sábado.
   */
  async function generateSchedule(isSaturday) {
    clearResults();
    showMessage("Generando horarios...", "info");

    // Obtener el token del localStorage
    const authToken = localStorage.getItem("authToken");

    // Si no hay token, mostrar un error y detener la ejecución
    if (!authToken) {
      showMessage(
        "Error: No se encontró el token de autenticación. Por favor, inicie sesión nuevamente.",
        "error"
      );
      return;
    }

    try {
      const response = await fetch(API_ENDPOINT, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          // Agregar el token como un Bearer Token en el encabezado Authorization
          Authorization: `Bearer ${authToken}`,
        },
        body: JSON.stringify({ is_saturday: isSaturday }),
      });

      const data = await response.json();

      if (response.ok && data.success) {
        showMessage("Horario generado con éxito.", "success");
        if (isSaturday) {
          renderSchedule(data.data.profesores, "saturday");
        } else {
          renderSchedule(data.data.profesores, "weekday");
          renderUnassignedSubjects(data.data.materias_sin_asignar);
        }
      } else {
        showMessage(`Error: ${data.message}`, "error");
      }
    } catch (error) {
      console.error("Error al generar horarios:", error);
      showMessage(
        "Error del servidor. Por favor, inténtalo de nuevo más tarde.",
        "error"
      );
    }
  }

  /**
   * Renderiza el horario generado en la interfaz.
   * @param {Array} profesores - Los datos de los profesores y sus horarios.
   * @param {string} type - El tipo de horario a renderizar ('weekday' o 'saturday').
   */
  function renderSchedule(profesores, type) {
    const containerId =
      type === "saturday"
        ? "profesor-schedules-saturday"
        : "profesor-schedules-weekday";
    const scheduleContainer = document.getElementById(containerId);
    const displayContainer =
      type === "saturday"
        ? saturdayScheduleContainer
        : weekdayScheduleContainer;

    displayContainer.style.display = "block";

    profesores.forEach((profesor) => {
      if (Object.keys(profesor.horario_detallado).length > 0) {
        const profesorDiv = document.createElement("div");
        profesorDiv.className = "profesor-schedule-card";

        const profesorTitle = document.createElement("h3");
        profesorTitle.textContent = profesor.nombre;
        profesorDiv.appendChild(profesorTitle);

        const scheduleTable = createScheduleTable(profesor.horario_detallado);
        profesorDiv.appendChild(scheduleTable);

        scheduleContainer.appendChild(profesorDiv);
      }
    });
  }

  /**
   * Crea una tabla de horario a partir de los datos.
   * @param {object} horarioDetallado - El objeto con el horario por día.
   * @returns {HTMLTableElement} La tabla HTML del horario.
   */
  function createScheduleTable(horarioDetallado) {
    const table = document.createElement("table");
    table.className = "schedule-table";

    // Encabezados de la tabla
    const thead = table.createTHead();
    const headerRow = thead.insertRow();
    ["Día", "Materia", "Hora de Inicio", "Hora de Fin"].forEach((text) => {
      const th = document.createElement("th");
      th.textContent = text;
      headerRow.appendChild(th);
    });

    // Cuerpo de la tabla
    const tbody = document.createElement("tbody");
    const daysOrder = [
      "Lunes",
      "Martes",
      "Miércoles",
      "Jueves",
      "Viernes",
      "Sábado",
      "Domingo",
    ];

    daysOrder.forEach((day) => {
      if (horarioDetallado[day]) {
        horarioDetallado[day].forEach((clase) => {
          const row = tbody.insertRow();
          const cell1 = row.insertCell(0);
          const cell2 = row.insertCell(1);
          const cell3 = row.insertCell(2);
          const cell4 = row.insertCell(3);

          cell1.textContent = day;
          cell2.textContent = clase.materia_nombre;
          cell3.textContent = clase.inicio;
          cell4.textContent = clase.fin;
        });
      }
    });

    table.appendChild(tbody);
    return table;
  }

  /**
   * Renderiza las materias que no pudieron ser asignadas.
   * @param {Array} materiasSinAsignar - Las materias que no se asignaron.
   */
  function renderUnassignedSubjects(materiasSinAsignar) {
    const list = document.getElementById("unassigned-subjects-list");
    if (materiasSinAsignar.length > 0) {
      unassignedSubjectsContainer.style.display = "block";
      materiasSinAsignar.forEach((materia) => {
        const li = document.createElement("li");
        li.textContent = `${materia.nombre} (${materia.horas_semana} horas)`;
        list.appendChild(li);
      });
    }
  }
});
