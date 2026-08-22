# Horarios IUTEPI

Sistema de planificación académica del **Instituto Universitario de Tecnología para la Informática**.

Sustituye el proceso manual con el que hoy se arman los horarios (hojas de cálculo, impresiones
en la cartelera y mucho ojo humano para detectar choques) por un generador automático que reparte
materias, docentes, aulas y laboratorios respetando la disponibilidad real de cada profesor.

---

## Qué resuelve

La coordinación arma cada período a mano. Con 7 secciones, ~40 docentes, 83 materias, 15 salones
y 4 laboratorios, cada cambio obliga a revisar todo otra vez, y los choques aparecen cuando el
horario ya está pegado en la pared.

Este sistema:

- **Asigna docentes por competencias.** Cada materia declara qué habilidades exige y con qué nivel
  mínimo; cada docente declara las suyas con estrellas del 1 al 5. El cruce produce una afinidad
  de 0 a 100 y el sistema propone (el administrador confirma).
- **Genera la parrilla completa** de un período en menos de un segundo, respetando disponibilidad,
  recesos, capacidad de aulas y materias que requieren laboratorio.
- **No inventa soluciones inválidas.** Lo que no puede resolver lo reporta como conflicto con las
  cuatro salidas que usa la coordinación: reasignar docente, regenerar, dejar sin docente o mover
  de bloque.
- **Deja que el estudiante arme su horario** y, cuando dos materias de semestres distintos chocan
  (pasa seguido con repitientes), le ofrece cursar una en modalidad virtual en vez de bloquearlo.

---

## Estructura

```
horarios-iutepi/
├── api/                    Backend PHP 8 (MVC, sin dependencias externas)
│   ├── index.php           Front controller único
│   ├── routes/api.php      Mapa completo de rutas y permisos
│   ├── app/
│   │   ├── Core/           Router, Request, Response, Modelo, JWT, validador
│   │   ├── Middleware/     CORS, límite de peticiones, autenticación, roles
│   │   ├── Models/         Consultas por dominio
│   │   ├── Controllers/    Un controlador por recurso
│   │   └── Services/       Generador de horarios, asignador, conflictos, webhooks
│   └── scripts/            Utilidades de línea de comandos
├── frontend/               React 18 + Vite + Tailwind + Lucide
│   └── src/
│       ├── context/        Autenticación, avisos y notificaciones
│       ├── components/     UI, layout, rejilla de horarios, pasos del alta de docente
│       ├── pages/          Una por módulo
│       └── lib/            Cliente HTTP, hooks y utilidades
└── database/
    └── horarios_iutepi.sql Esquema + datos de prueba, listo para phpMyAdmin
```

---

## Puesta en marcha

### Requisitos

- PHP **8.0+** con `pdo_mysql`, `mbstring`, `json` y `curl`
- MySQL **5.7+** o MariaDB **10.3+**
- Apache con `mod_rewrite` (o cualquier servidor que enrute todo a `index.php`)
- Node **18+** solo para compilar el frontend

### 1. Base de datos

Importa `database/horarios_iutepi.sql`. Crea la base, el esquema completo y los datos de prueba.

```bash
mysql -u root -p < database/horarios_iutepi.sql
```

O desde phpMyAdmin: **Importar → seleccionar archivo → Continuar**.

> `database/` está en `.gitignore`: el volcado no viaja en el repositorio (pesa cientos de kB,
> cambia cada vez que se regeneran los horarios y lleva datos personales de ejemplo). Pídelo a
> quien administre el proyecto, o regenéralo con `php api/scripts/generar-demo.php` sobre una base
> ya creada.

### 2. API

```bash
cd api
cp .env.example .env
```

Edita `.env` con tus credenciales y **genera una clave nueva** para los tokens:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

No hace falta `composer install`: la API no tiene dependencias externas (el JWT está implementado
con `hash_hmac`). Se puede subir por FTP tal cual.

Comprueba que responde:

```bash
curl http://localhost/horarios-iutepi/api/estado
```

### 3. Frontend

```bash
cd frontend
npm install
cp .env.example .env      # ajusta VITE_API_URL si tu API no está en /horarios-iutepi/api
npm run dev               # desarrollo en http://localhost:5173
npm run build             # producción: genera dist/
```

El build son archivos estáticos: se sirven desde Apache, un hosting compartido o un CDN, sin Node
del lado del servidor.

---

## Accesos de prueba

Cada rol entra por una puerta distinta, tal como pidió el instituto:

| Rol | Credencial | Ejemplo |
|---|---|---|
| **Administrador** | correo + contraseña | `coordinacion@iutepi.edu.ve` / `Iutepi2026*` |
| **Docente** | solo cédula | cualquiera de la tabla `profesores` |
| **Estudiante** | solo código | cualquiera de la tabla `estudiantes` |

Que el alumno entre únicamente con su código es intencional: si no está inscrito, no existe el
código y no entra. Como son credenciales débiles a propósito, el límite de peticiones sobre
`/auth` es estrecho (8 intentos por minuto por IP) y el usuario se bloquea 10 minutos tras 5
fallos.

### Código de estudiante

Son seis dígitos, `AARRNN`, y **los asigna control de estudios a mano** al inscribir:

```
2 6 4 2 9 0
│ │ │ │ └─┴─ correlativo (01–99)
│ │ └─┴───── referencia o lote
└─┴───────── año de ingreso (2026)
```

Cuando el correlativo pasa de 99, la referencia sube uno: `264299` → `264300`. El formulario trae un
botón **Sugerir** que calcula el siguiente disponible del año, pero el campo queda editable porque la
numeración la manda el instituto, no el sistema.

En la base de prueba hay tres cohortes, y dos de ellas cruzan el 99 a propósito para que se vea el
acarreo:

| Año de ingreso | Alumnos | Rango |
|---|---|---|
| 2024 | 22 | `243801` – `243822` |
| 2025 | 87 | `253950` – `254036` |
| 2026 | 42 | `264290` – `264331` |

Para ver códigos y cédulas reales de la base de prueba:

```sql
SELECT codigo, nombres, apellidos FROM estudiantes LIMIT 5;
SELECT cedula, nombres, apellidos FROM profesores LIMIT 5;
```

---

## Cómo funciona el generador

El problema es un *timetabling* clásico. La estrategia, en `api/app/Services/GeneradorHorarios.php`:

1. **Oferta.** Por cada sección se toman las materias de su plan (su carrera + Estudios Generales)
   y se reparten entre los módulos del período sin pasarse de la capacidad de la rejilla. Las
   materias de 4 UC o más se dictan los dos módulos; las ligeras se reparten; las electivas van al
   último módulo.
2. **Docentes.** Se elige por afinidad de habilidades, penalizando la carga ya acumulada y
   descartando a quien no tenga disponibilidad en los días de esa modalidad.
3. **Sesiones.** Cada materia se explota en N sesiones de M bloques consecutivos.
4. **Colocación voraz** ordenada por dificultad (laboratorios y docentes con poca disponibilidad
   primero), con una función de preferencia que compacta el día de la sección, respeta el salón
   base y mantiene la continuidad entre módulos.
5. **Reparación** por búsqueda local de mínimos conflictos: desaloja como mucho dos sesiones y las
   reubica.
6. **Último recurso**, en este orden: probar otro docente habilitado que sí tenga hueco; si tampoco
   entra, dejar la materia en la parrilla sin docente. Solo lo que no cabe ni así se declara
   conflicto.

### Restricciones que nunca se violan

- Un docente no puede estar en dos aulas a la vez
- Un espacio no puede tener dos clases a la vez
- Una sección no puede ver dos materias a la vez (salvo electivas del mismo grupo, que van en
  paralelo a propósito)
- El docente solo da clase dentro de la disponibilidad que declaró
- Una sesión no cruza el receso
- Una materia no se repite dos veces el mismo día
- Las materias marcadas como de laboratorio van en laboratorio

Las tres primeras no dependen solo del código: hay **índices UNIQUE compuestos** en
`horario_bloques` que hacen imposible que la base acepte un choque. MySQL permite repetir `NULL`
en un índice único, lo que deja pasar los casos legítimos (clase virtual sin aula, materia sin
docente, electivas en paralelo).

```sql
UNIQUE KEY uq_hb_profesor (periodo_id, modulo, dia, bloque_id, profesor_id)
UNIQUE KEY uq_hb_espacio  (periodo_id, modulo, dia, bloque_id, espacio_id)
UNIQUE KEY uq_hb_seccion  (periodo_id, modulo, dia, bloque_id, slot_seccion)
```

### Rendimiento medido

| Período | Secciones | Materias | Sesiones | Tiempo | Conflictos |
|---|---|---|---|---|---|
| PR26-3 (semana) | 7 | 83 | 155 | 0,49 s | 3 sin docente |
| SA26-3 (sabatino) | 3 | 38 | 35 de 38 | 0,16 s | 3 sin bloque |
| PR27-1 (semana) | 3 | 35 | 64 | 0,12 s | 0 |

Los conflictos del sabatino son reales, no un fallo del solver: el sábado tiene 15 bloques
lectivos y las tres secciones llegan a ocupar 14–15. El sistema lo reporta en vez de inventar un
horario imposible.

---

## Estados de un período

Todo el sistema gira alrededor del período. Lo que se puede hacer depende de su estado, y **la API
lo valida siempre**, no solo la interfaz:

| Estado | Color | Qué permite |
|---|---|---|
| **Planificación** | amarillo | Todo: crear secciones, generar horarios, que los alumnos armen el suyo |
| **En curso** | verde | Solo cambios que no muevan horarios ya publicados: reasignar docente (la materia se queda en su bloque), extender la fecha de cierre, inscribir alumnos nuevos |
| **Finalizado** | gris | Solo consulta |

Los períodos entre semana (`PR`) y sabatinos (`SA`) son independientes: arrancan en fechas
distintas aunque compartan pensum y docentes. Duran 20 semanas por defecto, en 2 módulos de 10.

---

## API

Base: `http://tu-servidor/horarios-iutepi/api`

Respuesta uniforme en todos los endpoints:

```jsonc
// éxito
{ "exito": true, "datos": {...}, "meta": {...} }

// error
{ "exito": false, "error": { "codigo": "VALIDACION", "mensaje": "...", "detalles": {...} } }
```

### Autenticación

| Método | Ruta | Descripción |
|---|---|---|
| `POST` | `/auth/login/estudiante` | `{ codigo }` |
| `POST` | `/auth/login/docente` | `{ cedula }` |
| `POST` | `/auth/login/admin` | `{ correo, password }` |
| `POST` | `/auth/refresh` | Renueva el token (rotando el refresh) |
| `GET` | `/auth/yo` | Perfil del usuario autenticado |
| `POST` | `/auth/logout` | Revoca la sesión |

Token JWT HS256 con 8 h de vida; refresh token de 7 días guardado con hash SHA-256 en `sesiones`.

### Principales recursos

| Recurso | Rutas | Rol |
|---|---|---|
| Períodos | `GET/POST/PUT/DELETE /periodos`, `POST /periodos/{id}/generar-horarios`, `POST /periodos/{id}/estado` | admin (lectura: todos) |
| Secciones | `GET/POST/PUT/DELETE /secciones` | admin |
| Materias | `GET/POST/PUT/DELETE /materias`, `GET /materias/{id}/docentes-sugeridos` | admin |
| Docentes | `GET/POST/PUT/DELETE /profesores` + `/disponibilidad`, `/habilidades`, `/materias-sugeridas`, `/horario` | admin (el docente ve lo suyo) |
| Estudiantes | `GET/POST/PUT/DELETE /estudiantes`, `/inscribir`, `/oferta`, `/horario` | admin (el alumno ve lo suyo) |
| Espacios | `GET/POST/PUT/DELETE /salones` y `/laboratorios` | admin |
| Horarios | `GET /horarios`, `/horarios/general`, `/horarios/laboratorios`, `/horarios/seccion/{id}` | según rol |
| Asignaciones | `GET /asignaciones`, `PATCH /asignaciones/{id}/docente`, `/modalidad` | admin |
| Conflictos | `GET /conflictos`, `PATCH /conflictos/{id}/resolver`, `/ignorar` | admin |
| Notificaciones | `GET /notificaciones`, `PATCH /{id}/leer`, `POST /leer-todas` | todos |
| Webhooks | `GET/POST/PUT/DELETE /webhooks`, `POST /{id}/probar` | admin |

El mapa completo, con los permisos de cada ruta al lado, está en
[`api/routes/api.php`](api/routes/api.php).

### Límite de peticiones

Ventana deslizante de 60 segundos por usuario (o por IP si no hay sesión), en tres cubos:

| Cubo | Tope | Aplica a |
|---|---|---|
| `general` | 120/min | Todo el tráfico |
| `auth` | 8/min | Login y refresh |
| `pesado` | 3/min | Generación de horarios |

Al pasarse devuelve `429` con `Retry-After` y `X-RateLimit-*`. El cliente además cachea los GET
30 segundos y deduplica peticiones idénticas simultáneas, así que la interfaz no dispara ráfagas
que el backend tendría que rechazar.

---

## Notificaciones y webhooks

- **Campana en el navbar**: bandeja por usuario y por rol, con contador de no leídas. Se consulta
  cada 60 s y solo mientras la pestaña está visible.
- **Notificaciones push del navegador** cuando llega algo nuevo y la pestaña no está en primer
  plano.
- **Webhooks** hacia sistemas externos, con el payload firmado con HMAC-SHA256 en la cabecera
  `X-Firma`. Eventos disponibles:

| Evento | Cuándo |
|---|---|
| `conflicto.creado` | El generador no pudo resolver algo |
| `conflicto.resuelto` | Un administrador tomó una decisión |
| `horario.generado` | Terminó una generación (con su resumen) |
| `sistema.bd_caida` | No hay conexión con la base de datos |
| `sistema.bd_restaurada` | Volvió la conexión |

Los dos últimos no pueden depender de la base (está caída), así que se disparan directo contra
`WEBHOOK_SISTEMA_URL` del `.env`. El frontend, por su parte, muestra un aviso permanente cuando la
API deja de responder y otro cuando vuelve.

---

## Utilidades

```bash
# Regenerar los horarios de todos los períodos vigentes y los de los alumnos
php api/scripts/generar-demo.php

# Solo ciertos períodos
php api/scripts/generar-demo.php 3 4

# Sin tocar los horarios de los estudiantes
php api/scripts/generar-demo.php --sin-alumnos
```

---

## Decisiones de diseño

**Salones y laboratorios en tablas separadas, pero con una base común.**
`espacios` guarda lo compartido y es la única tabla que referencian los horarios, así la integridad
referencial es real. `salones` y `laboratorios` guardan sus atributos propios (pupitres y proyector
por un lado; puestos de cómputo, software y especialidad por el otro) y exponen cada uno su CRUD.
Es herencia por tablas: dos módulos en la interfaz, una sola fuente de verdad para las reservas.

**La sección vive en la inscripción, no en el estudiante.**
Un alumno pertenece a una sección *por período* (`SA26-3` en uno, `SA27-4` en el siguiente).
Guardarla en `estudiantes` obligaría a pisar el dato cada período y se perdería el historial. La
API devuelve siempre `seccion` en el objeto del estudiante, así que de cara a la interfaz el campo
existe igual.

**Sin composer en la API.**
El JWT son ~60 líneas con `hash_hmac`. Evitar `vendor/` significa que el backend se sube por FTP a
un hosting compartido y funciona, que era un requisito explícito.

**Denormalización deliberada en `horario_bloques`.**
`periodo_id`, `modulo`, `seccion_id`, `profesor_id` y `espacio_id` se repiten desde `asignaciones`
porque son los que forman los índices UNIQUE que previenen los choques. Es el precio de que la
base garantice la consistencia en lugar de confiar en la capa de aplicación.

**Las materias no se borran, se desactivan.**
Igual que docentes, estudiantes y espacios: si tienen historial académico, el `DELETE` los marca
como inactivos y lo dice en la respuesta. Borrar un período completo sí es posible, pero solo
mientras esté en planificación y sin inscritos.

---

## Datos de referencia

La rejilla horaria y el pensum salen de los horarios reales del instituto:

**Entre semana** — lunes a jueves (viernes bloqueado), 13 bloques de 40 minutos con receso de
11:50 a 12:05.

**Sabatino** — solo sábados, 16 bloques de 35 minutos con receso de 11:25 a 12:10.

**Carreras** — Estudios Generales (semestres 1 y 2, comunes), Análisis de Sistemas,
Administración Industrial y Electrónica.

---

## Identidad visual

La interfaz usa la paleta y la tipografía del sitio institucional (https://www.iutepi.edu):

| Uso | Color |
|---|---|
| Marca, botones principales, navegación activa | `#B20016` carmesí |
| Fondo dominante | `#FFFFFF` / `#F7F7F7` |
| Texto | `#2D2D2D` cuerpo · `#1A1A1A` títulos · `#666666` secundario |
| Bordes y separadores | `#E4E4E4` / `#D4D4D4` |

- **Poppins** para títulos y cifras (la del sitio), **Inter** para texto corrido, que a tamaño
  pequeño se lee mejor. Ambas con `display=swap` y respaldo del sistema.
- Las cifras van con `tabular-nums`, así las columnas de números no bailan al actualizarse.
- El logotipo y los favicons se descargaron del sitio y viven en `frontend/public/marca/`.
  Como el logotipo trae fondo blanco, sobre el panel carmesí del login se monta dentro de una
  pastilla blanca en lugar de recortarlo.
- En Tailwind, `slate` está redefinido con los grises neutros del sitio: toda la interfaz ya usaba
  `slate-*` como neutro, así que cambiarlo en un solo sitio retiñe la aplicación entera y evita que
  los grises azulados de fábrica choquen con el carmesí.
- **La rejilla de horarios no usa rojos.** El carmesí está reservado para acciones y navegación; si
  las celdas también fueran rojas, la parrilla competiría con la interfaz en vez de leerse. Cada
  materia recibe un color estable de una paleta de 12 tonos suaves (azul, verde, ámbar, violeta…).

---

## Licencia

Uso interno del Instituto Universitario de Tecnología para la Informática.
