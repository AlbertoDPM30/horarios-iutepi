<?php

namespace App\Services;

use App\Core\Env;
use App\Core\Log;
use App\Core\Modelo;

/**
 * Entrega de eventos a sistemas externos.
 *
 * El envio es best-effort y con timeout corto: un webhook lento no puede
 * dejar colgada la peticion del usuario. Cada intento queda registrado en
 * `webhook_entregas` y el payload va firmado con HMAC-SHA256 para que el
 * receptor pueda verificar el origen.
 */
final class WebhookService
{
    public static function emitir(string $evento, array $datos): void
    {
        try {
            $webhooks = Modelo::filas(
                'SELECT * FROM `webhooks` WHERE `activo` = 1 AND FIND_IN_SET(?, `eventos`)',
                [$evento]
            );
        } catch (\Throwable $e) {
            Log::aviso('No se pudieron leer los webhooks: ' . $e->getMessage());
            return;
        }

        if (!$webhooks) {
            return;
        }

        $payload = json_encode([
            'evento'   => $evento,
            'ocurrido' => date('c'),
            'origen'   => (string) Env::get('APP_NAME', 'Horarios IUTEPI'),
            'datos'    => $datos,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($webhooks as $webhook) {
            self::enviar($webhook, $evento, $payload);
        }
    }

    private static function enviar(array $webhook, string $evento, string $payload): void
    {
        $inicio  = microtime(true);
        $timeout = Env::int('WEBHOOK_TIMEOUT', 4);

        $headers = [
            'Content-Type: application/json',
            'User-Agent: HorariosIUTEPI-Webhook/1.0',
            'X-Evento: ' . $evento,
        ];

        if (!empty($webhook['secreto'])) {
            $headers[] = 'X-Firma: sha256=' . hash_hmac('sha256', $payload, $webhook['secreto']);
        }

        $ch = curl_init($webhook['url']);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => min(3, $timeout),
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        curl_exec($ch);
        $estado = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        $duracion = (int) round((microtime(true) - $inicio) * 1000);
        $exito    = $estado >= 200 && $estado < 300;

        try {
            Modelo::insertar([
                'webhook_id'  => (int) $webhook['webhook_id'],
                'evento'      => $evento,
                'payload'     => mb_substr($payload, 0, 60000),
                'http_status' => $estado ?: null,
                'error'       => mb_substr($error, 0, 250),
                'duracion_ms' => $duracion,
            ], 'webhook_entregas');

            Modelo::ejecutar(
                'UPDATE `webhooks`
                    SET `ultimo_estado` = ?, `ultimo_envio` = NOW(),
                        `fallos_consecutivos` = IF(?, 0, `fallos_consecutivos` + 1),
                        `activo` = IF(NOT ? AND `fallos_consecutivos` + 1 >= 10, 0, `activo`)
                  WHERE `webhook_id` = ?',
                [$estado ?: null, $exito ? 1 : 0, $exito ? 1 : 0, (int) $webhook['webhook_id']]
            );
        } catch (\Throwable $e) {
            Log::aviso('No se pudo registrar la entrega del webhook: ' . $e->getMessage());
        }
    }
}
