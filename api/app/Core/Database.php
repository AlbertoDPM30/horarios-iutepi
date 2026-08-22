<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Conexion PDO unica (una por request) con reintento corto.
 *
 * Si la base de datos no responde se emite el evento `sistema.bd_caida`
 * hacia los webhooks configurados, que es justo lo que pide el requisito
 * de avisar cuando se pierde la conexion.
 */
final class Database
{
    private static ?PDO $conexion = null;
    private static bool $caidaNotificada = false;

    public static function conexion(): PDO
    {
        if (self::$conexion instanceof PDO) {
            return self::$conexion;
        }

        $host    = (string) Env::get('DB_HOST', '127.0.0.1');
        $puerto  = (string) Env::get('DB_PORT', '3306');
        $nombre  = (string) Env::get('DB_NAME', 'horarios_iutepi');
        $usuario = (string) Env::get('DB_USER', 'root');
        $clave   = (string) Env::get('DB_PASS', '');
        $charset = (string) Env::get('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$puerto};dbname={$nombre};charset={$charset}";

        $intentos = 0;
        $ultimoError = null;

        while ($intentos < 2) {
            try {
                self::$conexion = new PDO($dsn, $usuario, $clave, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::ATTR_STRINGIFY_FETCHES  => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset}, sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
                ]);

                if (self::$caidaNotificada) {
                    self::$caidaNotificada = false;
                    self::avisarWebhook('sistema.bd_restaurada', ['mensaje' => 'Conexion con la base de datos restablecida']);
                }

                return self::$conexion;
            } catch (PDOException $e) {
                $ultimoError = $e;
                $intentos++;
                if ($intentos < 2) {
                    usleep(150000); // 150 ms antes del segundo intento
                }
            }
        }

        if (!self::$caidaNotificada) {
            self::$caidaNotificada = true;
            self::avisarWebhook('sistema.bd_caida', [
                'mensaje' => 'No se pudo conectar con la base de datos',
                'host'    => $host,
                'base'    => $nombre,
            ]);
        }

        Log::error('Fallo de conexion a la base de datos: ' . ($ultimoError?->getMessage() ?? 'desconocido'));

        throw new ApiException(
            'No hay conexion con la base de datos. Intenta de nuevo en unos segundos.',
            503,
            'BD_NO_DISPONIBLE'
        );
    }

    /** Ejecuta un callback dentro de una transaccion. */
    public static function transaccion(callable $callback): mixed
    {
        $pdo = self::conexion();
        $propia = !$pdo->inTransaction();

        if ($propia) {
            $pdo->beginTransaction();
        }

        try {
            $resultado = $callback($pdo);
            if ($propia) {
                $pdo->commit();
            }
            return $resultado;
        } catch (\Throwable $e) {
            if ($propia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function disponible(): bool
    {
        try {
            self::conexion()->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * El aviso de caida no puede usar la BD (esta caida), asi que se
     * dispara directo contra las URLs declaradas en el .env.
     */
    private static function avisarWebhook(string $evento, array $datos): void
    {
        $urls = (string) Env::get('WEBHOOK_SISTEMA_URL', '');
        if ($urls === '') {
            return;
        }

        $payload = json_encode([
            'evento'    => $evento,
            'ocurrido'  => date('c'),
            'datos'     => $datos,
        ], JSON_UNESCAPED_UNICODE);

        foreach (array_filter(array_map('trim', explode(',', $urls))) as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            curl_exec($ch);
            curl_close($ch);
        }
    }
}
