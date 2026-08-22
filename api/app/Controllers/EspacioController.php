<?php

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Controlador;
use App\Core\Modelo;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validador;
use App\Models\Asignacion;
use App\Models\Espacio;
use App\Services\AuditoriaService;

/**
 * CRUD de salones y laboratorios. Ambos comparten controlador porque el
 * flujo es identico; solo cambian los campos propios de cada tipo.
 */
class EspacioController extends Controlador
{
    /* ---- SALONES --------------------------------------------------- */

    public function salones(Request $peticion): Response
    {
        return $this->listar($peticion, 'SALON');
    }

    public function verSalon(Request $peticion): Response
    {
        return $this->ver($peticion, 'SALON');
    }

    public function crearSalon(Request $peticion): Response
    {
        return $this->crear($peticion, 'SALON');
    }

    public function editarSalon(Request $peticion): Response
    {
        return $this->editar($peticion, 'SALON');
    }

    public function borrarSalon(Request $peticion): Response
    {
        return $this->borrar($peticion, 'SALON');
    }

    /* ---- LABORATORIOS ---------------------------------------------- */

    public function laboratorios(Request $peticion): Response
    {
        return $this->listar($peticion, 'LABORATORIO');
    }

    public function verLaboratorio(Request $peticion): Response
    {
        return $this->ver($peticion, 'LABORATORIO');
    }

    public function crearLaboratorio(Request $peticion): Response
    {
        return $this->crear($peticion, 'LABORATORIO');
    }

    public function editarLaboratorio(Request $peticion): Response
    {
        return $this->editar($peticion, 'LABORATORIO');
    }

    public function borrarLaboratorio(Request $peticion): Response
    {
        return $this->borrar($peticion, 'LABORATORIO');
    }

    /* ---- Implementacion comun -------------------------------------- */

    private function listar(Request $peticion, string $tipo): Response
    {
        [$pagina, $porPagina, $desfase] = Espacio::paginacion($peticion, 50);

        $filtros = $this->filtros($peticion, ['buscar', 'edificio', 'activo']);
        $orden = Espacio::orden($peticion->query('orden'), $peticion->query('dir'), 'codigo');

        return Response::paginado(
            Espacio::listar($tipo, $filtros, $porPagina, $desfase, 'e.' . $orden),
            Espacio::contarFiltrado($tipo, $filtros),
            $pagina,
            $porPagina
        );
    }

    private function ver(Request $peticion, string $tipo): Response
    {
        $id = $peticion->paramInt('id');
        $espacio = Espacio::detalle($id);

        if (!$espacio || $espacio['tipo'] !== $tipo) {
            throw ApiException::noEncontrado($tipo === 'SALON' ? 'Salon' : 'Laboratorio');
        }

        $espacio['horario'] = Asignacion::horario(array_filter([
            'espacio_id' => $id,
            'periodo_id' => $peticion->queryInt('periodo_id'),
            'modulo'     => $peticion->queryInt('modulo'),
        ], static fn ($v) => $v !== null));

        return Response::ok($espacio);
    }

    private function crear(Request $peticion, string $tipo): Response
    {
        [$comun, $especifico] = $this->validar($peticion, $tipo, true);

        if (Espacio::existe('codigo', $comun['codigo'])) {
            throw ApiException::conflicto("Ya existe un espacio con el codigo {$comun['codigo']}.");
        }

        $id = Espacio::crear($tipo, $comun, $especifico);
        AuditoriaService::registrar($peticion, 'crear', strtolower($tipo), $id, ['codigo' => $comun['codigo']]);

        return Response::creado(Espacio::detalle($id));
    }

    private function editar(Request $peticion, string $tipo): Response
    {
        $id = $peticion->paramInt('id');
        $espacio = Espacio::detalle($id);

        if (!$espacio || $espacio['tipo'] !== $tipo) {
            throw ApiException::noEncontrado($tipo === 'SALON' ? 'Salon' : 'Laboratorio');
        }

        [$comun, $especifico] = $this->validar($peticion, $tipo, false);

        if (isset($comun['codigo']) && Espacio::existe('codigo', $comun['codigo'], $id)) {
            throw ApiException::conflicto("Ya existe otro espacio con el codigo {$comun['codigo']}.");
        }

        Espacio::actualizarCompleto($id, $tipo, $comun, $especifico);
        AuditoriaService::registrar($peticion, 'editar', strtolower($tipo), $id, $comun);

        return Response::ok(Espacio::detalle($id));
    }

    private function borrar(Request $peticion, string $tipo): Response
    {
        $id = $peticion->paramInt('id');
        $espacio = Espacio::detalle($id);

        if (!$espacio || $espacio['tipo'] !== $tipo) {
            throw ApiException::noEncontrado($tipo === 'SALON' ? 'Salon' : 'Laboratorio');
        }

        $enUso = Modelo::contar(
            'horario_bloques hb JOIN periodos p ON p.periodo_id = hb.periodo_id',
            'hb.`espacio_id` = ? AND p.`estado` <> "FINALIZADO"',
            [$id]
        );

        if ($enUso > 0) {
            Espacio::actualizar($id, ['activo' => 0], 'espacios', 'espacio_id');
            AuditoriaService::registrar($peticion, 'desactivar', strtolower($tipo), $id);

            return Response::ok([
                'mensaje'     => "Este espacio tiene {$enUso} bloque(s) de clase en periodos vigentes, asi que se desactivo en lugar de eliminarse.",
                'desactivado' => true,
            ]);
        }

        Espacio::eliminar($id, 'espacios', 'espacio_id');
        AuditoriaService::registrar($peticion, 'eliminar', strtolower($tipo), $id, ['codigo' => $espacio['codigo']]);

        return Response::ok(['mensaje' => "{$espacio['codigo']} eliminado."]);
    }

    /** @return array{0: array, 1: array} [campos comunes, campos del tipo] */
    private function validar(Request $peticion, string $tipo, bool $creando): array
    {
        $obligatorio = $creando ? 'requerido' : 'opcional';

        $comun = Validador::hacer($peticion->cuerpo(), [
            'codigo'        => "{$obligatorio}|texto|min:2|max:12",
            'nombre'        => "{$obligatorio}|texto|min:3|max:100",
            'capacidad'     => 'opcional|entero|entre:5,200',
            'edificio'      => 'opcional|texto|max:40',
            'piso'          => 'opcional|entero|entre:0,10',
            'activo'        => 'opcional|booleano',
            'observaciones' => 'opcional|texto|max:255',
        ]);

        $especifico = $tipo === 'LABORATORIO'
            ? Validador::hacer($peticion->cuerpo(), [
                'puestos'           => 'opcional|entero|entre:1,100',
                'sistema_operativo' => 'opcional|texto|max:60',
                'software'          => 'opcional|texto|max:255',
                'tiene_servidor'    => 'opcional|booleano',
                'tiene_internet'    => 'opcional|booleano',
                'especialidad'      => 'opcional|en:SISTEMAS,REDES,ELECTRONICA,MIXTO',
            ])
            : Validador::hacer($peticion->cuerpo(), [
                'pupitres'               => 'opcional|entero|entre:1,120',
                'tiene_proyector'        => 'opcional|booleano',
                'tiene_aire'             => 'opcional|booleano',
                'tiene_pizarra_digital'  => 'opcional|booleano',
            ]);

        if (isset($comun['codigo'])) {
            $comun['codigo'] = strtoupper(trim($comun['codigo']));
        }

        return [
            array_filter($comun, static fn ($v) => $v !== null),
            array_filter($especifico, static fn ($v) => $v !== null),
        ];
    }
}
