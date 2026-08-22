<?php

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Controlador;
use App\Core\Modelo;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validador;
use App\Models\Asignacion;
use App\Models\Catalogo;
use App\Models\Periodo;
use App\Services\AuditoriaService;

class HorarioController extends Controlador
{
    /**
     * GET /horarios?periodo_id=3&vista=seccion|profesor|laboratorio|general
     *
     * Devuelve la rejilla ya lista para pintar: bloques del periodo,
     * dias validos y las clases ubicadas.
     */
    public function index(Request $peticion): Response
    {
        $periodoId = $peticion->queryInt('periodo_id');
        if (!$periodoId) {
            throw ApiException::validacion(['periodo_id' => 'Indica el periodo que quieres consultar.']);
        }

        $periodo = $this->periodo($periodoId);
        $vista = (string) $peticion->query('vista', 'seccion');

        $filtros = ['periodo_id' => $periodoId];
        foreach (['modulo', 'seccion_id', 'profesor_id', 'espacio_id', 'carrera_id', 'semestre'] as $clave) {
            $valor = $peticion->queryInt($clave);
            if ($valor !== null) {
                $filtros[$clave] = $valor;
            }
        }

        if ($vista === 'laboratorio') {
            $filtros['tipo_espacio'] = 'LABORATORIO';
        }

        // Un docente sin rol de admin solo ve su propio horario
        if ($peticion->esRol('DOCENTE') && $vista !== 'laboratorio') {
            $filtros['profesor_id'] = (int) $this->perfilDocente($peticion)['profesor_id'];
        }

        return Response::ok([
            'periodo'  => $periodo,
            'modulos'  => Periodo::modulos($periodoId),
            'dias'     => Catalogo::dias($periodo['modalidad']),
            'bloques'  => Catalogo::bloques($periodo['modalidad']),
            'clases'   => Asignacion::horario($filtros),
            'vista'    => $vista,
        ]);
    }

    /** GET /horarios/general?periodo_id=3 — matriz docente x bloque. */
    public function general(Request $peticion): Response
    {
        $periodoId = $peticion->queryInt('periodo_id');
        if (!$periodoId) {
            throw ApiException::validacion(['periodo_id' => 'Indica el periodo que quieres consultar.']);
        }

        $periodo = $this->periodo($periodoId);
        $modulo  = $peticion->queryInt('modulo', 1);

        $clases = Asignacion::horario(['periodo_id' => $periodoId, 'modulo' => $modulo]);

        $docentes = Modelo::filas(
            'SELECT DISTINCT p.`profesor_id`, CONCAT(p.`nombres`," ",p.`apellidos`) AS `profesor`,
                    p.`telefono`, p.`tipo_contrato`, p.`max_bloques_semana`,
                    (SELECT COUNT(*) FROM `horario_bloques` hb
                      WHERE hb.`profesor_id` = p.`profesor_id` AND hb.`periodo_id` = ? AND hb.`modulo` = ?) AS `bloques`
             FROM `profesores` p
             WHERE p.`activo` = 1
             ORDER BY p.`apellidos`, p.`nombres`',
            [$periodoId, $modulo]
        );

        return Response::ok([
            'periodo'  => $periodo,
            'modulo'   => $modulo,
            'modulos'  => Periodo::modulos($periodoId),
            'dias'     => Catalogo::dias($periodo['modalidad']),
            'bloques'  => Catalogo::bloques($periodo['modalidad']),
            'docentes' => $docentes,
            'clases'   => $clases,
        ]);
    }

    /** GET /horarios/laboratorios?periodo_id=3 */
    public function laboratorios(Request $peticion): Response
    {
        $periodoId = $peticion->queryInt('periodo_id');
        if (!$periodoId) {
            throw ApiException::validacion(['periodo_id' => 'Indica el periodo que quieres consultar.']);
        }

        $periodo = $this->periodo($periodoId);

        return Response::ok([
            'periodo'       => $periodo,
            'modulos'       => Periodo::modulos($periodoId),
            'dias'          => Catalogo::dias($periodo['modalidad']),
            'bloques'       => Catalogo::bloques($periodo['modalidad']),
            'laboratorios'  => Modelo::filas(
                'SELECT e.`espacio_id`, e.`codigo`, e.`nombre`, l.`especialidad`, l.`puestos`
                 FROM `espacios` e
                 JOIN `laboratorios` l ON l.`espacio_id` = e.`espacio_id`
                 WHERE e.`activo` = 1 ORDER BY e.`codigo`'
            ),
            'clases'        => Asignacion::horario([
                'periodo_id'   => $periodoId,
                'tipo_espacio' => 'LABORATORIO',
                'modulo'       => $peticion->queryInt('modulo'),
            ]),
        ]);
    }

    /** GET /horarios/seccion/{id} */
    public function porSeccion(Request $peticion): Response
    {
        $seccionId = $peticion->paramInt('id');
        $seccion = Periodo::seccion($seccionId);
        if (!$seccion) {
            throw ApiException::noEncontrado('Seccion');
        }

        $periodo = $this->periodo((int) $seccion['periodo_id']);

        return Response::ok([
            'seccion' => $seccion,
            'periodo' => $periodo,
            'modulos' => Periodo::modulos((int) $periodo['periodo_id']),
            'dias'    => Catalogo::dias($periodo['modalidad']),
            'bloques' => Catalogo::bloques($periodo['modalidad']),
            'clases'  => Asignacion::horario([
                'periodo_id' => (int) $periodo['periodo_id'],
                'seccion_id' => $seccionId,
                'modulo'     => $peticion->queryInt('modulo'),
            ]),
        ]);
    }

    /* =============================================================
       AJUSTES MANUALES
       ============================================================= */

    /** GET /asignaciones?periodo_id=3 */
    public function asignaciones(Request $peticion): Response
    {
        $filtros = $this->filtros($peticion, [
            'periodo_id', 'seccion_id', 'materia_id', 'profesor_id', 'modulo', 'estado', 'sin_docente',
        ]);

        if (empty($filtros['periodo_id'])) {
            throw ApiException::validacion(['periodo_id' => 'Indica el periodo.']);
        }

        return Response::ok(Asignacion::listar($filtros));
    }

    /** GET /asignaciones/{id} */
    public function verAsignacion(Request $peticion): Response
    {
        $asignacion = Asignacion::detalle($peticion->paramInt('id'));
        if (!$asignacion) {
            throw ApiException::noEncontrado('Asignacion');
        }

        return Response::ok($asignacion);
    }

    /** GET /asignaciones/{id}/candidatos */
    public function candidatos(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        Asignacion::buscarOFallar($id, 'Asignacion');

        return Response::ok(Asignacion::candidatos($id));
    }

    /**
     * PATCH /asignaciones/{id}/docente  { profesor_id }
     *
     * Con el periodo en curso se puede cambiar de docente, pero la
     * materia se queda en el mismo bloque: eso es lo que pidio la
     * coordinacion para no romper horarios ya publicados.
     */
    public function cambiarDocente(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $asignacion = Asignacion::buscarOFallar($id, 'Asignacion');
        $periodo = $this->periodo((int) $asignacion['periodo_id']);

        $this->exigirPermisoPeriodo($periodo, 'reasignar_docente');

        $datos = Validador::hacer($peticion->cuerpo(), [
            'profesor_id' => 'opcional|entero|min:1',
        ]);

        $profesorId = $datos['profesor_id'] ?? null;

        // Con el periodo en curso no se permite dejar la materia huerfana
        if ($periodo['estado'] === 'EN_CURSO' && $profesorId === null) {
            throw ApiException::conflicto(
                'El periodo esta en curso: al desasignar un docente debes indicar inmediatamente el reemplazo.'
            );
        }

        if ($profesorId !== null) {
            $candidatos = array_column(Asignacion::candidatos($id), null, 'profesor_id');

            if (!isset($candidatos[$profesorId])) {
                throw ApiException::validacion([
                    'profesor_id' => 'Ese docente no esta habilitado para dictar la materia.',
                ]);
            }
            if (empty($candidatos[$profesorId]['libre'])) {
                throw ApiException::conflicto(
                    'Ese docente ya tiene clase en alguno de esos bloques: ' . $candidatos[$profesorId]['motivo'] . '.'
                );
            }
        }

        Asignacion::reasignarDocente($id, $profesorId);
        AuditoriaService::registrar($peticion, 'cambiar_docente', 'asignacion', $id, ['profesor_id' => $profesorId]);

        return Response::ok(Asignacion::detalle($id));
    }

    /** PATCH /asignaciones/{id}/modalidad  { modalidad } */
    public function cambiarModalidad(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $asignacion = Asignacion::buscarOFallar($id, 'Asignacion');
        $periodo = $this->periodo((int) $asignacion['periodo_id']);
        $this->exigirPermisoPeriodo($periodo, 'editar_datos');

        $datos = Validador::hacer($peticion->cuerpo(), [
            'modalidad' => 'requerido|en:PRESENCIAL,VIRTUAL',
        ]);

        if ($datos['modalidad'] === 'VIRTUAL') {
            Modelo::ejecutar(
                'UPDATE `asignaciones` SET `modalidad_clase` = "VIRTUAL", `espacio_id` = NULL WHERE `asignacion_id` = ?',
                [$id]
            );
            Modelo::ejecutar('UPDATE `horario_bloques` SET `espacio_id` = NULL WHERE `asignacion_id` = ?', [$id]);
        } else {
            Modelo::ejecutar(
                'UPDATE `asignaciones` SET `modalidad_clase` = "PRESENCIAL" WHERE `asignacion_id` = ?',
                [$id]
            );
        }

        AuditoriaService::registrar($peticion, 'cambiar_modalidad', 'asignacion', $id, $datos);

        return Response::ok(Asignacion::detalle($id));
    }

    /** GET /bloques?modalidad=SEMANA */
    public function bloques(Request $peticion): Response
    {
        $modalidad = $peticion->query('modalidad');

        return Response::ok([
            'bloques' => Catalogo::bloques($modalidad ? (string) $modalidad : null),
            'dias'    => [
                'SEMANA'   => Catalogo::dias('SEMANA'),
                'SABATINO' => Catalogo::dias('SABATINO'),
            ],
        ]);
    }
}
