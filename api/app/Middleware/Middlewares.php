<?php

namespace App\Middleware;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\Env;
use App\Core\Jwt;
use App\Core\Modelo;
use App\Core\Request;
use App\Core\Response;

/**
 * Middlewares de la API. Cada uno es un callable
 * `fn(Request $req, callable $siguiente): Response`.
 */
final class Middlewares
{
    /* =============================================================
       CORS
       ============================================================= */
    public static function cors(): callable
    {
        return static function (Request $peticion, callable $siguiente): Response {
            $permitidos = array_filter(array_map('trim', explode(',', (string) Env::get('CORS_ORIGINS', '*'))));
            $origen = $peticion->header('origin') ?? '';

            $headerOrigen = '*';
            if ($permitidos && !in_array('*', $permitidos, true)) {
                $headerOrigen = in_array($origen, $permitidos, true) ? $origen : ($permitidos[0] ?? '');
            }

            $headersCors = [
                'Access-Control-Allow-Origin'  => $headerOrigen,
                'Access-Control-Allow-Headers' => 'Origin, Content-Type, Accept, Authorization, X-Requested-With',
                'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
                'Access-Control-Max-Age'       => '86400',
                'Vary'                         => 'Origin',
            ];

            if ($peticion->metodo() === 'OPTIONS') {
                return Response::sinContenido()->conHeaders($headersCors);
            }

            return $siguiente($peticion)->conHeaders($headersCors);
        };
    }

    /* =============================================================
       LIMITADOR DE PETICIONES
       -------------------------------------------------------------
       Ventana deslizante de 60 s guardada en la tabla `rate_limits`.
       Tres cubos con topes distintos:
         general -> uso normal de la app
         auth    -> login (freno a la fuerza bruta)
         pesado  -> generar horarios / asignaciones masivas
       ============================================================= */
    public static function rateLimit(string $cubo = 'general'): callable
    {
        return static function (Request $peticion, callable $siguiente) use ($cubo): Response {
            if (!Env::bool('RATE_LIMIT_ACTIVO', true)) {
                return $siguiente($peticion);
            }

            $limite = match ($cubo) {
                'auth'   => Env::int('RATE_LIMIT_AUTH', 8),
                'pesado' => Env::int('RATE_LIMIT_PESADO', 3),
                default  => Env::int('RATE_LIMIT_GENERAL', 120),
            };

            $identidad = $peticion->usuarioId() !== null
                ? 'u' . $peticion->usuarioId()
                : 'ip' . $peticion->ip();

            $clave   = substr("{$cubo}:{$identidad}", 0, 140);
            $ventana = intdiv(time(), 60) * 60;

            try {
                Modelo::ejecutar(
                    'INSERT INTO `rate_limits` (`clave`,`ventana`,`peticiones`) VALUES (?,?,1)
                     ON DUPLICATE KEY UPDATE `peticiones` = `peticiones` + 1',
                    [$clave, $ventana]
                );
                $usadas = (int) Modelo::valor(
                    'SELECT `peticiones` FROM `rate_limits` WHERE `clave` = ? AND `ventana` = ?',
                    [$clave, $ventana]
                );
            } catch (ApiException $e) {
                // Si la BD no responde no bloqueamos al usuario por el limitador:
                // el propio error de BD ya se reporta mas abajo en la pila.
                if ($e->estado() === 503) {
                    return $siguiente($peticion);
                }
                throw $e;
            }

            // Limpieza oportunista (1 de cada 50 peticiones)
            if (random_int(1, 50) === 1) {
                Modelo::ejecutar('DELETE FROM `rate_limits` WHERE `ventana` < ?', [$ventana - 300]);
            }

            $restantes = max(0, $limite - $usadas);
            $reinicio  = $ventana + 60;

            $headers = [
                'X-RateLimit-Limit'     => (string) $limite,
                'X-RateLimit-Remaining' => (string) $restantes,
                'X-RateLimit-Reset'     => (string) $reinicio,
            ];

            if ($usadas > $limite) {
                $esperar = max(1, $reinicio - time());
                return Response::error(
                    429,
                    "Demasiadas peticiones. Vuelve a intentar en {$esperar} segundos.",
                    'LIMITE_EXCEDIDO',
                    ['reintentar_en' => $esperar]
                )->conHeadersSiFaltan($headers + ['Retry-After' => (string) $esperar]);
            }

            return $siguiente($peticion)->conHeadersSiFaltan($headers);
        };
    }

    /* =============================================================
       AUTENTICACION
       ============================================================= */
    public static function auth(): callable
    {
        return static function (Request $peticion, callable $siguiente): Response {
            $payload = Jwt::verificar($peticion->tokenBearer());

            if (!$payload || !isset($payload['sub'])) {
                throw ApiException::noAutorizado('Sesion no valida o expirada. Vuelve a iniciar sesion.');
            }

            $usuario = Modelo::fila(
                'SELECT `usuario_id`,`rol`,`identificador`,`nombre_completo`,`activo`
                 FROM `usuarios` WHERE `usuario_id` = ? LIMIT 1',
                [(int) $payload['sub']]
            );

            if (!$usuario) {
                throw ApiException::noAutorizado('El usuario ya no existe.');
            }
            if ((int) $usuario['activo'] !== 1) {
                throw ApiException::prohibido('Tu usuario esta desactivado. Contacta a control de estudios.');
            }

            $usuario['perfil_id'] = isset($payload['pid']) ? (int) $payload['pid'] : null;
            $peticion->setUsuario($usuario);

            return $siguiente($peticion);
        };
    }

    /* =============================================================
       ROLES
       ============================================================= */
    public static function rol(string ...$roles): callable
    {
        return static function (Request $peticion, callable $siguiente) use ($roles): Response {
            if ($peticion->usuario() === null) {
                throw ApiException::noAutorizado();
            }
            if (!$peticion->esRol(...$roles)) {
                throw ApiException::prohibido(
                    'Esta accion es solo para: ' . implode(', ', array_map('strtolower', $roles)) . '.'
                );
            }
            return $siguiente($peticion);
        };
    }

    /* =============================================================
       ESTADO DE LA BASE DE DATOS
       -------------------------------------------------------------
       Se ejecuta al inicio para que un fallo de BD devuelva 503 con
       un mensaje claro en vez de un 500 opaco.
       ============================================================= */
    public static function requiereBd(): callable
    {
        return static function (Request $peticion, callable $siguiente): Response {
            Database::conexion();
            return $siguiente($peticion);
        };
    }
}
