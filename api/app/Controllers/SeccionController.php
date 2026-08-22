<?php

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Controlador;
use App\Core\Modelo;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validador;
use App\Models\Periodo;
use App\Services\AuditoriaService;

class SeccionController extends Controlador
{
    /** GET /secciones?periodo_id=3 */
    public function index(Request $peticion): Response
    {
        $periodoId = $peticion->queryInt('periodo_id');
        if (!$periodoId) {
            throw ApiException::validacion(['periodo_id' => 'Indica de que periodo quieres las secciones.']);
        }

        return Response::ok(Periodo::secciones($periodoId, $this->filtros($peticion, ['carrera_id', 'semestre'])));
    }

    /** GET /secciones/{id} */
    public function ver(Request $peticion): Response
    {
        $seccion = Periodo::seccion($peticion->paramInt('id'));
        if (!$seccion) {
            throw ApiException::noEncontrado('Seccion');
        }

        $seccion['estudiantes'] = Modelo::filas(
            'SELECT e.`estudiante_id`, e.`codigo`, CONCAT(e.`nombres`," ",e.`apellidos`) AS `nombre_completo`,
                    i.`horario_confirmado`
             FROM `estudiante_inscripciones` i
             JOIN `estudiantes` e ON e.`estudiante_id` = i.`estudiante_id`
             WHERE i.`seccion_id` = ? AND i.`estado` = "INSCRITO"
             ORDER BY e.`apellidos`, e.`nombres`',
            [(int) $seccion['seccion_id']]
        );

        return Response::ok($seccion);
    }

    /** POST /secciones */
    public function crear(Request $peticion): Response
    {
        $datos = Validador::hacer($peticion->cuerpo(), [
            'periodo_id' => 'requerido|entero|min:1',
            'codigo'     => 'requerido|texto|min:3|max:16',
            'carrera_id' => 'requerido|entero|min:1',
            'semestre'   => 'requerido|entero|entre:1,6',
            'espacio_id' => 'opcional|entero|min:1',
            'cupo'       => 'opcional|entero|entre:5,80',
        ]);

        $periodo = $this->periodo($datos['periodo_id']);
        $this->exigirPermisoPeriodo($periodo, 'editar_estructura');

        $id = Modelo::insertar([
            'periodo_id' => $datos['periodo_id'],
            'codigo'     => strtoupper($datos['codigo']),
            'carrera_id' => $datos['carrera_id'],
            'semestre'   => $datos['semestre'],
            'espacio_id' => $datos['espacio_id'] ?? null,
            'cupo'       => $datos['cupo'] ?? 35,
        ], 'secciones');

        AuditoriaService::registrar($peticion, 'crear', 'seccion', $id, $datos);

        return Response::creado(Periodo::seccion($id));
    }

    /** PUT /secciones/{id} */
    public function editar(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $seccion = Periodo::seccion($id);
        if (!$seccion) {
            throw ApiException::noEncontrado('Seccion');
        }

        $periodo = $this->periodo((int) $seccion['periodo_id']);
        $this->exigirPermisoPeriodo($periodo, 'editar_datos');

        $datos = Validador::hacer($peticion->cuerpo(), [
            'codigo'     => 'opcional|texto|min:3|max:16',
            'espacio_id' => 'opcional|entero|min:1',
            'cupo'       => 'opcional|entero|entre:5,80',
            'activo'     => 'opcional|booleano',
        ]);

        // Con el periodo en curso no se cambia la estructura academica
        if ($periodo['estado'] !== 'PLANIFICACION') {
            unset($datos['codigo']);
        }

        $cambios = array_filter($datos, static fn ($v) => $v !== null && $v !== '');
        if (isset($cambios['codigo'])) {
            $cambios['codigo'] = strtoupper($cambios['codigo']);
        }

        if ($cambios) {
            Modelo::actualizar($id, $cambios, 'secciones', 'seccion_id');
            AuditoriaService::registrar($peticion, 'editar', 'seccion', $id, $cambios);
        }

        return Response::ok(Periodo::seccion($id));
    }

    /** DELETE /secciones/{id} */
    public function borrar(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $seccion = Periodo::seccion($id);
        if (!$seccion) {
            throw ApiException::noEncontrado('Seccion');
        }

        $periodo = $this->periodo((int) $seccion['periodo_id']);
        $this->exigirPermisoPeriodo($periodo, 'editar_estructura');

        $inscritos = Modelo::contar('estudiante_inscripciones', '`seccion_id` = ?', [$id]);
        if ($inscritos > 0) {
            throw ApiException::conflicto("La seccion tiene {$inscritos} estudiante(s) inscrito(s).");
        }

        Modelo::eliminar($id, 'secciones', 'seccion_id');
        AuditoriaService::registrar($peticion, 'eliminar', 'seccion', $id, ['codigo' => $seccion['codigo']]);

        return Response::ok(['mensaje' => "Seccion {$seccion['codigo']} eliminada."]);
    }
}
