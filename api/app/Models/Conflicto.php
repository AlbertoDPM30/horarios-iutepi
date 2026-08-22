<?php

namespace App\Models;

use App\Core\Modelo;

class Conflicto extends Modelo
{
    protected static string $tabla = 'conflictos';
    protected static string $llave = 'conflicto_id';

    public static function listar(array $filtros): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (!empty($filtros['periodo_id'])) {
            $where[]  = 'c.`periodo_id` = ?';
            $params[] = (int) $filtros['periodo_id'];
        }
        if (!empty($filtros['estado'])) {
            $where[]  = 'c.`estado` = ?';
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['tipo'])) {
            $where[]  = 'c.`tipo` = ?';
            $params[] = $filtros['tipo'];
        }
        if (!empty($filtros['profesor_id'])) {
            $where[]  = 'c.`profesor_id` = ?';
            $params[] = (int) $filtros['profesor_id'];
        }

        return self::filas(
            'SELECT c.*,
                    m.`codigo` AS `materia_codigo`, m.`nombre` AS `materia`,
                    s.`codigo` AS `seccion`, s.`semestre`,
                    CONCAT(p.`nombres`," ",p.`apellidos`) AS `profesor`,
                    per.`codigo` AS `periodo`, per.`estado` AS `periodo_estado`,
                    u.`nombre_completo` AS `resuelto_por_nombre`
             FROM `conflictos` c
             JOIN `periodos` per ON per.`periodo_id` = c.`periodo_id`
             LEFT JOIN `materias` m ON m.`materia_id` = c.`materia_id`
             LEFT JOIN `secciones` s ON s.`seccion_id` = c.`seccion_id`
             LEFT JOIN `profesores` p ON p.`profesor_id` = c.`profesor_id`
             LEFT JOIN `usuarios` u ON u.`usuario_id` = c.`resuelto_por`
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY FIELD(c.`estado`,"PENDIENTE","IGNORADO","RESUELTO"),
                      FIELD(c.`severidad`,"CRITICA","ALTA","MEDIA","BAJA"),
                      c.`creado_en` DESC',
            $params
        );
    }

    public static function detalle(int $id): ?array
    {
        $conflicto = self::listar([]);
        foreach ($conflicto as $c) {
            if ((int) $c['conflicto_id'] === $id) {
                $c['contexto'] = $c['contexto'] ? json_decode($c['contexto'], true) : null;
                if ($c['asignacion_id']) {
                    $c['candidatos'] = Asignacion::candidatos((int) $c['asignacion_id']);
                    $c['bloques']    = Asignacion::bloquesDe((int) $c['asignacion_id']);
                }
                return $c;
            }
        }
        return null;
    }

    public static function registrar(array $datos): int
    {
        if (isset($datos['contexto']) && is_array($datos['contexto'])) {
            $datos['contexto'] = json_encode($datos['contexto'], JSON_UNESCAPED_UNICODE);
        }
        return self::insertar($datos);
    }

    public static function pendientes(int $periodoId): int
    {
        return (int) self::valor(
            'SELECT COUNT(*) FROM `conflictos` WHERE `periodo_id` = ? AND `estado` = "PENDIENTE"',
            [$periodoId]
        );
    }

    public static function limpiarDePeriodo(int $periodoId): void
    {
        self::ejecutar(
            'DELETE FROM `conflictos` WHERE `periodo_id` = ? AND `estado` = "PENDIENTE"',
            [$periodoId]
        );
    }

    public static function resolver(int $id, string $resolucion, ?int $usuarioId, string $nota = ''): void
    {
        self::ejecutar(
            'UPDATE `conflictos`
                SET `estado` = "RESUELTO", `resolucion` = ?, `nota_resolucion` = ?,
                    `resuelto_por` = ?, `resuelto_en` = NOW()
              WHERE `conflicto_id` = ?',
            [$resolucion, $nota, $usuarioId, $id]
        );
    }

    public static function resumen(int $periodoId): array
    {
        return self::filas(
            'SELECT `tipo`, `severidad`, COUNT(*) AS `total`
             FROM `conflictos`
             WHERE `periodo_id` = ? AND `estado` = "PENDIENTE"
             GROUP BY `tipo`, `severidad`',
            [$periodoId]
        );
    }
}
