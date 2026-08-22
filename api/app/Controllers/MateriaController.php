<?php

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Controlador;
use App\Core\Database;
use App\Core\Modelo;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validador;
use App\Models\Materia;
use App\Services\AsignadorService;
use App\Services\AuditoriaService;

class MateriaController extends Controlador
{
    /** GET /materias */
    public function index(Request $peticion): Response
    {
        [$pagina, $porPagina, $desfase] = Materia::paginacion($peticion, 50);

        $filtros = $this->filtros($peticion, ['carrera_id', 'semestre', 'buscar', 'requiere_laboratorio', 'activo']);
        $orden = Materia::orden($peticion->query('orden'), $peticion->query('dir'), 'codigo');

        return Response::paginado(
            Materia::listar($filtros, $porPagina, $desfase, 'm.' . $orden),
            Materia::contarFiltrado($filtros),
            $pagina,
            $porPagina
        );
    }

    /** GET /materias/{id} */
    public function ver(Request $peticion): Response
    {
        $materia = Materia::detalle($peticion->paramInt('id'));
        if (!$materia) {
            throw ApiException::noEncontrado('Materia');
        }

        return Response::ok($materia);
    }

    /** POST /materias */
    public function crear(Request $peticion): Response
    {
        $datos = $this->validar($peticion, true);

        if (Materia::existe('codigo', $datos['codigo'])) {
            throw ApiException::conflicto("Ya existe una materia con el codigo {$datos['codigo']}.");
        }

        $habilidades = (array) $peticion->input('habilidades', []);

        $id = Database::transaccion(static function () use ($datos, $habilidades): int {
            $id = Materia::insertar($datos);
            if ($habilidades) {
                Materia::sincronizarHabilidades($id, $habilidades);
            }
            return $id;
        });

        AuditoriaService::registrar($peticion, 'crear', 'materia', $id, ['codigo' => $datos['codigo']]);

        return Response::creado(Materia::detalle($id));
    }

    /** PUT /materias/{id} */
    public function editar(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        Materia::buscarOFallar($id, 'Materia');

        $datos = $this->validar($peticion, false);

        if (isset($datos['codigo']) && Materia::existe('codigo', $datos['codigo'], $id)) {
            throw ApiException::conflicto("Ya existe otra materia con el codigo {$datos['codigo']}.");
        }

        $habilidades = $peticion->input('habilidades');

        Database::transaccion(static function () use ($id, $datos, $habilidades): void {
            if ($datos) {
                Materia::actualizar($id, $datos);
            }
            if (is_array($habilidades)) {
                Materia::sincronizarHabilidades($id, $habilidades);
            }
        });

        AuditoriaService::registrar($peticion, 'editar', 'materia', $id, $datos);

        return Response::ok(Materia::detalle($id));
    }

    /** DELETE /materias/{id} */
    public function borrar(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $materia = Materia::buscarOFallar($id, 'Materia');

        $enUso = Modelo::contar('asignaciones', '`materia_id` = ?', [$id]);
        if ($enUso > 0) {
            // No se borra historia academica: se desactiva
            Materia::actualizar($id, ['activo' => 0]);
            AuditoriaService::registrar($peticion, 'desactivar', 'materia', $id);

            return Response::ok([
                'mensaje' => "La materia esta usada en {$enUso} horario(s), asi que se desactivo en lugar de eliminarse.",
                'desactivada' => true,
            ]);
        }

        Materia::eliminar($id);
        AuditoriaService::registrar($peticion, 'eliminar', 'materia', $id, ['codigo' => $materia['codigo']]);

        return Response::ok(['mensaje' => "Materia {$materia['codigo']} eliminada."]);
    }

    /** GET /materias/{id}/docentes-sugeridos */
    public function docentesSugeridos(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        Materia::buscarOFallar($id, 'Materia');

        return Response::ok(AsignadorService::sugerirDocentes($id));
    }

    /* ---------------------------------------------------------------- */

    private function validar(Request $peticion, bool $creando): array
    {
        $obligatorio = $creando ? 'requerido' : 'opcional';

        $datos = Validador::hacer($peticion->cuerpo(), [
            'codigo'               => "{$obligatorio}|texto|min:3|max:12",
            'nombre'               => "{$obligatorio}|texto|min:3|max:140",
            'carrera_id'           => "{$obligatorio}|entero|min:1",
            'semestre'             => "{$obligatorio}|entero|entre:1,6",
            'unidades_credito'     => 'opcional|entero|entre:1,10',
            'horas_semanales'      => 'opcional|entero|entre:1,20',
            'sesiones_semana'      => 'opcional|entero|entre:1,4',
            'bloques_sesion'       => 'opcional|entero|entre:1,6',
            'requiere_laboratorio' => 'opcional|booleano',
            'es_electiva'          => 'opcional|booleano',
            'grupo_electiva'       => 'opcional|texto|max:20',
            'descripcion'          => 'opcional|texto|max:255',
            'activo'               => 'opcional|booleano',
        ]);

        if (isset($datos['codigo'])) {
            $datos['codigo'] = strtoupper($datos['codigo']);
        }
        if (!empty($datos['es_electiva']) && empty($datos['grupo_electiva'])) {
            throw ApiException::validacion([
                'grupo_electiva' => 'Las electivas necesitan un grupo para poder dictarse en paralelo.',
            ]);
        }

        return array_filter($datos, static fn ($v) => $v !== null);
    }
}
