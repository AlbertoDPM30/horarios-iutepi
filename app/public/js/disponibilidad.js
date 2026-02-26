document.addEventListener("DOMContentLoaded", () => {
  const BASE_URL = "http://localhost/horarios-iutepi/api";
  const profesorSelect = document.getElementById("profesorSelect");
  const dayButtons = document.querySelectorAll(".day-btn");
  const availabilityForm = document.getElementById("availabilityForm");
  const resetButton = document.getElementById("resetButton");
  const messageElement = document.getElementById("message");
  const availabilityTableBody = document.querySelector(
    "#availabilityTable tbody"
  );

  // Variables de caché
  const profesoresData = {};
  let availabilitiesData = [];

  // Elementos de filtro
  const filterProfesor = document.getElementById("filterProfesor");
  const filterDay = document.getElementById("filterDay");
  const filterStart = document.getElementById("filterStart");
  const filterEnd = document.getElementById("filterEnd");

  // Obtener el token del almacenamiento local
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
    setTimeout(() => {
      messageElement.style.display = "none";
    }, 5000);
  };

  // Funcionalidad para seleccionar días
  dayButtons.forEach((button) => {
    button.addEventListener("click", () => {
      button.classList.toggle("selected");
    });
  });

  // Función para filtrar la tabla
  const filterTable = () => {
    const profesorQuery = filterProfesor.value.toLowerCase();
    const dayQuery = filterDay.value.toLowerCase();
    const startQuery = filterStart.value;
    const endQuery = filterEnd.value;

    const filteredAvailabilities = availabilitiesData.filter((availability) => {
      const profesorNombre =
        profesoresData[availability.teacher_id]?.name.toLowerCase() || "";
      const dayNombre = availability.day_of_week.toLowerCase();
      const startHora = availability.start_time;
      const endHora = availability.end_time;

      const matchesProfesor = profesorNombre.includes(profesorQuery);
      const matchesDay = dayNombre.includes(dayQuery);
      const matchesStart = startHora.includes(startQuery);
      const matchesEnd = endHora.includes(endQuery);

      return matchesProfesor && matchesDay && matchesStart && matchesEnd;
    });

    renderTable(filteredAvailabilities);
  };

  // Agregar "listeners" a los campos de filtro
  filterProfesor.addEventListener("keyup", filterTable);
  filterDay.addEventListener("keyup", filterTable);
  filterStart.addEventListener("input", filterTable);
  filterEnd.addEventListener("input", filterTable);

  // Cargar las disponibilidades
  const fetchAvailabilities = async () => {
    try {
      const response = await fetch(`${BASE_URL}/profesores-disponibilidad`, {
        headers: authHeaders,
      });
      if (!response.ok) {
        throw new Error("Error al obtener las disponibilidades.");
      }
      const result = await response.json();

      availabilitiesData = Array.isArray(result.data) ? result.data : [];
      renderTable(availabilitiesData);
    } catch (error) {
      console.error("Fetch error:", error);
      showMessage("Error de conexión o token inválido.", "error");
      availabilityTableBody.innerHTML =
        '<tr><td colspan="5">No se pudieron cargar las disponibilidades.</td></tr>';
    }
  };

  // Renderizar los datos en la tabla
  const renderTable = (availabilities) => {
    availabilityTableBody.innerHTML = "";
    if (availabilities && availabilities.length > 0) {
      availabilities.forEach((availability) => {
        const profesor =
          profesoresData[availability.teacher_id]?.name ||
          "Profesor desconocido";

        const row = document.createElement("tr");
        row.innerHTML = `
                    <td>${profesor}</td>
                    <td>${availability.day_of_week}</td>
                    <td>${availability.start_time.substring(0, 5)}</td>
                    <td>${availability.end_time.substring(0, 5)}</td>
                    <td>
                        <button class="delete-btn" data-id="${
                          availability.availability_id
                        }">Eliminar</button>
                    </td>
                `;
        availabilityTableBody.appendChild(row);
      });
    } else {
      availabilityTableBody.innerHTML =
        '<tr><td colspan="5">No hay disponibilidades que coincidan con la búsqueda.</td></tr>';
    }

    // Agregar listeners a los botones de la tabla
    document.querySelectorAll(".delete-btn").forEach((button) => {
      button.addEventListener("click", handleDelete);
    });
  };

  // Cargar profesores y almacenar en el objeto
  const fetchProfesores = async () => {
    try {
      const response = await fetch(`${BASE_URL}/profesores`, {
        headers: authHeaders,
      });
      if (!response.ok) {
        throw new Error("Error al obtener los profesores.");
      }
      const data = await response.json();
      if (data.data) {
        data.data.forEach((profesor) => {
          profesoresData[profesor.teacher_id] = profesor;
          const option = document.createElement("option");
          option.value = profesor.teacher_id;
          option.textContent = profesor.name;
          profesorSelect.appendChild(option);
        });
      } else {
        showMessage("No se encontraron profesores.", "error");
      }
    } catch (error) {
      console.error("Fetch error:", error);
      showMessage("Error de conexión o token inválido.", "error");
    }
  };

  // Manejar el envío del formulario
  const handleSubmit = async (e) => {
    e.preventDefault();

    const teacher_id = profesorSelect.value;
    const selectedDays = Array.from(
      document.querySelectorAll(".day-btn.selected")
    );

    if (selectedDays.length === 0) {
      showMessage("Por favor, selecciona al menos un día.", "error");
      return;
    }

    let allSuccessful = true;
    for (const dayBtn of selectedDays) {
      const day_of_week = dayBtn.dataset.day;
      const dayContainer = dayBtn.closest(".day-container");
      const startTimeInput = dayContainer.querySelector(".start-time-input");
      const endTimeInput = dayContainer.querySelector(".end-time-input");

      if (!startTimeInput.value || !endTimeInput.value) {
        showMessage(
          `Por favor, completa las horas para el día ${day_of_week}.`,
          "error"
        );
        allSuccessful = false;
        break;
      }

      const start_time = startTimeInput.value + ":00";
      const end_time = endTimeInput.value + ":00";

      const data = { teacher_id, day_of_week, start_time, end_time };

      try {
        const response = await fetch(`${BASE_URL}/profesores-disponibilidad`, {
          method: "POST",
          headers: authHeaders,
          body: JSON.stringify(data),
        });
        const result = await response.json();
        if (!response.ok) {
          allSuccessful = false;
          showMessage(
            result.message || `Error al asignar el día ${day_of_week}.`,
            "error"
          );
          break;
        }
      } catch (error) {
        console.error("Submission error:", error);
        allSuccessful = false;
        showMessage(
          `Error de red al intentar asignar el día ${day_of_week}.`,
          "error"
        );
        break;
      }
    }

    if (allSuccessful) {
      showMessage("Disponibilidades asignadas con éxito.", "success");
      resetForm();
      fetchAvailabilities();
    }
  };

  // Manejar la eliminación
  const handleDelete = async (e) => {
    const id = e.target.dataset.id;
    if (
      !confirm("¿Estás seguro de que quieres eliminar esta disponibilidad?")
    ) {
      return;
    }
    try {
      const response = await fetch(
        `${BASE_URL}/profesores-disponibilidad?availability_id=${id}`,
        {
          method: "DELETE",
          headers: authHeaders,
        }
      );

      const result = await response.json();
      if (response.ok) {
        showMessage(
          result.message || "Disponibilidad eliminada con éxito.",
          "success"
        );
        fetchAvailabilities();
      } else {
        showMessage(
          result.message || "Error al eliminar la disponibilidad.",
          "error"
        );
      }
    } catch (error) {
      console.error("Delete error:", error);
      showMessage("Error de red al eliminar la disponibilidad.", "error");
    }
  };

  // Función para reiniciar el formulario
  const resetForm = () => {
    availabilityForm.reset();
    dayButtons.forEach((btn) => btn.classList.remove("selected"));
    showMessage("", ""); // Limpiar el mensaje
  };

  // Cargar todos los datos iniciales
  Promise.all([fetchProfesores()])
    .then(() => {
      fetchAvailabilities();
    })
    .catch((error) => {
      console.error("Error cargando datos iniciales:", error);
    });

  availabilityForm.addEventListener("submit", handleSubmit);
  resetButton.addEventListener("click", resetForm);
});
