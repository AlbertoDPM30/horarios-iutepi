document.addEventListener("DOMContentLoaded", () => {
  const BASE_URL = "http://localhost/horarios-iutepi/api";
  const materiaSelect = document.getElementById("materiaSelect");
  const habilidadSelect = document.getElementById("habilidadSelect");
  const minStarsInput = document.getElementById("minStarsInput");
  const form = document.getElementById("assignmentForm");
  const messageElement = document.getElementById("message");
  const assignmentsTableBody = document.querySelector(
    "#assignmentsTable tbody"
  );

  // Variables de caché
  const materiasData = {};
  const habilidadesData = {};
  let assignmentsData = [];

  // Elementos de filtro
  const filterMateria = document.getElementById("filterMateria");
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
    const materiaQuery = filterMateria.value.toLowerCase();
    const habilidadQuery = filterHabilidad.value.toLowerCase();
    const estrellasQuery = filterEstrellas.value.toLowerCase();

    const filteredAssignments = assignmentsData.filter((assignment) => {
      const materiaNombre =
        materiasData[assignment.subject_id]?.name.toLowerCase() || "";
      const habilidadNombre =
        habilidadesData[assignment.skill_id]?.skill_name.toLowerCase() || "";
      const estrellasValor = assignment.min_stars
        ? assignment.min_stars.toString()
        : "";

      const matchesMateria = materiaNombre.includes(materiaQuery);
      const matchesHabilidad = habilidadNombre.includes(habilidadQuery);
      const matchesEstrellas = estrellasValor.includes(estrellasQuery);

      return matchesMateria && matchesHabilidad && matchesEstrellas;
    });

    renderTable(filteredAssignments);
  };

  // Agregar "listeners" a los campos de filtro
  filterMateria.addEventListener("keyup", filterTable);
  filterHabilidad.addEventListener("keyup", filterTable);
  filterEstrellas.addEventListener("keyup", filterTable);

  // Cargar las asignaciones y llenar la tabla
  const fetchAssignments = async () => {
    try {
      const response = await fetch(`${BASE_URL}/materias-habilidades`, {
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

      assignmentsData = assignments;
      renderTable(assignmentsData);
    } catch (error) {
      console.error("Fetch error:", error);
      showMessage("Error de conexión o token inválido.", "error");
      assignmentsTableBody.innerHTML =
        '<tr><td colspan="3">No se pudieron cargar las asignaciones.</td></tr>';
    }
  };

  // Renderizar los datos en la tabla
  const renderTable = (assignments) => {
    assignmentsTableBody.innerHTML = "";
    if (assignments && assignments.length > 0) {
      assignments.forEach((assignment) => {
        const materia =
          materiasData[assignment.subject_id]?.name || "Materia desconocida";
        const habilidad =
          habilidadesData[assignment.skill_id]?.skill_name ||
          "Habilidad desconocida";

        const row = document.createElement("tr");
        row.innerHTML = `
                    <td>${materia}</td>
                    <td>${habilidad}</td>
                    <td>${assignment.min_stars || "N/A"}</td>
                `;
        assignmentsTableBody.appendChild(row);
      });
    } else {
      assignmentsTableBody.innerHTML =
        '<tr><td colspan="3">No hay asignaciones que coincidan con la búsqueda.</td></tr>';
    }
  };

  // Obtener materias y almacenar en el objeto
  const fetchMaterias = async () => {
    try {
      const response = await fetch(`${BASE_URL}/materias`, {
        headers: authHeaders,
      });
      if (!response.ok) {
        throw new Error("Error al obtener las materias.");
      }
      const data = await response.json();
      if (data.data) {
        data.data.forEach((materia) => {
          materiasData[materia.subject_id] = materia;
          const option = document.createElement("option");
          option.value = materia.subject_id;
          option.textContent = materia.name;
          materiaSelect.appendChild(option);
        });
      } else {
        showMessage("No se encontraron materias.", "error");
      }
    } catch (error) {
      console.error("Fetch error:", error);
      showMessage("Error de conexión o token inválido.", "error");
    }
  };

  // Obtener habilidades y almacenar en el objeto
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

  // Cargar todos los datos al iniciar la página
  Promise.all([fetchMaterias(), fetchHabilidades()])
    .then(() => {
      fetchAssignments();
    })
    .catch((error) => {
      console.error("Error cargando datos iniciales:", error);
    });

  // Manejar el envío del formulario
  form.addEventListener("submit", async (e) => {
    e.preventDefault();

    const subject_id = materiaSelect.value;
    const skill_id = habilidadSelect.value;
    const min_stars = minStarsInput.value
      ? parseInt(minStarsInput.value)
      : null;

    const data = { subject_id, skill_id, min_stars };

    try {
      const response = await fetch(`${BASE_URL}/materias-habilidades`, {
        method: "POST",
        headers: authHeaders,
        body: JSON.stringify(data),
      });

      const result = await response.json();
      if (response.ok) {
        showMessage(
          result.message || "Habilidad asignada a materia con éxito.",
          "success"
        );
        // Agregar la nueva asignación a la data y renderizar
        if (result.data) {
          assignmentsData.push(result.data);
          renderTable(assignmentsData);
        } else {
          // Si el servidor no devuelve la data, volvemos a obtener todas las asignaciones
          fetchAssignments();
        }

        // Limpiar filtros
        filterMateria.value = "";
        filterHabilidad.value = "";
        filterEstrellas.value = "";
      } else {
        showMessage(
          result.message || "Error al asignar la habilidad a la materia.",
          "error"
        );
      }
    } catch (error) {
      console.error("Submission error:", error);
      showMessage(
        "Error de red al intentar asignar la habilidad a la materia.",
        "error"
      );
    }
  });
});
