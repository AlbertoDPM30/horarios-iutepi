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
use App\Models\Catalogo;
use App\Models\Profesor;
use App\Models\Usuario;
use App\Services\AsignadorService;
use App\Services\AuditoriaService;

/**
 * Alta de docente en 5 pasos. Cada paso guarda y devuelve el siguiente,
 * de modo que el formulario nunca pierde lo ya cargado:
 *   1 datos generales · 2 disponibilidad · 3 skills
 *   4 materias (sugeridas y confirmadas) · 5 horario
 */
class ProfesorController extends Controlador
{
    /** GET /profesores */
    public function index(Request $peticion): Response
    {
        [$pagina, $porPagina, $desfase] = Profesor::paginacion($peticion, 25);

        $filtros = $this->filtros($peticion, ['buscar', 'tipo_contrato', 'activo', 'materia_id', 'incompletos']);
        $orden = Profesor::orden($peticion->query('orden'), $peticion->query('dir'), 'apellidos');

        return Response::paginado(
            Profesor::listar($filtros, $porPagina, $desfase, 'p.' . $orden),
            Profesor::contarFiltrado($filtros),
            $pagina,
            $porPagina
        );
    }

    /** GET /profesores/{id} */
    public function ver(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $this->exigirPropio($peticion, 'DOCENTE', $id);

        $profesor = Profesor::detalle($id);
        if (!$profesor) {
            throw ApiException::noEncontrado('Docente');
        }

        return Response::ok($profesor);
    }

    /** POST /profesores  (paso 1) */
    public function crear(Request $peticion): Response
    {
        $datos = $this->validarDatos($peticion, true);

        if (Profesor::existe('cedula', $datos['cedula'])) {
            throw ApiException::conflicto('Ya hay un docente registrado con esa cedula.');
        }

        $id = Database::transaccion(static function () use ($datos): int {
            $usuarioId = Usuario::crear(
                'DOCENTE',
                $datos['cedula'],
                $datos['nombres'] . ' ' . $datos['apellidos']
            );

            return Profesor::insertar(array_merge($datos, [
                'usuario_id'    => $usuarioId,
                'paso_registro' => 1,
            ]));
        });

        AuditoriaService::registrar($peticion, 'crear', 'profesor', $id, ['cedula' => $datos['cedula']]);

        return Response::creado([
            'profesor'        => Profesor::detalle($id),
            'siguiente_paso'  => 2,
        ]);
    }

    /** PUT /profesores/{id}  (edicion del paso 1) */
    public function editar(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $profesor = Profesor::buscarOFallar($id, 'Docente');

        $datos = $this->validarDatos($peticion, false);

        if (isset($datos['cedula']) && Profesor::existe('cedula', $datos['cedula'], $id)) {
            throw ApiException::conflicto('Ya hay otro docente con esa cedula.');
        }

        Database::transaccion(static function () use ($id, $datos, $profesor): void {
            if ($datos) {
                Profesor::actualizar($id, $datos);
            }
            if ($profesor['usuario_id'] && (isset($datos['cedula']) || isset($datos['nombres']) || isset($datos['apellidos']))) {
                Usuario::actualizar((int) $profesor['usuario_id'], array_filter([
                    'identificador'   => $datos['cedula'] ?? null,
                    'nombre_completo' => isset($datos['nombres']) || isset($datos['apellidos'])
                        ? ($datos['nombres'] ?? $profesor['nombres']) . ' ' . ($datos['apellidos'] ?? $profesor['apellidos'])
                        : null,
                ], static fn ($v) => $v !== null));
            }
        });

        AuditoriaService::registrar($peticion, 'editar', 'profesor', $id, $datos);

        return Response::ok(Profesor::detalle($id));
    }

    /** DELETE /profesores/{id} */
    public function borrar(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $profesor = Profesor::buscarOFallar($id, 'Docente');

        $activas = Modelo::contar(
            'asignaciones a JOIN periodos p ON p.periodo_id = a.periodo_id',
            'a.`profesor_id` = ? AND p.`estado` <> "FINALIZADO"',
            [$id]
        );

        if ($activas > 0) {
            Profesor::actualizar($id, ['activo' => 0]);
            AuditoriaService::registrar($peticion, 'desactivar', 'profesor', $id);

            return Response::ok([
                'mensaje' => "El docente tiene {$activas} materia(s) en periodos vigentes, asi que se desactivo en lugar de eliminarse.",
                'desactivado' => true,
            ]);
        }

        Database::transaccion(static function () use ($id, $profesor): void {
            Profesor::eliminar($id);
            if ($profesor['usuario_id']) {
                Usuario::eliminar((int) $profesor['usuario_id']);
            }
        });

        AuditoriaService::registrar($peticion, 'eliminar', 'profesor', $id, ['cedula' => $profesor['cedula']]);

        return Response::ok(['mensaje' => 'Docente eliminado.']);
    }

    /* =============================================================
       PASO 2 · DISPONIBILIDAD
       ============================================================= */

    /** GET /profesores/{id}/disponibilidad */
    public function verDisponibilidad(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $this->exigirPropio($peticion, 'DOCENTE', $id);

        return Response::ok([
            'disponibilidad' => Profesor::disponibilidad($id, $peticion->queryInt('periodo_id')),
            'dias_validos'   => ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'SABADO'],
            'bloques'        => [
                'SEMANA'    => Catalogo::bloques('SEMANA'),
                'SABATINO'  => Catalogo::bloques('SABATINO'),
            ],
        ]);
    }

    /** PUT /profesores/{id}/disponibilidad  { franjas: [{dia, hora_inicio, hora_fin}] } */
    public function guardarDisponibilidad(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        Profesor::buscarOFallar($id, 'Docente');

        $franjas = $peticion->input('franjas');
        if (!is_array($franjas)) {
            throw ApiException::validacion(['franjas' => 'Envia la lista de franjas de disponibilidad.']);
        }

        $limpias = [];
        $diasValidos = ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'SABADO'];

        foreach ($franjas as $i => $franja) {
            $f = Validador::hacer((array) $franja, [
                'dia'         => 'requerido|texto',
                'hora_inicio' => 'requerido|hora',
                'hora_fin'    => 'requerido|hora',
            ]);

            $dia = strtoupper($f['dia']);
            if (!in_array($dia, $diasValidos, true)) {
                throw ApiException::validacion([
                    "franjas.{$i}.dia" => 'Dia no valido. Viernes y domingo estan bloqueados.',
                ]);
            }
            if ($f['hora_fin'] <= $f['hora_inicio']) {
                throw ApiException::validacion([
                    "franjas.{$i}.hora_fin" => 'La hora de salida debe ser posterior a la de entrada.',
                ]);
            }

            $limpias[] = ['dia' => $dia, 'hora_inicio' => $f['hora_inicio'], 'hora_fin' => $f['hora_fin']];
        }

        $periodoId = $peticion->queryInt('periodo_id') ?? ($peticion->input('periodo_id') !== null ? (int) $peticion->input('periodo_id') : null);

        Database::transaccion(static function () use ($id, $limpias, $periodoId): void {
            Profesor::sincronizarDisponibilidad($id, $limpias, $periodoId);
            Profesor::avanzarPaso($id, 2);
        });

        AuditoriaService::registrar($peticion, 'guardar_disponibilidad', 'profesor', $id, ['franjas' => count($limpias)]);

        return Response::ok([
            'disponibilidad' => Profesor::disponibilidad($id, $periodoId),
            'siguiente_paso' => 3,
        ]);
    }

    /* =============================================================
       PASO 3 · HABILIDADES
       ============================================================= */

    /** GET /profesores/{id}/habilidades */
    public function verHabilidades(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $this->exigirPropio($peticion, 'DOCENTE', $id);

        return Response::ok([
            'habilidades' => Profesor::habilidades($id),
            'catalogo'    => Catalogo::categoriasConHabilidades(),
        ]);
    }

    /** PUT /profesores/{id}/habilidades  { habilidades: [{habilidad_id, estrellas}] } */
    public function guardarHabilidades(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        Profesor::buscarOFallar($id, 'Docente');

        $habilidades = $peticion->input('habilidades');
        if (!is_array($habilidades)) {
            throw ApiException::validacion(['habilidades' => 'Envia la lista de habilidades con sus estrellas.']);
        }

        Database::transaccion(static function () use ($id, $habilidades): void {
            Profesor::sincronizarHabilidades($id, $habilidades);
            Profesor::avanzarPaso($id, 3);
        });

        AuditoriaService::registrar($peticion, 'guardar_habilidades', 'profesor', $id, ['total' => count($habilidades)]);

        return Response::ok([
            'habilidades'     => Profesor::habilidades($id),
            'sugerencias'     => AsignadorService::sugerirMaterias($id),
            'siguiente_paso'  => 4,
        ]);
    }

    /* =============================================================
       PASO 4 · MATERIAS (automatico con revision manual)
       ============================================================= */

    /** GET /profesores/{id}/materias-sugeridas */
    public function materiasSugeridas(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        Profesor::buscarOFallar($id, 'Docente');

        return Response::ok([
            'sugerencias' => AsignadorService::sugerirMaterias($id, $peticion->queryBool('solo_aptas')),
            'confirmadas' => Profesor::materias($id),
        ]);
    }

    /** PUT /profesores/{id}/materias  { materias: [ids] } */
    public function guardarMaterias(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        Profesor::buscarOFallar($id, 'Docente');

        $materias = $peticion->input('materias');
        if (!is_array($materias)) {
            throw ApiException::validacion(['materias' => 'Envia la lista de materias a confirmar.']);
        }

        $guardadas = Database::transaccion(static function () use ($id, $materias): int {
            $n = AsignadorService::confirmarMaterias($id, $materias);
            Profesor::avanzarPaso($id, 4);
            return $n;
        });

        AuditoriaService::registrar($peticion, 'guardar_materias', 'profesor', $id, ['total' => $guardadas]);

        return Response::ok([
            'materias'       => Profesor::materias($id),
            'siguiente_paso' => 5,
        ]);
    }

    /* =============================================================
       PASO 5 · HORARIO
       ============================================================= */

    /** GET /profesores/{id}/horario?periodo_id=3&modulo=1 */
    public function horario(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $this->exigirPropio($peticion, 'DOCENTE', $id);

        $filtros = ['profesor_id' => $id];
        if ($peticion->queryInt('periodo_id')) {
            $filtros['periodo_id'] = $peticion->queryInt('periodo_id');
        }
        if ($peticion->queryInt('modulo')) {
            $filtros['modulo'] = $peticion->queryInt('modulo');
        }

        $bloques = Asignacion::horario($filtros);

        return Response::ok([
            'profesor' => Profesor::detalle($id),
            'bloques'  => $bloques,
            'carga'    => $peticion->queryInt('periodo_id')
                ? Profesor::carga($id, (int) $peticion->queryInt('periodo_id'))
                : [],
        ]);
    }

    /** POST /profesores/{id}/finalizar-registro */
    public function finalizarRegistro(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        Profesor::buscarOFallar($id, 'Docente');

        $faltantes = [];
        if (!Profesor::disponibilidad($id)) {
            $faltantes[] = 'disponibilidad';
        }
        if (!Profesor::habilidades($id)) {
            $faltantes[] = 'habilidades';
        }
        if (!Profesor::materias($id)) {
            $faltantes[] = 'materias';
        }

        if ($faltantes) {
            throw ApiException::validacion(
                array_fill_keys($faltantes, 'Falta completar este paso.'),
                'El registro del docente aun tiene pasos pendientes.'
            );
        }

        Profesor::avanzarPaso($id, 5);
        AuditoriaService::registrar($peticion, 'finalizar_registro', 'profesor', $id);

        return Response::ok(Profesor::detalle($id));
    }

    /* ---------------------------------------------------------------- */

    private function validarDatos(Request $peticion, bool $creando): array
    {
        $obligatorio = $creando ? 'requerido' : 'opcional';

        $datos = Validador::hacer($peticion->cuerpo(), [
            'cedula'             => "{$obligatorio}|texto|min:5|max:20",
            'nombres'            => "{$obligatorio}|texto|min:2|max:80",
            'apellidos'          => "{$obligatorio}|texto|min:2|max:80",
            'telefono'           => 'opcional|texto|max:20',
            'correo'             => 'opcional|correo|max:120',
            'titulo'             => 'opcional|texto|max:120',
            'tipo_contrato'      => 'opcional|en:TIEMPO_COMPLETO,MEDIO_TIEMPO,POR_HORAS',
            'max_bloques_semana' => 'opcional|entero|entre:2,40',
            'activo'             => 'opcional|booleano',
        ]);

        if (isset($datos['cedula'])) {
            $datos['cedula'] = strtoupper(trim($datos['cedula']));
        }

        return array_filter($datos, static fn ($v) => $v !== null);
    }
}
