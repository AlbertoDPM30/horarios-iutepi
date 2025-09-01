document.addEventListener("DOMContentLoaded", () => {
  const BASE_URL = "http://localhost/horarios-iutepi/api";
  const loginForm = document.getElementById("loginForm");
  const messageElement = document.getElementById("message");

  // Función para mostrar mensajes de éxito o error
  const showMessage = (msg, type) => {
    messageElement.textContent = msg;
    messageElement.className = `message ${type}`;
    setTimeout(() => {
      messageElement.style.display = "none";
    }, 5000);
  };

  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const username = document.getElementById("username").value;
    const password = document.getElementById("password").value;

    try {
      const response = await fetch(`${BASE_URL}/login`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify({ username, password }),
      });

      const result = await response.json();

      if (response.ok && result.success) {
        // Almacenar el token en el almacenamiento local del navegador
        localStorage.setItem("authToken", result.token);
        showMessage(
          result.message || "Inicio de sesión exitoso. Redirigiendo...",
          "success"
        );
        window.location.href = "index.html"; // Redirigir a la página principal
      } else {
        showMessage(result.message || "Error de inicio de sesión.", "error");
      }
    } catch (error) {
      console.error("Login error:", error);
      showMessage("Error de red al intentar iniciar sesión.", "error");
    }
  });
});
