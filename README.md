# Documentación Horarios IUTEPI

### Descripción general:

El presente es un proyecto académico para el uso exclusivo del **Instituto Universitario de Tecnologías para la Informática (IUTEPI)**. Con el fin de lograr una gestión automatizada para la realización de los horarios académicos tanto para el alumnado como el profesorado de la institución, con el menor margen de error posible; Evitando conflictos entre materias, disponibilidad para el uso de los laboratorios, y una mayor flexibilidad para los estudiantes al momento de asistir a sus clases correspondientes.

## API.

### Descripción:

API RESTful desarrollada con PHP en MVC (Modelo-vista-controlador) + PDO (Para el manejo de datos con la BD). Conexión a una base de datos MySQL.

### Estructura de carpetas:

- **config**: Carpeta donde se ejecutan servicios de configuración para el funcionamiento del API.
- **controladores**: Carpeta donde se maneja toda la lógica de cada petición del cliente.
- **core:** Se encuentra un archivo enrutador (router.php) y una sub-carpeta llamada **rutas** donde se hallan todos los Endpoints.
- **servicios:** Archivos de servicios con la lógica funcional de la API.
- **modelos:** Modelos que realizan consultas con la base de datos.
- **vendor:** Librerías y dependencias.
- **.env.example:** Ejemplos de variables de entorno.
- **.gitignore:** Archivos o carpetas excluidas del repositorio de GitHub.
- **.htaccess:** Archivo de texto plano que se utiliza para definir directivas de configuración en servidores web Apache.
- **composer.json:** Archivo JSON con las dependencias del proyecto.
- **composer.lock:** Archivo donde se encuentran las configuraciones generales para todas las librerías instaladas.
- **index.php:** Archivo base que define las rutas de los controladores, modelos y la configuración de CORS.

### Seguridad:

Todas las rutas con la excepción de “/login” y “/logout” se encuentran protegidas. Para acceder a ellas se debe comprobar el *token* de autenticación proporcionado por la misma API al momento de loggearse. Este tiene una duración de 5 Horas antes de expirar. Por lo que el cliente deberá enviar el *token* solicitado en cada petición.

### Uso práctico:

Cada uno de los Endpoints reciben los datos desde un JSON y las peticiones a través de los método CRUD (GET, POST, PUT o PATCH y DELETE).

```jsx
const API_BASE_URL = "https://url-ejemplo.api/";
let currentJwtToken = localStorage.getItem('jwtToken') || null;

async function makeRequest(method, endpoint, data = null, needsAuth = true) {
    const url = `${API_BASE_URL}?ruta=${endpoint}`;
    const headers = {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
    };

    if (needsAuth && currentJwtToken) {
        headers['Authorization'] = `Bearer ${currentJwtToken}`;
    }

    const options = {
        method: method,
        headers: headers
    };

    if (data) {
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(url, options);
        const result = await response.json();

        if (!response.ok) {
            console.error('Error en la petición:', result);
            return { success: false, status: response.status, data: result, message: result.message || 'Error en la petición.' };
        }
        return { success: true, status: response.status, data: result };
    } catch (error) {
        console.error('Error de red o JSON:', error);
        return { success: false, status: 0, data: null, message: error.message };
    }
}
```

## Rutas y Endpoints de la API.

### Inicio de sesión:

<aside>
💡

**Nota:** Los usuarios pueden estar desactivados, y solo un Administrador podrá activarlos; Mientras que los usuarios estén desactivados, no se podrá iniciar sesión y el cliente recibirá la siguiente respuesta:

```json
{
	"status" : 401,
  "success" : false,
  "message" : "Usuario existente no disponible. Comuniquese con un administrador"
}
```

</aside>

- **Endpoint:**
    
    ```
    /login
    ```
    
- **Parámetros:**
    
    ```json
    {
    	"username" : "nombre_usuario",
    	"password" : "p4ssw0rd" 
    }
    ```
    
- **Ejemplo de uso desde el cliente:**
    
    ```jsx
    const API_BASE_URL = "https://url-ejemplo.api/";
    
    let currentJwtToken = localStorage.getItem('jwtToken') || null;
    
    let data = {
    	"username": "usuario_ejemplo",
    	"password": "Ej3mp10"
    };
    
    const needsAuth = false;
    
    async function login(data, needsAuth) {
        const url = `${API_BASE_URL}?ruta=/login`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    
        if (needsAuth && currentJwtToken) {
            headers['Authorization'] = `Bearer ${currentJwtToken}`;
        }
    
        const options = {
            method: "POST",
            headers: headers
        };
    
        if (data) {
            options.body = JSON.stringify(data);
        }
    
        try {
            const response = await fetch(url, options);
            const result = await response.json();
    
            if (!response.ok) {
                console.error('Error en la petición:', result);
                return { success: false, status: response.status, data: result, message: result.message || 'Error en la petición.' };
            }
            return { success: true, status: response.status, data: result };
        } catch (error) {
            console.error('Error de red o JSON:', error);
            return { success: false, status: 0, data: null, message: error.message };
        }
    }
    ```
    
- **Respuesta:**
    
    ```json
    // Si la respuesta es correcta
    {
        "status": 201,
        "success": true,
        "data": {
            "logged:": "ok",
            "id:": 1,
            "nombres:": "Nombre del Usuario",
            "apellidos:": "Apellidos del Usuario",
            "usuario:": "usuario_ejemplo",
            "cedula": "12345678",
            "token": "Hash-con-token-de-validacion"
        },
        "mensaje": "Inicio de sesion exitoso"
    }
    
    // Si ocurrió un error
    {
    	"Error": "Parametros o datos Incorrectos."
    }
    ```
    

### Cerrar sesión:

- **Endpoint:**
    
    ```
    /logout
    ```
    
- **Ejemplo de uso desde el cliente:**
    
    ```jsx
    const API_BASE_URL = "https://url-ejemplo.api/";
    
    let currentJwtToken = localStorage.getItem('jwtToken') || null;
    
    let data = {
    	"user_id": INT
    };
    
    const needsAuth = false;
    
    async function logout(data, needsAuth) {
        const url = `${API_BASE_URL}?ruta=/login`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    
        if (needsAuth && currentJwtToken) {
            headers['Authorization'] = `Bearer ${currentJwtToken}`;
        }
    
        const options = {
            method: "POST",
            headers: headers
        };
    
        if (data) {
            options.body = JSON.stringify(data);
        }
    
        try {
            const response = await fetch(url, options);
            const result = await response.json();
    
            if (!response.ok) {
                console.error('Error en la petición:', result);
                return { success: false, status: response.status, data: result, message: result.message || 'Error en la petición.' };
            }
            return { success: true, status: response.status, data: result };
        } catch (error) {
            console.error('Error de red o JSON:', error);
            return { success: false, status: 0, data: null, message: error.message };
        }
    }
    ```
    
- **Respuesta:**
    
    ```json
    // Si la respuesta es correcta
    {
    	"status": 201,
    	"success": true,
    	"message": "Sesión cerrada correctamente."
    }
    
    // Si ocurrió un error
    {
    	"status": 402,
    	"success": false,
    	"mensaje": "No se pudo cerrar la sesión.",
    	"descripcion": "Parametro invalido o sesión no iniciada."
    }
    ```
    

### Usuarios:

- **Endpoint:**
    
    ```
    /usuarios
    ```
    
- **Obtener todos los usuarios (Ejemplo):**
    
    ```jsx
    const API_BASE_URL = "https://url-ejemplo.api/";
    
    let currentJwtToken = localStorage.getItem('jwtToken') || null;
    
    let user_id = null || INT; // Todos los usuarios = null | Un usuario = ID del usuario
    
    const needsAuth = false;
    
    async function getUsers(user_id, needsAuth) {
        const url = `${API_BASE_URL}?ruta=/login&${user_id}`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    
        if (needsAuth && currentJwtToken) {
            headers['Authorization'] = `Bearer ${currentJwtToken}`;
        }
    
        const options = {
            method: "GET",
            headers: headers
        };
    
        try {
            const response = await fetch(url, options);
            const result = await response.json();
    
            if (!response.ok) {
                console.error('Error en la petición:', result);
                return { success: false, status: response.status, data: result, message: result.message || 'Error en la petición.' };
            }
            return { success: true, status: response.status, data: result };
        } catch (error) {
            console.error('Error de red o JSON:', error);
            return { success: false, status: 0, data: null, message: error.message };
        }
    }
    ```
    
- **Agregar un usuario (Ejemplo):**
    
    ```jsx
    const API_BASE_URL = "https://url-ejemplo.api/";
    
    let currentJwtToken = localStorage.getItem('jwtToken') || null;
    
    let data = {
    	"nombres": "nombres del usuario",
    	"apellidos": "apellidos del usuario",
    	"ci": "12345678",
    	"username": "usuario_ejemplo",
    	"password": "Ej3mp10"
    };
    
    const needsAuth = false;
    
    async function addUser(data, needsAuth) {
        const url = `${API_BASE_URL}?ruta=/login`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    
        if (needsAuth && currentJwtToken) {
            headers['Authorization'] = `Bearer ${currentJwtToken}`;
        }
    
        const options = {
            method: "POST",
            headers: headers
        };
    
        if (data) {
            options.body = JSON.stringify(data);
        }
    
        try {
            const response = await fetch(url, options);
            const result = await response.json();
    
            if (!response.ok) {
                console.error('Error en la petición:', result);
                return { success: false, status: response.status, data: result, message: result.message || 'Error en la petición.' };
            }
            return { success: true, status: response.status, data: result };
        } catch (error) {
            console.error('Error de red o JSON:', error);
            return { success: false, status: 0, data: null, message: error.message };
        }
    }
    ```
    
- **Editar un usuario (Ejemplo):**
    
    ```jsx
    const API_BASE_URL = "https://url-ejemplo.api/";
    
    let currentJwtToken = localStorage.getItem('jwtToken') || null;
    
    let data = {
    	"user_id": INT, // ID del usuario
    	"nombres": "nombres del usuario editados",
    	"apellidos": "apellidos del usuario editados",
    	"ci": "87654321",
    	"username": "usuario_ejemplo_editado",
    	"password": "Ed1t4d4"
    };
    
    const needsAuth = false;
    
    async function editUser(data, needsAuth) {
        const url = `${API_BASE_URL}?ruta=/login`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    
        if (needsAuth && currentJwtToken) {
            headers['Authorization'] = `Bearer ${currentJwtToken}`;
        }
    
        const options = {
            method: "PUT",
            headers: headers
        };
    
        if (data) {
            options.body = JSON.stringify(data);
        }
    
        try {
            const response = await fetch(url, options);
            const result = await response.json();
    
            if (!response.ok) {
                console.error('Error en la petición:', result);
                return { success: false, status: response.status, data: result, message: result.message || 'Error en la petición.' };
            }
            return { success: true, status: response.status, data: result };
        } catch (error) {
            console.error('Error de red o JSON:', error);
            return { success: false, status: 0, data: null, message: error.message };
        }
    }
    ```
    
- **Actualizar estado *~Administrador~* (Ejemplo):**
    
    ```jsx
    const API_BASE_URL = "https://url-ejemplo.api/";
    
    let currentJwtToken = localStorage.getItem('jwtToken') || null;
    
    let data = {
    	"user_id": INT, // ID del usuario
    	"status": 1 // Activado = 1 | Desactivado = 0
    };
    
    const needsAuth = false;
    
    async function updateUserStatus(data, needsAuth) {
        const url = `${API_BASE_URL}?ruta=/login`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    
        if (needsAuth && currentJwtToken) {
            headers['Authorization'] = `Bearer ${currentJwtToken}`;
        }
    
        const options = {
            method: "PATCH",
            headers: headers
        };
    
        if (data) {
            options.body = JSON.stringify(data);
        }
    
        try {
            const response = await fetch(url, options);
            const result = await response.json();
    
            if (!response.ok) {
                console.error('Error en la petición:', result);
                return { success: false, status: response.status, data: result, message: result.message || 'Error en la petición.' };
            }
            return { success: true, status: response.status, data: result };
        } catch (error) {
            console.error('Error de red o JSON:', error);
            return { success: false, status: 0, data: null, message: error.message };
        }
    }
    ```
    
- **Eliminar un Usuario (Ejemplo):**
    
    ```jsx
    const API_BASE_URL = "https://url-ejemplo.api/";
    
    let currentJwtToken = localStorage.getItem('jwtToken') || null;
    
    let user_id = INT; // ID del usuario
    
    const needsAuth = false;
    
    async function deleteUser(user_id, needsAuth) {
        const url = `${API_BASE_URL}?ruta=/login&${user_id}`;
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
    
        if (needsAuth && currentJwtToken) {
            headers['Authorization'] = `Bearer ${currentJwtToken}`;
        }
    
        const options = {
            method: "PUT",
            headers: headers
        };
    
        try {
            const response = await fetch(url, options);
            const result = await response.json();
    
            if (!response.ok) {
                console.error('Error en la petición:', result);
                return { success: false, status: response.status, data: result, message: result.message || 'Error en la petición.' };
            }
            return { success: true, status: response.status, data: result };
        } catch (error) {
            console.error('Error de red o JSON:', error);
            return { success: false, status: 0, data: null, message: error.message };
        }
    }
    ```
