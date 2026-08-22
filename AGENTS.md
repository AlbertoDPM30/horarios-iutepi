# AGENTS.md

Contexto para agentes de IA que trabajen en este repositorio.
Mantener actualizado cuando cambie la arquitectura o las convenciones.

> Última actualización: 2026-08-22 · versión 2.2.0

---

## Qué es esto

Sistema de horarios académicos del IUTEPI (Punto Fijo, Venezuela). Dos piezas:

- `api/` — PHP 8 puro, MVC, **sin composer ni dependencias externas**
- `frontend/` — React 18 + Vite + Tailwind + Lucide, **JavaScript (no TypeScript)**

La base de datos es MySQL/MariaDB. El archivo `database/horarios_iutepi.sql` contiene esquema y
datos de prueba en un solo volcado importable desde phpMyAdmin.

---

## Convenciones que hay que respetar

### Idioma

**Todo el código está en español**: tablas, columnas, clases, métodos, variables, rutas, comentarios
y mensajes de la interfaz. No mezclar inglés.

```php
// bien
public static function buscarOFallar(int $id, string $recurso): array

// mal
public static function findOrFail(int $id, string $resource): array
```

Excepción: palabras clave de PHP/JS y nombres de librerías.

Los textos visibles se escriben **sin acentos en el código PHP** (los datos de la base salieron de
un volcado ASCII) pero **con acentos en el frontend** cuando son literales de la interfaz. Al
tocar un archivo, sigue lo que ya haya en él.

### PHP

- PSR-4 con prefijo `App\` → `api/app/`. El autoloader está en `App\Core\App::registrarAutoload()`.
- Tipado estricto en firmas (parámetros y retorno). `declare(strict_types=1)` **no** se usa.
- Los modelos extienden `App\Core\Modelo` y exponen métodos estáticos. **No es un ORM**: las
  consultas complejas se escriben en SQL, que es donde se leen mejor.
- Todo valor de usuario va como parámetro enlazado. Los nombres de tabla/columna nunca vienen del
  request sin pasar por lista blanca (`Modelo::orden()` es el patrón).
- Los errores de dominio se lanzan como `ApiException`; el resto termina en 500 genérico sin
  filtrar detalles internos.

### Frontend

- Componentes en PascalCase, hooks en camelCase con prefijo `use`.
- **Sin librerías de estado ni de fetching.** El cliente HTTP (`src/lib/api.js`) ya trae caché,
  deduplicación y renovación de token; los datos se cargan con `useDatos` y las escrituras con
  `useAccion` (`src/lib/hooks.js`).
- Tailwind con la paleta `marca-*` (carmesí institucional, `marca-700` = `#B20016`, el tono exacto
  del sitio). **Solo modo claro.**
- `slate` está **redefinido** en `tailwind.config.js` con los grises neutros del sitio. No es un
  descuido: es la forma de retiñir la aplicación entera desde un solo archivo.
- Tipografía: `font-titulo` = **Poppins** (títulos y cifras), `font-sans` = **Inter** (texto).
  Para números grandes usar la clase `.cifra`, que además fija `tabular-nums`.
- Marca en `frontend/public/marca/` (logotipo y favicons bajados del sitio institucional).
  Usar el componente `<Logo />`; sobre fondos oscuros, `<Logo sobreOscuro />`.
- Iconos: `lucide-react`, siempre importados por nombre.
- Todo debe verse bien desde 360 px de ancho.

---

## Arquitectura de la API

```
index.php  →  App::iniciar()  →  Router::despachar()
                                    ↓
     CORS → requiereBd → rateLimit → [auth] → [rol] → Controlador → Modelo
```

- **Las rutas viven todas en `api/routes/api.php`**, con sus middlewares al lado. Si una ruta no
  está ahí, no existe. No hay `switch` ni includes dispersos.
- Los middlewares globales envuelven **toda** la petición, incluidas las que no encuentran ruta.
  Esto es necesario para que el preflight `OPTIONS` y los 404 salgan con cabeceras CORS.
- Respuesta siempre con la misma envoltura: `{exito, datos, meta}` o `{exito, error}`.

### Servicios (donde vive la lógica de negocio)

| Servicio | Responsabilidad |
|---|---|
| `GeneradorHorarios` | El solver. Lo más complejo del proyecto; leer su cabecera antes de tocarlo |
| `AsignadorService` | Afinidad materia ↔ docente a partir de habilidades |
| `HorarioEstudianteService` | Oferta, choques y virtualización del horario del alumno |
| `ConflictoService` | Las cinco resoluciones posibles de un conflicto |
| `NotificacionService` | Campana + disparo de webhooks |
| `WebhookService` | Entrega best-effort con firma HMAC |
| `AuthService` | Tres puertas de login (código / cédula / correo+clave) |
| `AuditoriaService` | Bitácora de acciones sensibles |

---

## Invariantes del dominio (no romper)

1. **Un docente, un espacio y una sección no pueden tener dos clases en el mismo bloque.**
   Lo garantizan índices UNIQUE en `horario_bloques`, no solo el código. Si añades una escritura a
   esa tabla, mantén sincronizadas las columnas denormalizadas (`periodo_id`, `modulo`,
   `seccion_id`, `slot_seccion`, `profesor_id`, `espacio_id`).

2. **`slot_seccion` es `seccion_id` para materias normales y `NULL` para electivas.**
   Así las electivas del mismo grupo pueden dictarse en paralelo sin que el índice las rechace.

3. **Viernes y domingo están bloqueados.** Días válidos: `LUNES`–`JUEVES` (modalidad `SEMANA`) y
   `SABADO` (modalidad `SABATINO`).

4. **Ninguna sesión cruza el receso.** El generador construye "segmentos" de bloques contiguos sin
   receso y solo coloca dentro de uno.

5. **El estado del período manda.** `Periodo::permisos()` define qué se puede hacer y
   `Controlador::exigirPermisoPeriodo()` lo aplica en el servidor. Nunca confiar solo en que el
   frontend deshabilitó el botón.

6. **Con el período en curso no se puede dejar una materia sin docente** al desasignar: hay que
   indicar el reemplazo en la misma operación.

7. **Nada con historial académico se borra.** Materias, docentes, estudiantes y espacios se
   desactivan y la respuesta lo explica.

8. **El código de estudiante lo escribe el administrador, no el sistema.** Son seis dígitos
   `AARRNN`:

   | Parte | Ejemplo | Significado |
   |---|---|---|
   | `AA` | `18` | año de ingreso (2018) |
   | `RR` | `42` | referencia o lote |
   | `NN` | `06` | correlativo, 01–99 |

   Cuando `NN` pasa de 99, `RR` sube uno (`184299` → `184300`). Internamente `RRNN` es un contador
   de cuatro dígitos por año, así que el acarreo sale solo al sumar 1. `POST /estudiantes` **exige**
   el campo; `GET /estudiantes/siguiente-codigo?anio=` solo devuelve una sugerencia para precargar
   el formulario y no reserva nada.

---

## Modelo de datos (resumen)

```
carreras ──< materias ──< materia_habilidades >── habilidades >── categorias_habilidad
                 │
periodos ──< periodo_modulos                      profesores ──< profesor_disponibilidad
   │                                                   │      ──< profesor_habilidades
   ├──< secciones ──< estudiante_inscripciones          │      ──< profesor_materias
   │        │              │                            │
   └──< asignaciones ──────┴──< estudiante_horario      │
             │  └─────────────────────────────────────  ┘
             └──< horario_bloques >── bloques_horario
                        │
                   espacios ──┬── salones
                              └── laboratorios
```

Tablas de operación: `usuarios`, `administradores`, `sesiones`, `conflictos`, `notificaciones`,
`webhooks`, `webhook_entregas`, `auditoria`, `rate_limits`, `modulos_sistema`, `configuracion`.

Vistas: `v_horario_detalle` (la que consultan casi todos los endpoints de horario) y
`v_carga_profesor`.

### Detalles que suelen confundir

- **`espacios` + `salones`/`laboratorios`** es herencia por tablas. Los horarios referencian
  `espacios`; los atributos propios están en las hijas.
- **La sección del estudiante está en `estudiante_inscripciones`**, no en `estudiantes`: cambia
  cada período. La API igual devuelve `seccion` en el objeto del estudiante.
- **`asignaciones.modulo`** es 1 o 2. Una materia que se dicta los dos módulos tiene dos filas,
  igual que en los horarios impresos, que llevan una columna por módulo.

---

## Comandos

```bash
# --- API ---
php -l api/app/Services/GeneradorHorarios.php     # lint de un archivo
php api/scripts/generar-demo.php                  # regenerar horarios de demo
curl http://localhost/horarios-iutepi/api/estado  # health check

# --- Base de datos ---
mysql -u root < database/horarios_iutepi.sql      # reimportar desde cero

# --- Frontend ---
cd frontend && npm run dev      # http://localhost:5173
cd frontend && npm run build    # produce dist/
```

Entorno de desarrollo local: **XAMPP**. La API se sirve en
`http://localhost/horarios-iutepi/api` y el frontend en `http://localhost:5173`.

### Verificación mínima antes de dar algo por terminado

```bash
# 1. Todo el PHP compila
for f in $(find api -name "*.php" -not -path "*/storage/*"); do php -l "$f" >/dev/null || echo "FALLA $f"; done

# 2. El frontend compila
cd frontend && npm run build

# 3. El volcado importa limpio
mysql -u root < database/horarios_iutepi.sql

# 4. El generador sigue produciendo horarios válidos (desde el volcado limpio es determinista:
#    PR26-3 → 155 ubicadas / 3 sin docente · SA26-3 → 35 de 38 · PR27-1 → 64 sin conflictos)
php api/scripts/generar-demo.php
```

### Verificar la rejilla de horarios

Los fallos de maquetación en tablas con `rowSpan` no se ven a simple vista hasta que algo se sale de
sitio. Esta comprobación es objetiva: en `/horarios`, con **Todas las secciones** seleccionadas,
ejecutar en la consola del navegador:

```js
(() => {
  const t = document.querySelector('main table')
  const cols = t.querySelectorAll('thead th').length
  const abiertos = new Array(cols).fill(0)
  const malas = []
  ;[...t.querySelectorAll('tbody tr')].forEach((tr, i) => {
    const tds = [...tr.children]
    const heredadas = abiertos.filter((p) => p > 0).length
    const propias = tds.reduce((n, td) => n + (td.colSpan || 1), 0)
    if (heredadas + propias !== cols) malas.push(i + 1)
    let c = 0
    tds.forEach((td) => {
      while (c < cols && abiertos[c] > 0) c++
      for (let k = 0; k < (td.colSpan || 1) && c + k < cols; k++) abiertos[c + k] = td.rowSpan || 1
      c += td.colSpan || 1
    })
    for (let c2 = 0; c2 < cols; c2++) if (abiertos[c2] > 0) abiertos[c2]--
  })
  const fuera = [...document.querySelectorAll('.celda-horario')].filter((card) => {
    const td = card.closest('td'), a = card.getBoundingClientRect(), b = td.getBoundingClientRect()
    return a.right > b.right + 1.5 || a.bottom > b.bottom + 1.5
  })
  return { filasMalFormadas: malas, tarjetasFueraDeCelda: fuera.length }
})()
```

Tiene que devolver `{ filasMalFormadas: [], tarjetasFueraDeCelda: 0 }`. Con los datos de prueba y
PR26-3 salen 77 tarjetas en 13 filas × 5 columnas.

---

## Credenciales de prueba

| Rol | Credencial |
|---|---|
| Admin | `coordinacion@iutepi.edu.ve` / `Iutepi2026*` |
| Admin | `control.estudios@iutepi.edu.ve` / `Iutepi2026*` |
| Docente | cualquier `cedula` de la tabla `profesores` |
| Estudiante | cualquier `codigo` de la tabla `estudiantes` |

---

## Trampas conocidas

- **Deduplicación del cliente.** `api.get()` comparte una sola promesa entre componentes que piden
  la misma URL. Por eso **no acepta `AbortSignal`**: abortar desde un componente cancelaría la
  petición de los otros. `useDatos` descarta resultados obsoletos con un contador de generación.
  (Este bug ya ocurrió: dos componentes pedían `/dashboard` y uno mataba al otro.)

- **CORS y `OPTIONS`.** Los middlewares globales tienen que envolver también las peticiones sin
  ruta; si se mueven dentro del `match` de rutas, el preflight vuelve a fallar con 405.

- **Cabeceras del limitador.** Cuando una ruta tiene dos cubos (general + auth), se usa
  `conHeadersSiFaltan()` para que el cubo que rechaza sea el que describa el límite.

- **`Modelo::contar()`** acepta un `FROM` con JOIN. Si el argumento no es un identificador simple
  se deja tal cual, sin entrecomillar.

- **Sabatino saturado.** El sábado tiene 15 bloques lectivos y las secciones llegan a ocupar 14–15.
  Los conflictos `SIN_BLOQUE` de `SA26-3` son reales, no un fallo del solver. Al tocar el generador,
  comprobar contra los tres períodos de demo antes de dar por buena una "mejora".

- **En la rejilla, el alto de la tarjeta ES la duración de la clase.** Cada bloque mide
  `ALTO_BLOQUE` (3.5rem) y la tarjeta rellena su `rowSpan` con `td.relative` + hijo
  `absolute inset-0`: `height:100%` **no** resuelve dentro de un `<td>`, ya se intentó y la tarjeta
  se quedaba en 64px dentro de una celda de 106px. Además cada tarjeta escribe su rango
  (`7:40–9:00 · 2 bloques · 1h 20min`), que es el dato que la gente busca primero.

- **Sin filtro concreto se dibuja una rejilla por entidad, no todo apilado.** `Horarios.jsx` agrupa
  por sección / docente / laboratorio cuando no hay uno seleccionado. Amontonar 6 secciones en una
  celda era ilegible: el texto se recortaba en 76 de 77 tarjetas.

- **La rejilla de horarios agrupa los solapes en una sola celda.** En la vista "todas las
  secciones" varias clases caen en el mismo día y bloque. Si cada una intentara ocupar su propia
  celda, los `rowSpan` se pisarían y la tabla escupiría las tarjetas fuera de las columnas (pasó).
  `RejillaHorario.jsx` funde los tramos solapados en una celda que va del primer bloque al último y
  apila las clases dentro. **Al tocar ese archivo, verificar con el script de más abajo.**

- **La rejilla de horarios no lleva rojos.** El carmesí es el color de marca (botones, navegación);
  si las celdas de materia también fueran rojas, la parrilla competiría con la interfaz. La paleta
  de `RejillaHorario.jsx` excluye rojo y rosa a propósito.

- **`api/.env` no se versiona.** `.env.example` sí. `JWT_SECRET` debe tener 32 caracteres o más o
  la API responde 500.

- **`database/` está en `.gitignore`.** El volcado con esquema y datos de prueba existe en disco
  pero no se versiona. Si el repo llega sin él, no lo recrees a mano: pídelo, o crea la base y usa
  `php api/scripts/generar-demo.php`.

- **`resource/` está en `.gitignore`.** Contiene los horarios impresos del instituto que sirvieron
  de referencia. No subirlos.

---

## Estado actual

Funciona de punta a punta y verificado contra la base de prueba:

- Login por los tres roles, con renovación de token y límite de peticiones
- Dashboard con semáforo de períodos y aviso de módulos sin datos
- CRUD de períodos, secciones, materias, habilidades, docentes, estudiantes, salones y laboratorios
- Alta de docente en 5 pasos, guardando en cada paso
- Generación automática de horarios (0,12–0,49 s por período)
- Resolución de conflictos con las cuatro salidas previstas
- Horario del estudiante con detección de choques y sugerencia de virtualizar
- Campana de notificaciones, push del navegador y webhooks firmados
- Identidad visual del sitio institucional: carmesí `#B20016`, Poppins/Inter y logotipo real

### Pendiente / posibles mejoras

- No hay suite de pruebas automatizadas. La verificación es manual con `generar-demo.php` y curl.
- El generador no reserva bloques para actividades especiales (defensas, evaluaciones).
- El horario del alumno generado automáticamente solo toma materias de su propia sección; los
  cruces con otros semestres se resuelven al agregarlas a mano.
- La exportación es CSV desde el navegador; no hay PDF con el formato de la cartelera.
