<?php

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Controlador;
use App\Core\Database;
use App\Core\Modelo;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validador;
use App\Models\Asignacion;
use App\Models\Estudiante;
use App\Models\Periodo;
use App\Services\AuditoriaService;
use App\Services\HorarioEstudianteService;

class EstudianteController extends Controlador
{
    /** GET /estudiantes */
    public function index(Request $peticion): Response
    {
        [$pagina, $porPagina, $desfase] = Estudiante::paginacion($peticion, 25);

        $filtros = $this->filtros($peticion, [
            'buscar', 'carrera_id', 'semestre', 'modalidad', 'estado', 'seccion_id', 'periodo_id',
        ]);
        $orden = Estudiante::orden($peticion->query('orden'), $peticion->query('dir'), 'apellidos');

        return Response::paginado(
            Estudiante::listar($filtros, $porPagina, $desfase, 'e.' . $orden),
            Estudiante::contarFiltrado($filtros),
            $pagina,
            $porPagina
        );
    }

    /** GET /estudiantes/{id} */
    public function ver(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $this->exigirPropio($peticion, 'ESTUDIANTE', $id);

        $estudiante = Estudiante::detalle($id);
        if (!$estudiante) {
            throw ApiException::noEncontrado('Estudiante');
        }

        return Response::ok($estudiante);
    }

    /**
     * POST /estudiantes
     * Puede inscribirse directamente en un periodo enviando `seccion_id`.
     * Con el periodo en curso, esta es la unica alta permitida y el
     * horario se genera una sola vez.
     */
    public function crear(Request $peticion): Response
    {
        $datos = $this->validarDatos($peticion, true);
        $seccionId = $peticion->input('seccion_id') !== null ? (int) $peticion->input('seccion_id') : null;

        // El codigo lo asigna control de estudios a mano; el sistema solo
        // sugiere el siguiente y verifica que tenga la forma correcta.
        if (empty($datos['codigo'])) {
            throw ApiException::validacion([
                'codigo' => 'Indica el codigo del estudiante (6 digitos: anio + referencia + correlativo).',
            ]);
        }

        if (Estudiante::existe('codigo', $datos['codigo'])) {
            throw ApiException::conflicto("Ya existe un estudiante con el codigo {$datos['codigo']}.");
        }
        if (Estudiante::existe('cedula', $datos['cedula'])) {
            throw ApiException::conflicto('Ya hay un estudiante registrado con esa cedula.');
        }

        $seccion = null;
        if ($seccionId) {
            $seccion = Periodo::seccion($seccionId);
            if (!$seccion) {
                throw ApiException::noEncontrado('Seccion');
            }
            $periodo = $this->periodo((int) $seccion['periodo_id']);
            $this->exigirPermisoPeriodo($periodo, 'inscribir_estudiantes');
        }

        $id = Database::transaccion(static function () use ($datos, $seccion): int {
            $usuarioId = \App\Models\Usuario::crear(
                'ESTUDIANTE',
                $datos['codigo'],
                $datos['nombres'] . ' ' . $datos['apellidos']
            );

            $estudianteId = Estudiante::insertar(array_merge($datos, ['usuario_id' => $usuarioId]));

            if ($seccion) {
                Modelo::insertar([
                    'estudiante_id' => $estudianteId,
                    'periodo_id'    => (int) $seccion['periodo_id'],
                    'seccion_id'    => (int) $seccion['seccion_id'],
                    'semestre'      => (int) $seccion['semestre'],
                ], 'estudiante_inscripciones');
            }

            return $estudianteId;
        });

        AuditoriaService::registrar($peticion, 'crear', 'estudiante', $id, ['codigo' => $datos['codigo']]);

        return Response::creado(Estudiante::detalle($id));
    }

    /** PUT /estudiantes/{id} */
    public function editar(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $estudiante = Estudiante::buscarOFallar($id, 'Estudiante');

        $datos = $this->validarDatos($peticion, false);

        if (isset($datos['cedula']) && Estudiante::existe('cedula', $datos['cedula'], $id)) {
            throw ApiException::conflicto('Ya hay otro estudiante con esa cedula.');
        }
        if (isset($datos['codigo']) && Estudiante::existe('codigo', $datos['codigo'], $id)) {
            throw ApiException::conflicto('Ya hay otro estudiante con ese codigo.');
        }

        Database::transaccion(static function () use ($id, $datos, $estudiante): void {
            if ($datos) {
                Estudiante::actualizar($id, $datos);
            }
            if ($estudiante['usuario_id'] && (isset($datos['codigo']) || isset($datos['nombres']) || isset($datos['apellidos']))) {
                \App\Models\Usuario::actualizar((int) $estudiante['usuario_id'], array_filter([
                    'identificador'   => $datos['codigo'] ?? null,
                    'nombre_completo' => isset($datos['nombres']) || isset($datos['apellidos'])
                        ? ($datos['nombres'] ?? $estudiante['nombres']) . ' ' . ($datos['apellidos'] ?? $estudiante['apellidos'])
                        : null,
                ], static fn ($v) => $v !== null));
            }
        });

        AuditoriaService::registrar($peticion, 'editar', 'estudiante', $id, $datos);

        return Response::ok(Estudiante::detalle($id));
    }

    /** DELETE /estudiantes/{id} */
    public function borrar(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $estudiante = Estudiante::buscarOFallar($id, 'Estudiante');

        $inscripciones = Modelo::contar('estudiante_inscripciones', '`estudiante_id` = ?', [$id]);

        if ($inscripciones > 0) {
            Estudiante::actualizar($id, ['estado' => 'RETIRADO']);
            if ($estudiante['usuario_id']) {
                \App\Models\Usuario::actualizar((int) $estudiante['usuario_id'], ['activo' => 0]);
            }
            AuditoriaService::registrar($peticion, 'retirar', 'estudiante', $id);

            return Response::ok([
                'mensaje' => 'El estudiante tiene historial academico, asi que se marco como RETIRADO en lugar de borrarse.',
                'retirado' => true,
            ]);
        }

        Database::transaccion(static function () use ($id, $estudiante): void {
            Estudiante::eliminar($id);
            if ($estudiante['usuario_id']) {
                \App\Models\Usuario::eliminar((int) $estudiante['usuario_id']);
            }
        });

        AuditoriaService::registrar($peticion, 'eliminar', 'estudiante', $id, ['codigo' => $estudiante['codigo']]);

        return Response::ok(['mensaje' => 'Estudiante eliminado.']);
    }

    /**
     * GET /estudiantes/siguiente-codigo?anio=2026
     *
     * Sugerencia para precargar el campo del formulario. No reserva nada:
     * el administrador puede escribir otro codigo si le corresponde.
     */
    public function siguienteCodigo(Request $peticion): Response
    {
        $anio = $peticion->queryInt('anio') ?? (int) date('Y');

        if ($anio < 2000 || $anio > (int) date('Y') + 1) {
            throw ApiException::validacion(['anio' => 'Anio fuera de rango.']);
        }

        $codigo = Estudiante::siguienteCodigo($anio);

        return Response::ok([
            'codigo'      => $codigo,
            'anio'        => $anio,
            'referencia'  => substr($codigo, 2, 2),
            'correlativo' => substr($codigo, 4, 2),
        ]);
    }

    /* =============================================================
       INSCRIPCION
       ============================================================= */

    /** POST /estudiantes/{id}/inscribir  { seccion_id } */
    public function inscribir(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        Estudiante::buscarOFallar($id, 'Estudiante');

        $datos = Validador::hacer($peticion->cuerpo(), [
            'seccion_id' => 'requerido|entero|min:1',
        ]);

        $seccion = Periodo::seccion($datos['seccion_id']);
        if (!$seccion) {
            throw ApiException::noEncontrado('Seccion');
        }

        $periodo = $this->periodo((int) $seccion['periodo_id']);
        $this->exigirPermisoPeriodo($periodo, 'inscribir_estudiantes');

        if (Estudiante::inscripcion($id, (int) $seccion['periodo_id'])) {
            throw ApiException::conflicto('El estudiante ya esta inscrito en ese periodo.');
        }

        $inscritos = Modelo::contar('estudiante_inscripciones', '`seccion_id` = ? AND `estado` = "INSCRITO"', [(int) $seccion['seccion_id']]);
        if ($inscritos >= (int) $seccion['cupo']) {
            throw ApiException::conflicto("La seccion {$seccion['codigo']} ya llego a su cupo ({$seccion['cupo']}).");
        }

        $inscripcionId = Modelo::insertar([
            'estudiante_id' => $id,
            'periodo_id'    => (int) $seccion['periodo_id'],
            'seccion_id'    => (int) $seccion['seccion_id'],
            'semestre'      => (int) $seccion['semestre'],
        ], 'estudiante_inscripciones');

        AuditoriaService::registrar($peticion, 'inscribir', 'estudiante', $id, [
            'seccion' => $seccion['codigo'], 'periodo' => $periodo['codigo'],
        ]);

        return Response::creado([
            'inscripcion_id' => $inscripcionId,
            'estudiante'     => Estudiante::detalle($id),
        ]);
    }

    /* =============================================================
       HORARIO DEL ESTUDIANTE
       ============================================================= */

    /** GET /estudiantes/{id}/oferta?periodo_id=4 */
    public function oferta(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $this->exigirPropio($peticion, 'ESTUDIANTE', $id);

        $periodoId = $this->periodoDeConsulta($peticion, $id);

        return Response::ok(HorarioEstudianteService::oferta($id, $periodoId));
    }

    /** GET /estudiantes/{id}/horario?periodo_id=4 */
    public function horario(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $this->exigirPropio($peticion, 'ESTUDIANTE', $id);

        $periodoId = $this->periodoDeConsulta($peticion, $id);
        $inscripcion = Estudiante::inscripcion($id, $periodoId);

        if (!$inscripcion) {
            throw ApiException::noEncontrado('Inscripcion del estudiante en ese periodo');
        }

        $inscripcionId = (int) $inscripcion['inscripcion_id'];
        $periodo = $this->periodo($periodoId);

        return Response::ok([
            'inscripcion' => $inscripcion,
            'periodo'     => $periodo,
            'editable'    => $periodo['estado'] === 'PLANIFICACION' && (int) $inscripcion['horario_confirmado'] === 0,
            'materias'    => Asignacion::materiasElegidas($inscripcionId),
            'bloques'     => Asignacion::horarioEstudiante($inscripcionId),
        ]);
    }

    /** POST /estudiantes/{id}/horario  { asignacion_id, virtual? } */
    public function agregarMateria(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $this->exigirPropio($peticion, 'ESTUDIANTE', $id);

        $datos = Validador::hacer($peticion->cuerpo(), [
            'periodo_id'    => 'requerido|entero|min:1',
            'asignacion_id' => 'requerido|entero|min:1',
            'virtual'       => 'opcional|booleano',
        ]);

        $resultado = HorarioEstudianteService::agregar(
            $id,
            $datos['periodo_id'],
            $datos['asignacion_id'],
            (bool) ($datos['virtual'] ?? false)
        );

        return Response::ok($resultado);
    }

    /** DELETE /estudiantes/{id}/horario/{asignacionId}?periodo_id=4 */
    public function quitarMateria(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $this->exigirPropio($peticion, 'ESTUDIANTE', $id);

        $periodoId = $this->periodoDeConsulta($peticion, $id);
        HorarioEstudianteService::quitar($id, $periodoId, $peticion->paramInt('asignacionId'));

        return Response::ok(['mensaje' => 'Materia retirada de tu horario.']);
    }

    /** PATCH /estudiantes/{id}/horario/{asignacionId}  { modalidad } */
    public function cambiarModalidad(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $this->exigirPropio($peticion, 'ESTUDIANTE', $id);

        $datos = Validador::hacer($peticion->cuerpo(), [
            'periodo_id' => 'requerido|entero|min:1',
            'modalidad'  => 'requerido|en:PRESENCIAL,VIRTUAL',
        ]);

        HorarioEstudianteService::cambiarModalidad(
            $id,
            $datos['periodo_id'],
            $peticion->paramInt('asignacionId'),
            $datos['modalidad']
        );

        return Response::ok(['mensaje' => 'Modalidad actualizada.']);
    }

    /** POST /estudiantes/{id}/horario/confirmar  { periodo_id } */
    public function confirmarHorario(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $this->exigirPropio($peticion, 'ESTUDIANTE', $id);

        $datos = Validador::hacer($peticion->cuerpo(), ['periodo_id' => 'requerido|entero|min:1']);
        $resultado = HorarioEstudianteService::confirmar($id, $datos['periodo_id']);

        AuditoriaService::registrar($peticion, 'confirmar_horario', 'estudiante', $id, $resultado);

        return Response::ok($resultado);
    }

    /** POST /estudiantes/{id}/horario/generar  { periodo_id } */
    public function generarHorario(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $this->exigirPropio($peticion, 'ESTUDIANTE', $id);

        $datos = Validador::hacer($peticion->cuerpo(), ['periodo_id' => 'requerido|entero|min:1']);
        $resultado = HorarioEstudianteService::generarAutomatico($id, $datos['periodo_id']);

        AuditoriaService::registrar($peticion, 'generar_horario_estudiante', 'estudiante', $id, $resultado);

        return Response::ok($resultado);
    }

    /* ---------------------------------------------------------------- */

    private function periodoDeConsulta(Request $peticion, int $estudianteId): int
    {
        $periodoId = $peticion->queryInt('periodo_id');
        if ($periodoId) {
            return $periodoId;
        }

        $ultima = Modelo::valor(
            'SELECT i.`periodo_id`
             FROM `estudiante_inscripciones` i
             JOIN `periodos` p ON p.`periodo_id` = i.`periodo_id`
             WHERE i.`estudiante_id` = ? AND i.`estado` = "INSCRITO"
             ORDER BY FIELD(p.`estado`,"PLANIFICACION","EN_CURSO","FINALIZADO"), p.`fecha_inicio` DESC
             LIMIT 1',
            [$estudianteId]
        );

        if ($ultima === null) {
            throw ApiException::noEncontrado('Inscripcion activa del estudiante');
        }

        return (int) $ultima;
    }

    private function validarDatos(Request $peticion, bool $creando): array
    {
        $obligatorio = $creando ? 'requerido' : 'opcional';

        $datos = Validador::hacer($peticion->cuerpo(), [
            'codigo'              => ($creando ? 'requerido' : 'opcional') . '|texto|min:6|max:6',
            'cedula'              => "{$obligatorio}|texto|min:5|max:20",
            'nombres'             => "{$obligatorio}|texto|min:2|max:80",
            'apellidos'           => "{$obligatorio}|texto|min:2|max:80",
            'correo'              => 'opcional|correo|max:120',
            'telefono'            => 'opcional|texto|max:20',
            'fecha_nacimiento'    => 'opcional|fecha',
            'genero'              => 'opcional|en:M,F,OTRO',
            'direccion'           => 'opcional|texto|max:200',
            'carrera_id'          => "{$obligatorio}|entero|min:1",
            'semestre_actual'     => 'opcional|entero|entre:1,6',
            'modalidad'           => 'opcional|en:SEMANA,SABATINO',
            'fecha_ingreso'       => 'opcional|fecha',
            'estado'              => 'opcional|en:ACTIVO,INACTIVO,EGRESADO,RETIRADO',
            'representante'       => 'opcional|texto|max:120',
            'telefono_emergencia' => 'opcional|texto|max:20',
        ]);

        if (isset($datos['codigo'])) {
            $datos['codigo'] = trim($datos['codigo']);

            if (!Estudiante::codigoValido($datos['codigo'])) {
                throw ApiException::validacion([
                    'codigo' => 'Deben ser 6 digitos: anio (2), referencia (2) y correlativo (2). Ejemplo: 264206.',
                ]);
            }
        }
        if (isset($datos['cedula'])) {
            $datos['cedula'] = strtoupper(trim($datos['cedula']));
        }

        return array_filter($datos, static fn ($v) => $v !== null);
    }
}
