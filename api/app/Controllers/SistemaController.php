<?php

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Controlador;
use App\Core\Database;
use App\Core\Env;
use App\Core\Modelo;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validador;
use App\Models\Catalogo;
use App\Models\Conflicto;
use App\Models\Notificacion;
use App\Models\Periodo;
use App\Services\AuditoriaService;
use App\Services\WebhookService;

/**
 * Dashboard, catalogos de solo lectura, notificaciones y webhooks.
 */
class SistemaController extends Controlador
{
    /** GET /estado — health check publico (lo usa el frontend para avisar caidas). */
    public function estado(Request $peticion): Response
    {
        $bdOk = Database::disponible();

        return Response::exito($bdOk ? 200 : 503, [
            'api'         => 'ok',
            'base_datos'  => $bdOk ? 'ok' : 'sin_conexion',
            'version'     => '2.0.0',
            'entorno'     => (string) Env::get('APP_ENV', 'local'),
            'hora'        => date('c'),
        ]);
    }

    /**
     * GET /dashboard
     * Todo lo que la primera pantalla necesita, en una sola peticion:
     * periodos, modulos con su bandera de "vacio" y contadores.
     */
    public function dashboard(Request $peticion): Response
    {
        Periodo::refrescarEstados();

        $rol = (string) $peticion->rol();
        $periodos = Periodo::listar([]);

        $datos = [
            'periodos' => $periodos,
            'modulos'  => Catalogo::modulos($rol),
            'resumen'  => [
                'periodos_en_curso'   => count(array_filter($periodos, static fn ($p) => $p['estado'] === 'EN_CURSO')),
                'periodos_planificacion' => count(array_filter($periodos, static fn ($p) => $p['estado'] === 'PLANIFICACION')),
                'periodos_finalizados' => count(array_filter($periodos, static fn ($p) => $p['estado'] === 'FINALIZADO')),
            ],
        ];

        if ($rol === 'ADMIN') {
            $datos['resumen'] += [
                'docentes'    => Modelo::contar('profesores', '`activo` = 1'),
                'estudiantes' => Modelo::contar('estudiantes', '`estado` = "ACTIVO"'),
                'materias'    => Modelo::contar('materias', '`activo` = 1'),
                'salones'     => Modelo::contar('espacios', '`tipo` = "SALON" AND `activo` = 1'),
                'laboratorios' => Modelo::contar('espacios', '`tipo` = "LABORATORIO" AND `activo` = 1'),
                'conflictos_pendientes' => Modelo::contar('conflictos', '`estado` = "PENDIENTE"'),
                'docentes_incompletos'  => Modelo::contar('profesores', '`activo` = 1 AND `paso_registro` < 5'),
            ];
        }

        if ($rol === 'DOCENTE') {
            $profesor = $this->perfilDocente($peticion);
            $datos['perfil'] = $profesor;
            $datos['resumen'] += [
                'materias_asignadas' => Modelo::contar(
                    'asignaciones a JOIN periodos p ON p.periodo_id = a.periodo_id',
                    'a.`profesor_id` = ? AND p.`estado` <> "FINALIZADO"',
                    [(int) $profesor['profesor_id']]
                ),
                'bloques_semana' => Modelo::contar(
                    'horario_bloques hb JOIN periodos p ON p.periodo_id = hb.periodo_id',
                    'hb.`profesor_id` = ? AND p.`estado` = "EN_CURSO"',
                    [(int) $profesor['profesor_id']]
                ),
            ];
        }

        if ($rol === 'ESTUDIANTE') {
            $estudiante = $this->perfilEstudiante($peticion);
            $datos['perfil'] = $estudiante;
            $datos['inscripciones'] = \App\Models\Estudiante::inscripciones((int) $estudiante['estudiante_id']);
        }

        return Response::ok($datos);
    }

    /** GET /catalogos — carreras, bloques, habilidades y espacios en una llamada. */
    public function catalogos(Request $peticion): Response
    {
        return Response::ok([
            'carreras'    => Catalogo::carreras(),
            'bloques'     => [
                'SEMANA'   => Catalogo::bloques('SEMANA'),
                'SABATINO' => Catalogo::bloques('SABATINO'),
            ],
            'dias'        => [
                'SEMANA'   => Catalogo::dias('SEMANA'),
                'SABATINO' => Catalogo::dias('SABATINO'),
            ],
            'habilidades' => Catalogo::categoriasConHabilidades(),
            'espacios'    => Modelo::filas(
                'SELECT `espacio_id`,`codigo`,`nombre`,`tipo`,`capacidad`
                 FROM `espacios` WHERE `activo` = 1 ORDER BY `tipo`,`codigo`'
            ),
            'configuracion' => Catalogo::configuracion(),
        ]);
    }

    /** GET /carreras */
    public function carreras(Request $peticion): Response
    {
        return Response::ok(Catalogo::carreras(!$peticion->queryBool('todas')));
    }

    /** GET /habilidades */
    public function habilidades(Request $peticion): Response
    {
        return Response::ok($peticion->queryBool('plano')
            ? Catalogo::habilidades()
            : Catalogo::categoriasConHabilidades());
    }

    /** POST /habilidades */
    public function crearHabilidad(Request $peticion): Response
    {
        $datos = Validador::hacer($peticion->cuerpo(), [
            'categoria_id' => 'requerido|entero|min:1',
            'nombre'       => 'requerido|texto|min:3|max:90',
            'descripcion'  => 'opcional|texto|max:200',
        ]);

        $id = Modelo::insertar($datos, 'habilidades');
        AuditoriaService::registrar($peticion, 'crear', 'habilidad', $id, $datos);

        return Response::creado(Modelo::buscar($id, 'habilidades', 'habilidad_id'));
    }

    /** PUT /habilidades/{id} */
    public function editarHabilidad(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');

        $datos = array_filter(Validador::hacer($peticion->cuerpo(), [
            'categoria_id' => 'opcional|entero|min:1',
            'nombre'       => 'opcional|texto|min:3|max:90',
            'descripcion'  => 'opcional|texto|max:200',
            'activo'       => 'opcional|booleano',
        ]), static fn ($v) => $v !== null);

        if ($datos) {
            Modelo::actualizar($id, $datos, 'habilidades', 'habilidad_id');
        }

        return Response::ok(Modelo::buscar($id, 'habilidades', 'habilidad_id'));
    }

    /** DELETE /habilidades/{id} */
    public function borrarHabilidad(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');

        $enUso = Modelo::contar('materia_habilidades', '`habilidad_id` = ?', [$id])
               + Modelo::contar('profesor_habilidades', '`habilidad_id` = ?', [$id]);

        if ($enUso > 0) {
            Modelo::actualizar($id, ['activo' => 0], 'habilidades', 'habilidad_id');
            return Response::ok([
                'mensaje' => "La habilidad esta usada en {$enUso} registro(s), asi que se desactivo en lugar de eliminarse.",
                'desactivada' => true,
            ]);
        }

        Modelo::eliminar($id, 'habilidades', 'habilidad_id');

        return Response::ok(['mensaje' => 'Habilidad eliminada.']);
    }

    /* =============================================================
       NOTIFICACIONES (campana)
       ============================================================= */

    /** GET /notificaciones */
    public function notificaciones(Request $peticion): Response
    {
        $usuarioId = (int) $peticion->usuarioId();
        $rol = (string) $peticion->rol();

        return Response::ok(
            Notificacion::bandeja($usuarioId, $rol, 30, $peticion->queryBool('solo_no_leidas')),
            ['no_leidas' => Notificacion::noLeidas($usuarioId, $rol)]
        );
    }

    /** PATCH /notificaciones/{id}/leer */
    public function leerNotificacion(Request $peticion): Response
    {
        $ok = Notificacion::marcarLeida(
            $peticion->paramInt('id'),
            (int) $peticion->usuarioId(),
            (string) $peticion->rol()
        );

        if (!$ok) {
            throw ApiException::noEncontrado('Notificacion');
        }

        return Response::ok(['mensaje' => 'Notificacion marcada como leida.']);
    }

    /** POST /notificaciones/leer-todas */
    public function leerTodas(Request $peticion): Response
    {
        $total = Notificacion::marcarTodas((int) $peticion->usuarioId(), (string) $peticion->rol());

        return Response::ok(['leidas' => $total]);
    }

    /* =============================================================
       WEBHOOKS
       ============================================================= */

    /** GET /webhooks */
    public function webhooks(Request $peticion): Response
    {
        return Response::ok(Modelo::filas(
            'SELECT w.*, (SELECT COUNT(*) FROM `webhook_entregas` e WHERE e.`webhook_id` = w.`webhook_id`) AS `entregas`
             FROM `webhooks` w ORDER BY w.`webhook_id`'
        ), [
            'eventos_disponibles' => [
                'conflicto.creado', 'conflicto.resuelto', 'horario.generado',
                'periodo.iniciado', 'sistema.bd_caida', 'sistema.bd_restaurada',
            ],
        ]);
    }

    /** POST /webhooks */
    public function crearWebhook(Request $peticion): Response
    {
        $datos = Validador::hacer($peticion->cuerpo(), [
            'nombre'  => 'requerido|texto|min:3|max:80',
            'url'     => 'requerido|texto|max:400',
            'eventos' => 'requerido|texto|max:300',
            'secreto' => 'opcional|texto|max:80',
            'activo'  => 'opcional|booleano',
        ]);

        if (!filter_var($datos['url'], FILTER_VALIDATE_URL) || !str_starts_with($datos['url'], 'http')) {
            throw ApiException::validacion(['url' => 'Debe ser una URL http(s) valida.']);
        }

        $id = Modelo::insertar($datos, 'webhooks');
        AuditoriaService::registrar($peticion, 'crear', 'webhook', $id, ['nombre' => $datos['nombre']]);

        return Response::creado(Modelo::buscar($id, 'webhooks', 'webhook_id'));
    }

    /** PUT /webhooks/{id} */
    public function editarWebhook(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');

        $datos = array_filter(Validador::hacer($peticion->cuerpo(), [
            'nombre'  => 'opcional|texto|min:3|max:80',
            'url'     => 'opcional|texto|max:400',
            'eventos' => 'opcional|texto|max:300',
            'secreto' => 'opcional|texto|max:80',
            'activo'  => 'opcional|booleano',
        ]), static fn ($v) => $v !== null);

        if ($datos) {
            if (isset($datos['activo'])) {
                $datos['fallos_consecutivos'] = 0;
            }
            Modelo::actualizar($id, $datos, 'webhooks', 'webhook_id');
        }

        return Response::ok(Modelo::buscar($id, 'webhooks', 'webhook_id'));
    }

    /** DELETE /webhooks/{id} */
    public function borrarWebhook(Request $peticion): Response
    {
        Modelo::eliminar($peticion->paramInt('id'), 'webhooks', 'webhook_id');

        return Response::ok(['mensaje' => 'Webhook eliminado.']);
    }

    /** POST /webhooks/{id}/probar */
    public function probarWebhook(Request $peticion): Response
    {
        $id = $peticion->paramInt('id');
        $webhook = Modelo::buscar($id, 'webhooks', 'webhook_id');

        if (!$webhook) {
            throw ApiException::noEncontrado('Webhook');
        }

        WebhookService::emitir((string) explode(',', $webhook['eventos'])[0], [
            'prueba'  => true,
            'mensaje' => 'Envio de prueba desde el panel de administracion.',
        ]);

        return Response::ok([
            'mensaje'  => 'Prueba enviada. Revisa el historial de entregas.',
            'entregas' => Modelo::filas(
                'SELECT * FROM `webhook_entregas` WHERE `webhook_id` = ? ORDER BY `entrega_id` DESC LIMIT 5',
                [$id]
            ),
        ]);
    }

    /** GET /auditoria */
    public function auditoria(Request $peticion): Response
    {
        [$pagina, $porPagina, $desfase] = Modelo::paginacion($peticion, 50);

        return Response::paginado(
            Modelo::filas(
                "SELECT a.*, u.`nombre_completo`
                 FROM `auditoria` a
                 LEFT JOIN `usuarios` u ON u.`usuario_id` = a.`usuario_id`
                 ORDER BY a.`auditoria_id` DESC
                 LIMIT {$porPagina} OFFSET {$desfase}"
            ),
            Modelo::contar('auditoria'),
            $pagina,
            $porPagina
        );
    }

    /** GET /conflictos/resumen?periodo_id=3 */
    public function resumenConflictos(Request $peticion): Response
    {
        $periodoId = $peticion->queryInt('periodo_id');
        if (!$periodoId) {
            throw ApiException::validacion(['periodo_id' => 'Indica el periodo.']);
        }

        return Response::ok(Conflicto::resumen($periodoId));
    }
}
