<?php

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Controlador;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validador;
use App\Models\Conflicto;
use App\Services\AuditoriaService;
use App\Services\ConflictoService;

class ConflictoController extends Controlador
{
    /** GET /conflictos?periodo_id=3&estado=PENDIENTE */
    public function index(Request $peticion): Response
    {
        $filtros = $this->filtros($peticion, ['periodo_id', 'estado', 'tipo', 'profesor_id']);
        $conflictos = Conflicto::listar($filtros);

        return Response::ok($conflictos, [
            'pendientes' => count(array_filter($conflictos, static fn ($c) => $c['estado'] === 'PENDIENTE')),
            'opciones'   => ConflictoService::OPCIONES,
        ]);
    }

    /** GET /conflictos/{id} */
    public function ver(Request $peticion): Response
    {
        $conflicto = Conflicto::detalle($peticion->paramInt('id'));
        if (!$conflicto) {
            throw ApiException::noEncontrado('Conflicto');
        }

        return Response::ok($conflicto);
    }

    /**
     * PATCH /conflictos/{id}/resolver
     * { resolucion, profesor_id?, dia?, bloques?, espacio_id?, nota? }
     */
    public function resolver(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');

        $datos = Validador::hacer($peticion->cuerpo(), [
            'resolucion' => 'requerido|en:' . implode(',', ConflictoService::OPCIONES),
        ]);

        $resultado = ConflictoService::resolver(
            $id,
            $datos['resolucion'],
            $peticion->cuerpo(),
            $peticion->usuarioId()
        );

        AuditoriaService::registrar($peticion, 'resolver_conflicto', 'conflicto', $id, $resultado);

        return Response::ok(array_merge($resultado, ['conflicto' => Conflicto::detalle($id)]));
    }

    /** PATCH /conflictos/{id}/ignorar */
    public function ignorar(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $conflicto = Conflicto::buscarOFallar($id, 'Conflicto');

        if ($conflicto['estado'] !== 'PENDIENTE') {
            throw ApiException::conflicto('Ese conflicto ya fue atendido.');
        }

        Conflicto::actualizar($id, [
            'estado'          => 'IGNORADO',
            'nota_resolucion' => (string) $peticion->input('nota', ''),
            'resuelto_por'    => $peticion->usuarioId(),
            'resuelto_en'     => date('Y-m-d H:i:s'),
        ]);

        AuditoriaService::registrar($peticion, 'ignorar_conflicto', 'conflicto', $id);

        return Response::ok(['mensaje' => 'Conflicto marcado como ignorado.']);
    }
}
