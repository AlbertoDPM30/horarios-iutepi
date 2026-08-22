<?php

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Controlador;
use App\Core\Database;
use App\Core\Modelo;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validador;
use App\Models\Conflicto;
use App\Models\Periodo;
use App\Services\AuditoriaService;
use App\Services\GeneradorHorarios;
use App\Services\NotificacionService;

class PeriodoController extends Controlador
{
    /** GET /periodos */
    public function index(Request $peticion): Response
    {
        Periodo::refrescarEstados();

        $periodos = Periodo::listar($this->filtros($peticion, ['estado', 'modalidad', 'anio', 'buscar']));

        return Response::ok($periodos, [
            'resumen' => [
                'en_curso'      => count(array_filter($periodos, static fn ($p) => $p['estado'] === 'EN_CURSO')),
                'planificacion' => count(array_filter($periodos, static fn ($p) => $p['estado'] === 'PLANIFICACION')),
                'finalizados'   => count(array_filter($periodos, static fn ($p) => $p['estado'] === 'FINALIZADO')),
            ],
        ]);
    }

    /** GET /periodos/{id} */
    public function ver(Request $peticion): Response
    {
        Periodo::refrescarEstados();

        $periodo = Periodo::detalle($peticion->paramInt('id'));
        if (!$periodo) {
            throw ApiException::noEncontrado('Periodo');
        }

        return Response::ok($periodo);
    }

    /**
     * POST /periodos
     * Crea el periodo y sus modulos. Por defecto 20 semanas en 2 modulos
     * de 10, que es como los maneja el instituto.
     */
    public function crear(Request $peticion): Response
    {
        $datos = Validador::hacer($peticion->cuerpo(), [
            'codigo'       => 'requerido|texto|min:4|max:12',
            'nombre'       => 'opcional|texto|max:100',
            'modalidad'    => 'requerido|en:SEMANA,SABATINO',
            'fecha_inicio' => 'requerido|fecha',
            'fecha_fin'    => 'opcional|fecha',
            'semanas'      => 'opcional|entero|entre:4,40',
            'modulos'      => 'opcional|entero|entre:1,4',
            'ordinal'      => 'opcional|entero|entre:1,6',
        ]);

        $codigo  = strtoupper($datos['codigo']);
        $semanas = $datos['semanas'] ?? 20;
        $nModulos = $datos['modulos'] ?? 2;

        $inicio = new \DateTimeImmutable($datos['fecha_inicio']);
        $fin = !empty($datos['fecha_fin'])
            ? new \DateTimeImmutable($datos['fecha_fin'])
            : $inicio->modify('+' . ($semanas * 7 - 1) . ' days');

        if ($fin <= $inicio) {
            throw ApiException::validacion(['fecha_fin' => 'La fecha de cierre debe ser posterior al inicio.']);
        }

        if (Periodo::porCodigo($codigo)) {
            throw ApiException::conflicto("Ya existe un periodo con el codigo {$codigo}.");
        }

        $anio = (int) $inicio->format('Y');

        $periodoId = Database::transaccion(static function () use ($datos, $codigo, $inicio, $fin, $semanas, $nModulos, $anio, $peticion): int {
            $id = Periodo::insertar([
                'codigo'       => $codigo,
                'nombre'       => $datos['nombre'] ?? self::nombrePorDefecto($codigo, $datos['modalidad'], $anio),
                'modalidad'    => $datos['modalidad'],
                'anio'         => $anio,
                'ordinal'      => $datos['ordinal'] ?? self::ordinalPorCodigo($codigo),
                'fecha_inicio' => $inicio->format('Y-m-d'),
                'fecha_fin'    => $fin->format('Y-m-d'),
                'semanas'      => $semanas,
                'estado'       => 'PLANIFICACION',
                'creado_por'   => $peticion->usuarioId(),
            ]);

            self::crearModulos($id, $inicio, $fin, $nModulos);

            return $id;
        });

        AuditoriaService::registrar($peticion, 'crear', 'periodo', $periodoId, ['codigo' => $codigo]);

        NotificacionService::aRol(
            'ADMIN',
            "Periodo {$codigo} creado",
            'Ya puedes cargar secciones y generar los horarios.',
            'PERIODO',
            'EXITO',
            '/periodos/' . $periodoId
        );

        return Response::creado(Periodo::detalle($periodoId));
    }

    /** PUT /periodos/{id} */
    public function editar(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $periodo = $this->periodo($id);
        $this->exigirPermisoPeriodo($periodo, 'editar_datos');

        $datos = Validador::hacer($peticion->cuerpo(), [
            'nombre'              => 'opcional|texto|max:100',
            'fecha_inicio'        => 'opcional|fecha',
            'fecha_fin'           => 'opcional|fecha',
            'inscripcion_abierta' => 'opcional|booleano',
        ]);

        // Con el periodo en curso solo se permite extender el cierre
        if ($periodo['estado'] === 'EN_CURSO') {
            if (isset($datos['fecha_inicio'])) {
                throw ApiException::prohibido('El periodo ya comenzo: la fecha de inicio no se puede mover.');
            }
            if (isset($datos['fecha_fin']) && $datos['fecha_fin'] < $periodo['fecha_fin']) {
                throw ApiException::prohibido('Con el periodo en curso la fecha de cierre solo se puede extender.');
            }
        }

        $cambios = array_filter($datos, static fn ($v) => $v !== null && $v !== '');
        if (!$cambios) {
            return Response::ok(Periodo::detalle($id));
        }

        $inicio = $cambios['fecha_inicio'] ?? $periodo['fecha_inicio'];
        $fin    = $cambios['fecha_fin'] ?? $periodo['fecha_fin'];
        if ($fin <= $inicio) {
            throw ApiException::validacion(['fecha_fin' => 'La fecha de cierre debe ser posterior al inicio.']);
        }

        if (isset($cambios['fecha_inicio']) || isset($cambios['fecha_fin'])) {
            $cambios['semanas'] = max(1, (int) ceil(
                (strtotime($fin) - strtotime($inicio)) / (7 * 86400)
            ));
        }

        Database::transaccion(static function () use ($id, $cambios, $inicio, $fin, $periodo): void {
            Periodo::actualizar($id, $cambios);

            if (isset($cambios['fecha_inicio']) || isset($cambios['fecha_fin'])) {
                $modulos = Periodo::modulos($id);
                Modelo::ejecutar('DELETE FROM `periodo_modulos` WHERE `periodo_id` = ?', [$id]);
                self::crearModulos(
                    $id,
                    new \DateTimeImmutable($inicio),
                    new \DateTimeImmutable($fin),
                    max(1, count($modulos))
                );
            }
        });

        AuditoriaService::registrar($peticion, 'editar', 'periodo', $id, $cambios);

        return Response::ok(Periodo::detalle($id));
    }

    /** DELETE /periodos/{id} */
    public function borrar(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $periodo = $this->periodo($id);
        $this->exigirPermisoPeriodo($periodo, 'eliminar');

        $inscritos = Modelo::contar('estudiante_inscripciones', '`periodo_id` = ?', [$id]);
        if ($inscritos > 0) {
            throw ApiException::conflicto(
                "No se puede eliminar: hay {$inscritos} estudiante(s) inscrito(s) en este periodo."
            );
        }

        Periodo::eliminar($id);
        AuditoriaService::registrar($peticion, 'eliminar', 'periodo', $id, ['codigo' => $periodo['codigo']]);

        return Response::ok(['mensaje' => "Periodo {$periodo['codigo']} eliminado."]);
    }

    /** POST /periodos/{id}/generar-horarios */
    public function generarHorarios(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $periodo = $this->periodo($id);
        $this->exigirPermisoPeriodo($periodo, 'generar_horarios');

        $opciones = Validador::hacer($peticion->cuerpo(), [
            'limpiar'            => 'opcional|booleano',
            'reasignar_docentes' => 'opcional|booleano',
            'modulo'             => 'opcional|entero|entre:1,4',
            'seccion_id'         => 'opcional|entero|min:1',
        ]);

        $generador = new GeneradorHorarios($id);
        $resultado = $generador->generar([
            'limpiar'            => (bool) ($opciones['limpiar'] ?? true),
            'reasignar_docentes' => (bool) ($opciones['reasignar_docentes'] ?? true),
            'modulo'             => $opciones['modulo'] ?? null,
            'seccion_id'         => $opciones['seccion_id'] ?? null,
        ]);

        AuditoriaService::registrar($peticion, 'generar_horarios', 'periodo', $id, $resultado['resumen']);

        return Response::ok($resultado);
    }

    /** POST /periodos/{id}/estado  { estado } */
    public function cambiarEstado(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $periodo = $this->periodo($id);

        $datos = Validador::hacer($peticion->cuerpo(), [
            'estado' => 'requerido|en:PLANIFICACION,EN_CURSO,FINALIZADO',
        ]);

        $transiciones = [
            'PLANIFICACION' => ['EN_CURSO'],
            'EN_CURSO'      => ['FINALIZADO'],
            'FINALIZADO'    => [],
        ];

        if (!in_array($datos['estado'], $transiciones[$periodo['estado']], true)) {
            throw ApiException::conflicto(
                "No se puede pasar de {$periodo['estado']} a {$datos['estado']}."
            );
        }

        if ($datos['estado'] === 'EN_CURSO') {
            $bloques = Modelo::contar('horario_bloques', '`periodo_id` = ?', [$id]);
            if ($bloques === 0) {
                throw ApiException::conflicto('Genera los horarios antes de iniciar el periodo.');
            }
        }

        Periodo::actualizar($id, [
            'estado' => $datos['estado'],
            'inscripcion_abierta' => $datos['estado'] === 'FINALIZADO' ? 0 : 1,
        ]);

        NotificacionService::aRol(
            'ADMIN',
            "Periodo {$periodo['codigo']}: " . strtolower(str_replace('_', ' ', $datos['estado'])),
            'El cambio de estado ya se aplico a todo el sistema.',
            'PERIODO',
            'INFO',
            '/periodos/' . $id
        );

        if ($datos['estado'] === 'EN_CURSO') {
            NotificacionService::aRol(
                'ESTUDIANTE',
                "Comenzo el periodo {$periodo['codigo']}",
                'Tu horario quedo cerrado. Cualquier cambio debes gestionarlo con control de estudios.',
                'PERIODO',
                'ADVERTENCIA',
                '/mi-horario'
            );
        }

        AuditoriaService::registrar($peticion, 'cambiar_estado', 'periodo', $id, $datos);

        return Response::ok(Periodo::detalle($id));
    }

    /** GET /periodos/{id}/resumen */
    public function resumen(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $periodo = $this->periodo($id);

        return Response::ok([
            'periodo'    => Periodo::detalle($id),
            'secciones'  => Periodo::secciones($id),
            'conflictos' => Conflicto::resumen($id),
            'cobertura'  => Modelo::fila(
                'SELECT COUNT(*) AS `asignaciones`,
                        SUM(`profesor_id` IS NULL) AS `sin_docente`,
                        SUM(`estado` = "SIN_HORARIO") AS `sin_horario`
                 FROM `asignaciones` WHERE `periodo_id` = ?',
                [$id]
            ),
            'ocupacion_espacios' => Modelo::filas(
                'SELECT e.`codigo`, e.`tipo`, COUNT(hb.`horario_id`) AS `bloques`
                 FROM `espacios` e
                 LEFT JOIN `horario_bloques` hb ON hb.`espacio_id` = e.`espacio_id` AND hb.`periodo_id` = ?
                 WHERE e.`activo` = 1
                 GROUP BY e.`espacio_id`
                 ORDER BY `bloques` DESC',
                [$id]
            ),
        ]);
    }

    /* ---------------------------------------------------------------- */

    private static function crearModulos(int $periodoId, \DateTimeImmutable $inicio, \DateTimeImmutable $fin, int $cantidad): void
    {
        $diasTotales = (int) $inicio->diff($fin)->days + 1;
        $diasPorModulo = (int) floor($diasTotales / $cantidad);

        for ($n = 1; $n <= $cantidad; $n++) {
            $desde = $inicio->modify('+' . (($n - 1) * $diasPorModulo) . ' days');
            $hasta = $n === $cantidad ? $fin : $desde->modify('+' . ($diasPorModulo - 1) . ' days');

            Modelo::insertar([
                'periodo_id'   => $periodoId,
                'numero'       => $n,
                'fecha_inicio' => $desde->format('Y-m-d'),
                'fecha_fin'    => $hasta->format('Y-m-d'),
                'semanas'      => max(1, (int) round(((int) $desde->diff($hasta)->days + 1) / 7)),
            ], 'periodo_modulos');
        }
    }

    private static function nombrePorDefecto(string $codigo, string $modalidad, int $anio): string
    {
        $etiqueta = $modalidad === 'SABATINO' ? 'Sabatino' : 'Entre Semana';
        return "Periodo {$etiqueta} {$anio} ({$codigo})";
    }

    private static function ordinalPorCodigo(string $codigo): int
    {
        return preg_match('/-(\d+)$/', $codigo, $m) ? max(1, min(6, (int) $m[1])) : 1;
    }
}
