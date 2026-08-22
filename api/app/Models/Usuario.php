<?php

namespace App\Models;

use App\Core\Modelo;

class Usuario extends Modelo
{
    protected static string $tabla = 'usuarios';
    protected static string $llave = 'usuario_id';

    public static function porIdentificador(string $rol, string $identificador): ?array
    {
        return self::fila(
            'SELECT * FROM `usuarios` WHERE `rol` = ? AND `identificador` = ? LIMIT 1',
            [$rol, $identificador]
        );
    }

    /** Perfil completo segun el rol, para armar la respuesta de login. */
    public static function perfil(array $usuario): array
    {
        $id = (int) $usuario['usuario_id'];

        $base = [
            'usuario_id'      => $id,
            'rol'             => $usuario['rol'],
            'identificador'   => $usuario['identificador'],
            'nombre_completo' => $usuario['nombre_completo'],
        ];

        return match ($usuario['rol']) {
            'ADMIN' => array_merge($base, (array) self::fila(
                'SELECT `cedula`,`nombres`,`apellidos`,`correo`,`telefono`,`cargo`
                 FROM `administradores` WHERE `usuario_id` = ?',
                [$id]
            )),
            'DOCENTE' => array_merge($base, (array) self::fila(
                'SELECT p.`profesor_id`,p.`cedula`,p.`nombres`,p.`apellidos`,p.`correo`,p.`telefono`,
                        p.`titulo`,p.`tipo_contrato`,p.`max_bloques_semana`,p.`paso_registro`
                 FROM `profesores` p WHERE p.`usuario_id` = ?',
                [$id]
            )),
            'ESTUDIANTE' => array_merge($base, (array) self::fila(
                'SELECT e.`estudiante_id`,e.`codigo`,e.`cedula`,e.`nombres`,e.`apellidos`,e.`correo`,
                        e.`telefono`,e.`semestre_actual`,e.`modalidad`,e.`estado`,
                        c.`carrera_id`,c.`nombre` AS `carrera`,c.`codigo` AS `carrera_codigo`,
                        (SELECT s.`codigo`
                           FROM `estudiante_inscripciones` i
                           JOIN `secciones` s ON s.`seccion_id` = i.`seccion_id`
                           JOIN `periodos` per ON per.`periodo_id` = i.`periodo_id`
                          WHERE i.`estudiante_id` = e.`estudiante_id` AND i.`estado` = "INSCRITO"
                          ORDER BY per.`fecha_inicio` DESC LIMIT 1) AS `seccion`
                 FROM `estudiantes` e
                 JOIN `carreras` c ON c.`carrera_id` = e.`carrera_id`
                 WHERE e.`usuario_id` = ?',
                [$id]
            )),
            default => $base,
        };
    }

    public static function registrarAcceso(int $usuarioId): void
    {
        self::ejecutar(
            'UPDATE `usuarios` SET `ultimo_acceso` = NOW(), `intentos_fallidos` = 0, `bloqueado_hasta` = NULL
             WHERE `usuario_id` = ?',
            [$usuarioId]
        );
    }

    public static function registrarFallo(int $usuarioId): void
    {
        self::ejecutar(
            'UPDATE `usuarios`
                SET `intentos_fallidos` = `intentos_fallidos` + 1,
                    `bloqueado_hasta` = IF(`intentos_fallidos` + 1 >= 5, DATE_ADD(NOW(), INTERVAL 10 MINUTE), `bloqueado_hasta`)
              WHERE `usuario_id` = ?',
            [$usuarioId]
        );
    }

    public static function crear(string $rol, string $identificador, string $nombre, ?string $passwordHash = null): int
    {
        return self::insertar([
            'rol'             => $rol,
            'identificador'   => $identificador,
            'password_hash'   => $passwordHash,
            'nombre_completo' => $nombre,
        ]);
    }

    /* ---- Sesiones (refresh tokens) ------------------------------- */

    public static function guardarSesion(int $usuarioId, string $token, string $ip, string $userAgent, int $ttl): void
    {
        self::insertar([
            'usuario_id' => $usuarioId,
            'token_hash' => hash('sha256', $token),
            'ip'         => $ip,
            'user_agent' => $userAgent,
            'expira_en'  => date('Y-m-d H:i:s', time() + $ttl),
        ], 'sesiones');

        // Limpieza: solo se conservan las 5 sesiones vigentes mas recientes
        self::ejecutar(
            'DELETE FROM `sesiones`
              WHERE `usuario_id` = ?
                AND (`expira_en` < NOW() OR `revocado` = 1
                     OR `sesion_id` NOT IN (
                        SELECT * FROM (SELECT `sesion_id` FROM `sesiones`
                                        WHERE `usuario_id` = ? AND `revocado` = 0 AND `expira_en` >= NOW()
                                        ORDER BY `sesion_id` DESC LIMIT 5) t))',
            [$usuarioId, $usuarioId]
        );
    }

    public static function sesionValida(string $token): ?array
    {
        return self::fila(
            'SELECT * FROM `sesiones`
              WHERE `token_hash` = ? AND `revocado` = 0 AND `expira_en` >= NOW() LIMIT 1',
            [hash('sha256', $token)]
        );
    }

    public static function revocarSesion(string $token): void
    {
        self::ejecutar('UPDATE `sesiones` SET `revocado` = 1 WHERE `token_hash` = ?', [hash('sha256', $token)]);
    }

    public static function revocarTodas(int $usuarioId): void
    {
        self::ejecutar('UPDATE `sesiones` SET `revocado` = 1 WHERE `usuario_id` = ?', [$usuarioId]);
    }
}
