<?php

namespace App\Services;

use App\Core\ApiException;
use App\Core\Env;
use App\Core\Jwt;
use App\Core\Request;
use App\Models\Usuario;

/**
 * Tres puertas de entrada distintas, una por rol:
 *   ESTUDIANTE -> solo su codigo (asi nadie sin inscribir entra)
 *   DOCENTE    -> solo su cedula
 *   ADMIN      -> correo + contrasena
 *
 * Los dos primeros son credenciales debiles a proposito (es lo que pidio
 * el instituto), por eso el limitador de peticiones sobre /auth es
 * estrecho y ademas se bloquea el usuario tras 5 fallos.
 */
final class AuthService
{
    public static function loginEstudiante(Request $peticion, string $codigo): array
    {
        $codigo = strtoupper(trim($codigo));

        $estudiante = Usuario::fila(
            'SELECT e.*, u.`usuario_id`, u.`activo` AS `usuario_activo`
             FROM `estudiantes` e
             LEFT JOIN `usuarios` u ON u.`usuario_id` = e.`usuario_id`
             WHERE e.`codigo` = ?',
            [$codigo]
        );

        if (!$estudiante) {
            throw ApiException::noAutorizado('Ese codigo de estudiante no esta registrado. Verifica con control de estudios.');
        }
        if ($estudiante['estado'] !== 'ACTIVO') {
            throw ApiException::prohibido('Tu inscripcion no esta activa. Acercate a control de estudios.');
        }

        // Un estudiante cargado sin usuario aun: se crea al primer acceso
        $usuarioId = $estudiante['usuario_id'] ? (int) $estudiante['usuario_id'] : null;
        if ($usuarioId === null) {
            $usuarioId = Usuario::crear(
                'ESTUDIANTE',
                $codigo,
                $estudiante['nombres'] . ' ' . $estudiante['apellidos']
            );
            Usuario::actualizar((int) $estudiante['estudiante_id'], ['usuario_id' => $usuarioId], 'estudiantes', 'estudiante_id');
        }

        return self::emitirSesion($peticion, $usuarioId, (int) $estudiante['estudiante_id']);
    }

    public static function loginDocente(Request $peticion, string $cedula): array
    {
        $cedula = self::normalizarCedula($cedula);

        $profesor = Usuario::fila(
            'SELECT p.*, u.`usuario_id`, u.`activo` AS `usuario_activo`
             FROM `profesores` p
             LEFT JOIN `usuarios` u ON u.`usuario_id` = p.`usuario_id`
             WHERE REPLACE(REPLACE(UPPER(p.`cedula`),"V-",""),"-","") = ?',
            [$cedula]
        );

        if (!$profesor) {
            throw ApiException::noAutorizado('Esa cedula no esta registrada como docente.');
        }
        if ((int) $profesor['activo'] !== 1) {
            throw ApiException::prohibido('Tu registro de docente esta inactivo.');
        }

        $usuarioId = $profesor['usuario_id'] ? (int) $profesor['usuario_id'] : null;
        if ($usuarioId === null) {
            $usuarioId = Usuario::crear(
                'DOCENTE',
                $profesor['cedula'],
                $profesor['nombres'] . ' ' . $profesor['apellidos']
            );
            Usuario::actualizar((int) $profesor['profesor_id'], ['usuario_id' => $usuarioId], 'profesores', 'profesor_id');
        }

        return self::emitirSesion($peticion, $usuarioId, (int) $profesor['profesor_id']);
    }

    public static function loginAdmin(Request $peticion, string $correo, string $password): array
    {
        $usuario = Usuario::porIdentificador('ADMIN', strtolower(trim($correo)));

        if (!$usuario) {
            // Mismo mensaje que con clave mala: no revelamos si el correo existe
            throw ApiException::noAutorizado('Correo o contrasena incorrectos.');
        }

        if ($usuario['bloqueado_hasta'] !== null && strtotime($usuario['bloqueado_hasta']) > time()) {
            $minutos = (int) ceil((strtotime($usuario['bloqueado_hasta']) - time()) / 60);
            throw ApiException::prohibido("Demasiados intentos fallidos. Intenta de nuevo en {$minutos} minuto(s).");
        }

        if (!password_verify($password, (string) $usuario['password_hash'])) {
            Usuario::registrarFallo((int) $usuario['usuario_id']);
            throw ApiException::noAutorizado('Correo o contrasena incorrectos.');
        }

        if ((int) $usuario['activo'] !== 1) {
            throw ApiException::prohibido('Tu usuario esta desactivado.');
        }

        // Rehash si el algoritmo por defecto cambio
        if (password_needs_rehash((string) $usuario['password_hash'], PASSWORD_BCRYPT)) {
            Usuario::actualizar((int) $usuario['usuario_id'], ['password_hash' => password_hash($password, PASSWORD_BCRYPT)]);
        }

        return self::emitirSesion($peticion, (int) $usuario['usuario_id'], null);
    }

    /* ---------------------------------------------------------------- */

    private static function emitirSesion(Request $peticion, int $usuarioId, ?int $perfilId): array
    {
        $usuario = Usuario::buscar($usuarioId);
        if (!$usuario || (int) $usuario['activo'] !== 1) {
            throw ApiException::prohibido('Tu usuario esta desactivado.');
        }

        $ttl        = Env::int('JWT_TTL', 28800);
        $ttlRefresh = Env::int('JWT_REFRESH_TTL', 604800);

        $token = Jwt::firmar([
            'sub' => $usuarioId,
            'rol' => $usuario['rol'],
            'pid' => $perfilId,
            'nom' => $usuario['nombre_completo'],
        ], $ttl);

        $refresh = bin2hex(random_bytes(32));
        Usuario::guardarSesion($usuarioId, $refresh, $peticion->ip(), $peticion->userAgent(), $ttlRefresh);
        Usuario::registrarAcceso($usuarioId);

        return [
            'token'         => $token,
            'refresh_token' => $refresh,
            'expira_en'     => time() + $ttl,
            'usuario'       => Usuario::perfil($usuario),
        ];
    }

    public static function refrescar(Request $peticion, string $refreshToken): array
    {
        $sesion = Usuario::sesionValida($refreshToken);
        if (!$sesion) {
            throw ApiException::noAutorizado('La sesion expiro. Vuelve a iniciar sesion.');
        }

        // Rotacion: el refresh usado se revoca y se emite uno nuevo
        Usuario::revocarSesion($refreshToken);

        $usuarioId = (int) $sesion['usuario_id'];
        $perfil    = Usuario::perfil((array) Usuario::buscar($usuarioId));
        $perfilId  = $perfil['profesor_id'] ?? $perfil['estudiante_id'] ?? null;

        return self::emitirSesion($peticion, $usuarioId, $perfilId !== null ? (int) $perfilId : null);
    }

    public static function cerrarSesion(?string $refreshToken, ?int $usuarioId): void
    {
        if ($refreshToken) {
            Usuario::revocarSesion($refreshToken);
        } elseif ($usuarioId !== null) {
            Usuario::revocarTodas($usuarioId);
        }
    }

    private static function normalizarCedula(string $cedula): string
    {
        return preg_replace('/[^0-9]/', '', $cedula) ?: '';
    }
}
