<?php

namespace App\Core;

/**
 * Lector minimalista de archivos .env.
 *
 * No usamos vlucas/phpdotenv a proposito: la API no depende de composer
 * para que se pueda subir por FTP a un hosting compartido y funcionar.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $valores = [];
    private static bool $cargado = false;

    public static function cargar(string $ruta): void
    {
        self::$cargado = true;

        if (!is_readable($ruta)) {
            return;
        }

        $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if ($linea === '' || str_starts_with($linea, '#')) {
                continue;
            }
            $partes = explode('=', $linea, 2);
            if (count($partes) < 2) {
                continue;
            }
            $clave = trim($partes[0]);
            $valor = trim($partes[1]);

            // Quitar comentarios al final de la linea (salvo si van entre comillas)
            if ($valor !== '' && $valor[0] !== '"' && $valor[0] !== "'") {
                $valor = trim(preg_split('/\s+#/', $valor)[0]);
            }
            $valor = trim($valor, "\"'");

            self::$valores[$clave] = $valor;
        }
    }

    public static function get(string $clave, mixed $porDefecto = null): mixed
    {
        if (!self::$cargado) {
            self::cargar(dirname(__DIR__, 2) . '/.env');
        }

        $valor = self::$valores[$clave] ?? getenv($clave);
        if ($valor === false || $valor === null || $valor === '') {
            return $porDefecto;
        }

        return match (strtolower((string) $valor)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            default            => $valor,
        };
    }

    public static function int(string $clave, int $porDefecto): int
    {
        $valor = self::get($clave);
        return is_numeric($valor) ? (int) $valor : $porDefecto;
    }

    public static function bool(string $clave, bool $porDefecto): bool
    {
        $valor = self::get($clave);
        if (is_bool($valor)) {
            return $valor;
        }
        if ($valor === null) {
            return $porDefecto;
        }
        return in_array(strtolower((string) $valor), ['1', 'true', 'si', 'yes', 'on'], true);
    }
}
