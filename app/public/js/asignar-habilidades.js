document.addEventListener("DOMContentLoaded", () => {
  const BASE_URL = "http://localhost/horarios-iutepi/api";
  const profesorSelect = document.getElementById("profesorSelect");
  const habilidadSelect = document.getElementById("habilidadSelect");
  const starsInput = document.getElementById("starsInput");
  const form = document.getElementById("assignmentForm");
  const messageElement = document.getElementById("message");
  const assignmentsTableBody = document.querySelector(
    "#assignmentsTable tbody"
  );

  // Variables de caché
  const profesoresData = {};
  const habilidadesData = {};
  let assignmentsData = []; // Variable para almacenar todas las asignaciones

  // Elementos de filtro
  const filterProfesor = document.getElementById("filterProfesor");
  const filterHabilidad = document.getElementById("filterHabilidad");
  const filterEstrellas = document.getElementById("filterEstrellas");

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

  // Función para filtrar la tabla
  const filterTable = () => {
    const profesorQuery = filterProfesor.value.toLowerCase();
    const habilidadQuery = filterHabilidad.value.toLowerCase();
    const estrellasQuery = filterEstrellas.value.toLowerCase();

    const filteredAssignments = assignmentsData.filter((assignment) => {
      const profesorNombre =
        profesoresData[assignment.teacher_id]?.name.toLowerCase() || "";
      const habilidadNombre =
        habilidadesData[assignment.skill_id]?.skill_name.toLowerCase() || "";
      const estrellasValor = assignment.stars
        ? assignment.stars.toString()
        : "";

      const matchesProfesor = profesorNombre.includes(profesorQuery);
      const matchesHabilidad = habilidadNombre.includes(habilidadQuery);
      const matchesEstrellas = estrellasValor.includes(estrellasQuery);

      return matchesProfesor && matchesHabilidad && matchesEstrellas;
    });

    renderTable(filteredAssignments);
  };

  // Agregar "listeners" a los campos de filtro
  filterProfesor.addEventListener("keyup", filterTable);
  filterHabilidad.addEventListener("keyup", filterTable);
  filterEstrellas.addEventListener("keyup", filterTable);

  const fetchAssignments = async () => {
    try {
      const response = await fetch(`${BASE_URL}/profesores-habilidades`, {
        headers: authHeaders,
      });
      if (!response.ok) {
        throw new Error("Error al obtener las asignaciones.");
      }
      const result = await response.json();

      let assignments = [];
      if (result.data) {
        if (Array.isArray(result.data)) {
          assignments = result.data;
        } else {
          assignments.push(result.data);
        }
      }

      assignmentsData = assignments; // Almacenar en la variable global
      renderTable(assignmentsData); // Renderizar la tabla completa al inicio
    } catch (error) {
      console.error("Fetch error:", error);
      showMessage("Error de conexión o token inválido.", "error");
      assignmentsTableBody.innerHTML =
        '<tr><td colspan="3">No se pudieron cargar las asignaciones.</td></tr>';
    }
  };

  const renderTable = (assignments) => {
    assignmentsTableBody.innerHTML = "";
    if (assignments && assignments.length > 0) {
      assignments.forEach((assignment) => {
        const profesor =
          profesoresData[assignment.teacher_id]?.name || "Profesor desconocido";
        const habilidad =
          habilidadesData[assignment.skill_id]?.skill_name ||
          "Habilidad desconocida";

        const row = document.createElement("tr");
        row.innerHTML = `
                    <td>${profesor}</td>
                    <td>${habilidad}</td>
                    <td>${assignment.stars || "N/A"}</td>
                `;
        assignmentsTableBody.appendChild(row);
      });
    } else {
      assignmentsTableBody.innerHTML =
        '<tr><td colspan="3">No hay asignaciones que coincidan con la búsqueda.</td></tr>';
    }
  };

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

  const fetchHabilidades = async () => {
    try {
      const response = await fetch(`${BASE_URL}/habilidades`, {
        headers: authHeaders,
      });
      if (!response.ok) {
        throw new Error("Error al obtener las habilidades.");
      }
      const data = await response.json();
      if (data.data) {
        data.data.forEach((habilidad) => {
          habilidadesData[habilidad.skill_id] = habilidad;
          const option = document.createElement("option");
          option.value = habilidad.skill_id;
          option.textContent = habilidad.skill_name;
          habilidadSelect.appendChild(option);
        });
      } else {
        showMessage("No se encontraron habilidades.", "error");
      }
    } catch (error) {
      console.error("Fetch error:", error);
      showMessage("Error de conexión o token inválido.", "error");
    }
  };

  Promise.all([fetchProfesores(), fetchHabilidades()])
    .then(() => {
      fetchAssignments();
    })
    .catch((error) => {
      console.error("Error cargando datos iniciales:", error);
    });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const teacher_id = profesorSelect.value;
    const skill_id = habilidadSelect.value;
    const stars = starsInput.value ? parseInt(starsInput.value) : null;

    const data = { teacher_id, skill_id, stars };

    try {
      const response = await fetch(`${BASE_URL}/profesores-habilidades`, {
        method: "POST",
        headers: authHeaders,
        body: JSON.stringify(data),
      });

      const result = await response.json();
      if (response.ok) {
        showMessage(
          result.message || "Habilidad asignada con éxito.",
          "success"
        );
        // Almacenar y renderizar la nueva asignación
        assignmentsData.push(result.data);
        renderTable(assignmentsData);
        // Si la tabla estaba filtrada, se limpiarán los filtros para mostrar la nueva asignación
        filterProfesor.value = "";
        filterHabilidad.value = "";
        filterEstrellas.value = "";
      } else {
        showMessage(
          result.message || "Error al asignar la habilidad.",
          "error"
        );
      }
    } catch (error) {
      console.error("Submission error:", error);
      showMessage("Error de red al intentar asignar la habilidad.", "error");
    }
  });
});
