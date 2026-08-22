<?php

namespace App\Models;

use App\Core\Modelo;

class Notificacion extends Modelo
{
    protected static string $tabla = 'notificaciones';
    protected static string $llave = 'notificacion_id';

    /**
     * Bandeja del usuario: sus notificaciones directas mas las dirigidas
     * a todo su rol (que se marcan leidas por usuario mediante `leida`
     * solo cuando son personales; las de rol se filtran por fecha de
     * ultimo acceso para no arrastrar historia vieja).
     */
    public static function bandeja(int $usuarioId, string $rol, int $limite = 30, bool $soloNoLeidas = false): array
    {
        $filtroLeidas = $soloNoLeidas ? 'AND `leida` = 0' : '';

        return self::filas(
            "SELECT * FROM `notificaciones`
              WHERE (`usuario_id` = ? OR (`usuario_id` IS NULL AND `rol_destino` = ?))
              {$filtroLeidas}
              ORDER BY `leida` ASC, `creado_en` DESC
              LIMIT {$limite}",
            [$usuarioId, $rol]
        );
    }

    public static function noLeidas(int $usuarioId, string $rol): int
    {
        return (int) self::valor(
            'SELECT COUNT(*) FROM `notificaciones`
              WHERE (`usuario_id` = ? OR (`usuario_id` IS NULL AND `rol_destino` = ?)) AND `leida` = 0',
            [$usuarioId, $rol]
        );
    }

    public static function marcarLeida(int $id, int $usuarioId, string $rol): bool
    {
        return self::ejecutar(
            'UPDATE `notificaciones` SET `leida` = 1, `leida_en` = NOW()
              WHERE `notificacion_id` = ? AND (`usuario_id` = ? OR (`usuario_id` IS NULL AND `rol_destino` = ?))',
            [$id, $usuarioId, $rol]
        )->rowCount() > 0;
    }

    public static function marcarTodas(int $usuarioId, string $rol): int
    {
        return self::ejecutar(
            'UPDATE `notificaciones` SET `leida` = 1, `leida_en` = NOW()
              WHERE (`usuario_id` = ? OR (`usuario_id` IS NULL AND `rol_destino` = ?)) AND `leida` = 0',
            [$usuarioId, $rol]
        )->rowCount();
    }
}
