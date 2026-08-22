<?php

namespace App\Core;

/** Registro a archivo con rotacion simple por tamano. */
final class Log
{
    private const MAX_BYTES = 2_097_152; // 2 MB

    public static function error(string $mensaje, array $contexto = []): void
    {
        self::escribir('ERROR', $mensaje, $contexto);
    }

    public static function aviso(string $mensaje, array $contexto = []): void
    {
        self::escribir('AVISO', $mensaje, $contexto);
    }

    public static function info(string $mensaje, array $contexto = []): void
    {
        if (!Env::bool('APP_DEBUG', false)) {
            return;
        }
        self::escribir('INFO', $mensaje, $contexto);
    }

    private static function escribir(string $nivel, string $mensaje, array $contexto): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $archivo = $dir . '/api-' . date('Y-m') . '.log';

        if (is_file($archivo) && filesize($archivo) > self::MAX_BYTES) {
            @rename($archivo, $archivo . '.' . date('YmdHis') . '.old');
        }

        $linea = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $nivel,
            $mensaje,
            $contexto ? ' ' . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
        );

        @file_put_contents($archivo, $linea, FILE_APPEND | LOCK_EX);
    }
}
