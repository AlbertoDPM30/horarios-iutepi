<?php

/**
 * =====================================================================
 *  Mapa de rutas de la API
 * ---------------------------------------------------------------------
 *  Toda la superficie publica del backend esta declarada aqui, con sus
 *  permisos al lado. Si una ruta no aparece en este archivo, no existe.
 *
 *  Pipeline por peticion:
 *      CORS -> BD -> limitador -> [auth] -> [rol] -> controlador
 *
 *  Roles:
 *      ADMIN      todo
 *      DOCENTE    consulta sus horarios y los de laboratorio
 *      ESTUDIANTE arma y consulta su propio horario
 * =====================================================================
 *
 * @var \App\Core\Router $router
 */

use App\Controllers\AuthController;
use App\Controllers\ConflictoController;
use App\Controllers\EspacioController;
use App\Controllers\EstudianteController;
use App\Controllers\HorarioController;
use App\Controllers\MateriaController;
use App\Controllers\PeriodoController;
use App\Controllers\ProfesorController;
use App\Controllers\SeccionController;
use App\Controllers\SistemaController;
use App\Middleware\Middlewares as M;

/* ---------------------------------------------------------------------
   Middlewares aplicados a todas las rutas
   ------------------------------------------------------------------ */
$router->global(
    M::cors(),
    M::requiereBd(),
    M::rateLimit('general')
);

$auth       = M::auth();
$soloAdmin  = [$auth, M::rol('ADMIN')];
$adminODocente = [$auth, M::rol('ADMIN', 'DOCENTE')];
$cualquiera = [$auth];
$pesado     = M::rateLimit('pesado');

/* =====================================================================
   PUBLICO
   ===================================================================== */

$router->get('/estado',  [SistemaController::class, 'estado']);
$router->get('/',        [SistemaController::class, 'estado']);

$router->post('/auth/login/estudiante', [AuthController::class, 'loginEstudiante'], [M::rateLimit('auth')]);
$router->post('/auth/login/docente',    [AuthController::class, 'loginDocente'],    [M::rateLimit('auth')]);
$router->post('/auth/login/admin',      [AuthController::class, 'loginAdmin'],      [M::rateLimit('auth')]);
$router->post('/auth/refresh',          [AuthController::class, 'refrescar'],       [M::rateLimit('auth')]);

/* =====================================================================
   SESION
   ===================================================================== */

$router->get('/auth/yo',      [AuthController::class, 'yo'],     $cualquiera);
$router->post('/auth/logout', [AuthController::class, 'logout'], $cualquiera);

/* =====================================================================
   DASHBOARD Y CATALOGOS
   ===================================================================== */

$router->get('/dashboard',  [SistemaController::class, 'dashboard'], $cualquiera);
$router->get('/catalogos',  [SistemaController::class, 'catalogos'], $cualquiera);
$router->get('/carreras',   [SistemaController::class, 'carreras'],  $cualquiera);
$router->get('/bloques',    [HorarioController::class, 'bloques'],   $cualquiera);

/* =====================================================================
   PERIODOS
   ===================================================================== */

$router->get('/periodos',              [PeriodoController::class, 'index'],   $cualquiera);
$router->get('/periodos/{id}',         [PeriodoController::class, 'ver'],     $cualquiera);
$router->get('/periodos/{id}/resumen', [PeriodoController::class, 'resumen'], $cualquiera);

$router->post('/periodos',             [PeriodoController::class, 'crear'],  $soloAdmin);
$router->put('/periodos/{id}',         [PeriodoController::class, 'editar'], $soloAdmin);
$router->delete('/periodos/{id}',      [PeriodoController::class, 'borrar'], $soloAdmin);
$router->post('/periodos/{id}/estado', [PeriodoController::class, 'cambiarEstado'], $soloAdmin);

// Generar horarios es la operacion mas cara: limitador propio
$router->post(
    '/periodos/{id}/generar-horarios',
    [PeriodoController::class, 'generarHorarios'],
    array_merge($soloAdmin, [$pesado])
);

/* =====================================================================
   SECCIONES
   ===================================================================== */

$router->get('/secciones',        [SeccionController::class, 'index'], $cualquiera);
$router->get('/secciones/{id}',   [SeccionController::class, 'ver'],   $cualquiera);
$router->post('/secciones',       [SeccionController::class, 'crear'],  $soloAdmin);
$router->put('/secciones/{id}',   [SeccionController::class, 'editar'], $soloAdmin);
$router->delete('/secciones/{id}', [SeccionController::class, 'borrar'], $soloAdmin);

/* =====================================================================
   MATERIAS
   ===================================================================== */

$router->get('/materias',      [MateriaController::class, 'index'], $cualquiera);
$router->get('/materias/{id}', [MateriaController::class, 'ver'],   $cualquiera);
$router->get('/materias/{id}/docentes-sugeridos', [MateriaController::class, 'docentesSugeridos'], $soloAdmin);
$router->post('/materias',       [MateriaController::class, 'crear'],  $soloAdmin);
$router->put('/materias/{id}',   [MateriaController::class, 'editar'], $soloAdmin);
$router->delete('/materias/{id}', [MateriaController::class, 'borrar'], $soloAdmin);

/* =====================================================================
   HABILIDADES
   ===================================================================== */

$router->get('/habilidades',       [SistemaController::class, 'habilidades'],      $cualquiera);
$router->post('/habilidades',      [SistemaController::class, 'crearHabilidad'],   $soloAdmin);
$router->put('/habilidades/{id}',  [SistemaController::class, 'editarHabilidad'],  $soloAdmin);
$router->delete('/habilidades/{id}', [SistemaController::class, 'borrarHabilidad'], $soloAdmin);

/* =====================================================================
   DOCENTES  (formulario por pasos)
   ===================================================================== */

$router->get('/profesores',      [ProfesorController::class, 'index'], $soloAdmin);
$router->get('/profesores/{id}', [ProfesorController::class, 'ver'],   $adminODocente);
$router->post('/profesores',     [ProfesorController::class, 'crear'],  $soloAdmin);   // paso 1
$router->put('/profesores/{id}', [ProfesorController::class, 'editar'], $soloAdmin);
$router->delete('/profesores/{id}', [ProfesorController::class, 'borrar'], $soloAdmin);

// Paso 2 · disponibilidad
$router->get('/profesores/{id}/disponibilidad', [ProfesorController::class, 'verDisponibilidad'], $adminODocente);
$router->put('/profesores/{id}/disponibilidad', [ProfesorController::class, 'guardarDisponibilidad'], $soloAdmin);

// Paso 3 · habilidades
$router->get('/profesores/{id}/habilidades', [ProfesorController::class, 'verHabilidades'], $adminODocente);
$router->put('/profesores/{id}/habilidades', [ProfesorController::class, 'guardarHabilidades'], $soloAdmin);

// Paso 4 · materias
$router->get('/profesores/{id}/materias-sugeridas', [ProfesorController::class, 'materiasSugeridas'], $soloAdmin);
$router->put('/profesores/{id}/materias',           [ProfesorController::class, 'guardarMaterias'], $soloAdmin);

// Paso 5 · horario
$router->get('/profesores/{id}/horario', [ProfesorController::class, 'horario'], $adminODocente);
$router->post('/profesores/{id}/finalizar-registro', [ProfesorController::class, 'finalizarRegistro'], $soloAdmin);

/* =====================================================================
   ESTUDIANTES
   ===================================================================== */

$router->get('/estudiantes',      [EstudianteController::class, 'index'], $soloAdmin);
$router->get('/estudiantes/siguiente-codigo', [EstudianteController::class, 'siguienteCodigo'], $soloAdmin);
$router->get('/estudiantes/{id}', [EstudianteController::class, 'ver'],   $cualquiera);
$router->post('/estudiantes',     [EstudianteController::class, 'crear'],  $soloAdmin);
$router->put('/estudiantes/{id}', [EstudianteController::class, 'editar'], $soloAdmin);
$router->delete('/estudiantes/{id}', [EstudianteController::class, 'borrar'], $soloAdmin);
$router->post('/estudiantes/{id}/inscribir', [EstudianteController::class, 'inscribir'], $soloAdmin);

// El estudiante arma su propio horario
$router->get('/estudiantes/{id}/oferta',  [EstudianteController::class, 'oferta'],  $cualquiera);
$router->get('/estudiantes/{id}/horario', [EstudianteController::class, 'horario'], $cualquiera);
$router->post('/estudiantes/{id}/horario', [EstudianteController::class, 'agregarMateria'], $cualquiera);
$router->delete('/estudiantes/{id}/horario/{asignacionId}', [EstudianteController::class, 'quitarMateria'], $cualquiera);
$router->patch('/estudiantes/{id}/horario/{asignacionId}',  [EstudianteController::class, 'cambiarModalidad'], $cualquiera);
$router->post('/estudiantes/{id}/horario/confirmar', [EstudianteController::class, 'confirmarHorario'], $cualquiera);
$router->post('/estudiantes/{id}/horario/generar',   [EstudianteController::class, 'generarHorario'], $cualquiera);

/* =====================================================================
   SALONES Y LABORATORIOS
   ===================================================================== */

$router->get('/salones',        [EspacioController::class, 'salones'],   $cualquiera);
$router->get('/salones/{id}',   [EspacioController::class, 'verSalon'],  $cualquiera);
$router->post('/salones',       [EspacioController::class, 'crearSalon'],  $soloAdmin);
$router->put('/salones/{id}',   [EspacioController::class, 'editarSalon'], $soloAdmin);
$router->delete('/salones/{id}', [EspacioController::class, 'borrarSalon'], $soloAdmin);

$router->get('/laboratorios',      [EspacioController::class, 'laboratorios'],   $cualquiera);
$router->get('/laboratorios/{id}', [EspacioController::class, 'verLaboratorio'], $cualquiera);
$router->post('/laboratorios',     [EspacioController::class, 'crearLaboratorio'],  $soloAdmin);
$router->put('/laboratorios/{id}', [EspacioController::class, 'editarLaboratorio'], $soloAdmin);
$router->delete('/laboratorios/{id}', [EspacioController::class, 'borrarLaboratorio'], $soloAdmin);

/* =====================================================================
   HORARIOS
   ===================================================================== */

$router->get('/horarios',              [HorarioController::class, 'index'],        $cualquiera);
$router->get('/horarios/general',      [HorarioController::class, 'general'],      $soloAdmin);
$router->get('/horarios/laboratorios', [HorarioController::class, 'laboratorios'], $adminODocente);
$router->get('/horarios/seccion/{id}', [HorarioController::class, 'porSeccion'],   $cualquiera);

$router->get('/asignaciones',      [HorarioController::class, 'asignaciones'],   $adminODocente);
$router->get('/asignaciones/{id}', [HorarioController::class, 'verAsignacion'],  $adminODocente);
$router->get('/asignaciones/{id}/candidatos', [HorarioController::class, 'candidatos'], $soloAdmin);
$router->patch('/asignaciones/{id}/docente',   [HorarioController::class, 'cambiarDocente'],   $soloAdmin);
$router->patch('/asignaciones/{id}/modalidad', [HorarioController::class, 'cambiarModalidad'], $soloAdmin);

/* =====================================================================
   CONFLICTOS
   ===================================================================== */

$router->get('/conflictos',         [ConflictoController::class, 'index'], $soloAdmin);
$router->get('/conflictos/resumen', [SistemaController::class, 'resumenConflictos'], $soloAdmin);
$router->get('/conflictos/{id}',    [ConflictoController::class, 'ver'],   $soloAdmin);
$router->patch('/conflictos/{id}/resolver', [ConflictoController::class, 'resolver'], $soloAdmin);
$router->patch('/conflictos/{id}/ignorar',  [ConflictoController::class, 'ignorar'],  $soloAdmin);

/* =====================================================================
   NOTIFICACIONES (campana)
   ===================================================================== */

$router->get('/notificaciones',   [SistemaController::class, 'notificaciones'],   $cualquiera);
$router->patch('/notificaciones/{id}/leer', [SistemaController::class, 'leerNotificacion'], $cualquiera);
$router->post('/notificaciones/leer-todas', [SistemaController::class, 'leerTodas'], $cualquiera);

/* =====================================================================
   WEBHOOKS Y AUDITORIA
   ===================================================================== */

$router->get('/webhooks',            [SistemaController::class, 'webhooks'],       $soloAdmin);
$router->post('/webhooks',           [SistemaController::class, 'crearWebhook'],   $soloAdmin);
$router->put('/webhooks/{id}',       [SistemaController::class, 'editarWebhook'],  $soloAdmin);
$router->delete('/webhooks/{id}',    [SistemaController::class, 'borrarWebhook'],  $soloAdmin);
$router->post('/webhooks/{id}/probar', [SistemaController::class, 'probarWebhook'], $soloAdmin);

$router->get('/auditoria', [SistemaController::class, 'auditoria'], $soloAdmin);
