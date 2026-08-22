<?php

namespace App\Core;

/**
 * JWT HS256 implementado con hash_hmac.
 *
 * Son ~60 lineas y evitan arrastrar composer + vendor/ a un hosting
 * compartido. La comparacion de firmas usa hash_equals para no filtrar
 * informacion por tiempo de respuesta.
 */
final class Jwt
{
    private const ALGORITMO = 'HS256';

    public static function firmar(array $payload, int $duracionSegundos): string
    {
        $ahora = time();
        $payload = array_merge($payload, [
            'iat' => $ahora,
            'nbf' => $ahora,
            'exp' => $ahora + $duracionSegundos,
            'jti' => bin2hex(random_bytes(8)),
            'iss' => (string) Env::get('JWT_ISSUER', 'horarios-iutepi'),
        ]);

        $cabecera = self::base64UrlEncode(json_encode(['alg' => self::ALGORITMO, 'typ' => 'JWT']));
        $datos    = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_UNICODE));
        $firma    = self::base64UrlEncode(self::hmac("{$cabecera}.{$datos}"));

        return "{$cabecera}.{$datos}.{$firma}";
    }

    /**
     * Devuelve el payload si el token es valido, o null si no lo es.
     * Nunca lanza: el llamador decide que hacer con un token invalido.
     */
    public static function verificar(?string $token): ?array
    {
        if (!$token) {
            return null;
        }

        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            return null;
        }

        [$cabecera64, $datos64, $firma64] = $partes;

        $cabecera = json_decode(self::base64UrlDecode($cabecera64), true);
        if (!is_array($cabecera) || ($cabecera['alg'] ?? '') !== self::ALGORITMO) {
            return null; // rechaza alg:none y cualquier algoritmo distinto
        }

        $esperada = self::base64UrlEncode(self::hmac("{$cabecera64}.{$datos64}"));
        if (!hash_equals($esperada, $firma64)) {
            return null;
        }

        $payload = json_decode(self::base64UrlDecode($datos64), true);
        if (!is_array($payload)) {
            return null;
        }

        $ahora = time();
        if (isset($payload['exp']) && $ahora >= (int) $payload['exp']) {
            return null;
        }
        if (isset($payload['nbf']) && $ahora < (int) $payload['nbf'] - 5) {
            return null;
        }

        return $payload;
    }

    private static function hmac(string $mensaje): string
    {
        return hash_hmac('sha256', $mensaje, self::secreto(), true);
    }

    private static function secreto(): string
    {
        $secreto = (string) Env::get('JWT_SECRET', '');
        if (strlen($secreto) < 32) {
            throw new ApiException(
                'JWT_SECRET no esta configurado o es demasiado corto (minimo 32 caracteres).',
                500,
                'CONFIG_INVALIDA'
            );
        }
        return $secreto;
    }

    private static function base64UrlEncode(string $datos): string
    {
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $datos): string
    {
        return base64_decode(strtr($datos, '-_', '+/') . str_repeat('=', (4 - strlen($datos) % 4) % 4)) ?: '';
    }
}
