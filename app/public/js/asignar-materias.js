document.addEventListener("DOMContentLoaded", () => {
  const profesorSelect = document.getElementById("profesorSelect");
  const scheduleForm = document.getElementById("scheduleForm");
  const generateButton = document.getElementById("generateButton");
  const generateSaturdayButton = document.getElementById(
    "generateSaturdayButton"
  );
  const messageDiv = document.getElementById("message");
  const scheduleContainer = document.getElementById("scheduleContainer");
  const profesorSchedulesDiv = document.getElementById("profesor-schedules");
  const unassignedSubjectsContainer = document.getElementById(
    "unassigned-subjects-container"
  );
  const unassignedSubjectsList = document.getElementById(
    "unassigned-subjects-list"
  );

  const apiUrl = "http://localhost/horarios-iutepi/api";
  const token = localStorage.getItem("authToken");

  // Ocultar botones de la lógica anterior
  generateButton.style.display = "none";
  generateSaturdayButton.style.display = "none";

  // Crear el botón para guardar asignaciones
  const saveButton = document.createElement("button");
  saveButton.type = "button";
  saveButton.id = "saveAssignmentsButton";
  saveButton.textContent = "Guardar Asignaciones";
  saveButton.style.display = "none";
  scheduleForm.appendChild(saveButton);

  /*=============================================
    FUNCIÓN PARA MOSTRAR MENSAJES DE ESTADO
    =============================================*/
  function showMessage(msg, type) {
    messageDiv.textContent = msg;
    messageDiv.className = `message ${type}`;
    messageDiv.style.display = "block";
  }

  /*=============================================
    FUNCIÓN PARA OCULTAR TODOS LOS CONTENEDORES DE RESULTADOS
    =============================================*/
  function hideAllResults() {
    messageDiv.style.display = "none";
    profesorSchedulesDiv.innerHTML = "";
    unassignedSubjectsList.innerHTML = "";
    unassignedSubjectsContainer.style.display = "none";
    saveButton.style.display = "none";
  }

  /*=============================================
    OBTENER Y CARGAR LA LISTA DE PROFESORES
    =============================================*/
  async function loadProfesores() {
    try {
      const response = await fetch(`${apiUrl}/profesores`, {
        method: "GET",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
      });

      const data = await response.json();

      if (data.success && data.data && data.data.length > 0) {
        profesorSelect.innerHTML =
          '<option value="">Selecciona un profesor</option>';
        data.data.forEach((profesor) => {
          const option = document.createElement("option");
          option.value = profesor.teacher_id;
          option.textContent = profesor.name;
          profesorSelect.appendChild(option);
        });
      } else {
        profesorSelect.innerHTML =
          '<option value="">No se encontraron profesores</option>';
        showMessage("No se pudieron cargar los profesores.", "error");
      }
    } catch (error) {
      console.error("Error al cargar profesores:", error);
      profesorSelect.innerHTML = '<option value="">Error al cargar</option>';
      showMessage("Error de red al cargar los profesores.", "error");
    }
  }

  /*=============================================
    GENERAR Y MOSTRAR LAS MATERIAS ELEGIBLES
    =============================================*/
  async function showEligibleSubjects() {
    hideAllResults();

    const profesorId = profesorSelect.value;
    if (!profesorId) {
      showMessage("Por favor, selecciona un profesor.", "error");
      return;
    }

    showMessage("Cargando materias elegibles...", "info");

    try {
      const response = await fetch(
        `${apiUrl}/profesores-materias?teacher_id=${profesorId}`,
        {
          method: "GET",
          headers: {
            "Content-Type": "application/json",
            Authorization: `Bearer ${token}`,
          },
        }
      );

      const data = await response.json();

      if (data.success) {
        if (data.data && data.data.length > 0) {
          displayEligibleSubjects(data.data);
          saveButton.style.display = "block";
        } else {
          showMessage("El profesor no tiene materias elegibles.", "info");
          saveButton.style.display = "none";
        }
      } else {
        showMessage(data.message, "error");
      }
    } catch (error) {
      console.error("Error al obtener las materias:", error);
      showMessage("Error de red al obtener las materias elegibles.", "error");
    }
  }

  /*=============================================
    RENDERIZAR LAS MATERIAS ELEGIBLES CON CHECKBOXES
    =============================================*/
  function displayEligibleSubjects(subjects) {
    unassignedSubjectsContainer.style.display = "block";
    const title = unassignedSubjectsContainer.querySelector("h2");
    title.textContent = "Materias Elegibles";
    unassignedSubjectsList.innerHTML = "";

    subjects.forEach((subject) => {
      const listItem = document.createElement("li");
      const checkbox = document.createElement("input");
      checkbox.type = "checkbox";
      checkbox.name = "subjectSelection";
      checkbox.value = subject.subject_id;
      checkbox.id = `subject-${subject.subject_id}`;

      const label = document.createElement("label");
      label.htmlFor = `subject-${subject.subject_id}`;
      label.textContent = `${subject.name} (Semestre: ${subject.semester}, Horas: ${subject.duration_hours})`;

      listItem.appendChild(checkbox);
      listItem.appendChild(label);
      unassignedSubjectsList.appendChild(listItem);
    });
  }

  /*=============================================
    GUARDAR ASIGNACIONES SELECCIONADAS
    =============================================*/
  async function saveAssignments() {
    const profesorId = profesorSelect.value;
    const selectedSubjects = Array.from(
      document.querySelectorAll('input[name="subjectSelection"]:checked')
    ).map((checkbox) => parseInt(checkbox.value));

    hideAllResults();

    if (selectedSubjects.length === 0) {
      showMessage("No has seleccionado ninguna materia.", "error");
      return;
    }

    showMessage("Guardando asignaciones...", "info");

    try {
      const response = await fetch(`${apiUrl}/profesores-materias`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${token}`,
        },
        body: JSON.stringify({
          teacher_id: parseInt(profesorId),
          subject_ids: selectedSubjects,
        }),
      });

      const data = await response.json();
      if (data.success) {
        showMessage(data.message, "success");
      } else {
        showMessage(data.message, "error");
      }
    } catch (error) {
      console.error("Error al guardar las asignaciones:", error);
      showMessage("Error de red al guardar las asignaciones.", "error");
    }
  }

  /*=============================================
    EVENTOS
    =============================================*/
  profesorSelect.addEventListener("change", showEligibleSubjects);
  saveButton.addEventListener("click", saveAssignments);

  // Cargar los profesores al iniciar la página
  loadProfesores();
});
