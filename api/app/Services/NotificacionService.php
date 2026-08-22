<?php

namespace App\Services;

use App\Core\Log;
use App\Core\Modelo;
use App\Models\Conflicto;

/**
 * Crea las notificaciones de la campana y dispara el webhook asociado.
 *
 * Todo lo que llega aqui es "avisos", nunca logica de negocio: si falla
 * el envio no debe romperse la operacion que lo origino.
 */
final class NotificacionService
{
    public static function aRol(
        string $rol,
        string $titulo,
        string $mensaje,
        string $tipo = 'SISTEMA',
        string $severidad = 'INFO',
        string $enlace = '',
        ?int $conflictoId = null
    ): void {
        self::crear(null, $rol, $titulo, $mensaje, $tipo, $severidad, $enlace, $conflictoId);
    }

    public static function aUsuario(
        int $usuarioId,
        string $titulo,
        string $mensaje,
        string $tipo = 'SISTEMA',
        string $severidad = 'INFO',
        string $enlace = ''
    ): void {
        self::crear($usuarioId, null, $titulo, $mensaje, $tipo, $severidad, $enlace, null);
    }

    private static function crear(
        ?int $usuarioId,
        ?string $rol,
        string $titulo,
        string $mensaje,
        string $tipo,
        string $severidad,
        string $enlace,
        ?int $conflictoId
    ): void {
        try {
            Modelo::insertar([
                'usuario_id'   => $usuarioId,
                'rol_destino'  => $rol,
                'tipo'         => $tipo,
                'severidad'    => $severidad,
                'titulo'       => mb_substr($titulo, 0, 160),
                'mensaje'      => mb_substr($mensaje, 0, 500),
                'enlace'       => mb_substr($enlace, 0, 200),
                'conflicto_id' => $conflictoId,
            ], 'notificaciones');
        } catch (\Throwable $e) {
            Log::aviso('No se pudo crear la notificacion: ' . $e->getMessage());
        }
    }

    /**
     * Registra el conflicto, avisa a los administradores por la campana
     * y emite el webhook `conflicto.creado`.
     */
    public static function conflicto(array $datos, bool $notificar = true): int
    {
        $conflictoId = Conflicto::registrar($datos);

        if (!$notificar) {
            return $conflictoId;
        }

        $severidad = match ($datos['severidad'] ?? 'MEDIA') {
            'CRITICA', 'ALTA' => 'ERROR',
            'MEDIA'           => 'ADVERTENCIA',
            default           => 'INFO',
        };

        self::aRol(
            'ADMIN',
            $datos['titulo'] ?? 'Conflicto de horario',
            $datos['descripcion'] ?? '',
            'CONFLICTO',
            $severidad,
            '/conflictos?conflicto=' . $conflictoId,
            $conflictoId
        );

        WebhookService::emitir('conflicto.creado', [
            'conflicto_id' => $conflictoId,
            'periodo_id'   => $datos['periodo_id'] ?? null,
            'tipo'         => $datos['tipo'] ?? null,
            'severidad'    => $datos['severidad'] ?? null,
            'titulo'       => $datos['titulo'] ?? '',
            'descripcion'  => $datos['descripcion'] ?? '',
        ]);

        return $conflictoId;
    }

    /** Aviso agrupado tras generar horarios (evita 40 campanitas seguidas). */
    public static function resumenGeneracion(int $periodoId, string $periodoCodigo, array $resumen): void
    {
        $conflictos = (int) ($resumen['conflictos'] ?? 0);

        if ($conflictos > 0) {
            self::aRol(
                'ADMIN',
                "Horarios de {$periodoCodigo} generados con {$conflictos} conflicto(s)",
                sprintf(
                    'Se ubicaron %d de %d materias. Quedaron %d conflicto(s) por resolver.',
                    $resumen['ubicadas'] ?? 0,
                    $resumen['total'] ?? 0,
                    $conflictos
                ),
                'HORARIO',
                'ADVERTENCIA',
                '/conflictos?periodo=' . $periodoId
            );
        } else {
            self::aRol(
                'ADMIN',
                "Horarios de {$periodoCodigo} generados sin conflictos",
                sprintf('Se ubicaron las %d materias del periodo.', $resumen['ubicadas'] ?? 0),
                'HORARIO',
                'EXITO',
                '/periodos/' . $periodoId
            );
        }

        WebhookService::emitir('horario.generado', array_merge(
            ['periodo_id' => $periodoId, 'periodo' => $periodoCodigo],
            $resumen
        ));
    }
}
