<?php

namespace App\Services;

use App\Core\Log;
use App\Core\Modelo;
use App\Core\Request;

/** Bitacora de acciones sensibles (quien cambio que y cuando). */
final class AuditoriaService
{
    public static function registrar(
        Request $peticion,
        string $accion,
        string $entidad,
        int|string $entidadId = '',
        array $detalle = []
    ): void {
        try {
            Modelo::insertar([
                'usuario_id' => $peticion->usuarioId(),
                'rol'        => (string) ($peticion->rol() ?? ''),
                'accion'     => mb_substr($accion, 0, 60),
                'entidad'    => mb_substr($entidad, 0, 60),
                'entidad_id' => (string) $entidadId,
                'detalle'    => $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null,
                'ip'         => $peticion->ip(),
            ], 'auditoria');
        } catch (\Throwable $e) {
            Log::aviso('No se pudo escribir en auditoria: ' . $e->getMessage());
        }
    }
}
