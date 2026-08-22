<?php

namespace App\Models;

use App\Core\Modelo;

class Materia extends Modelo
{
    protected static string $tabla = 'materias';
    protected static string $llave = 'materia_id';
    protected static array $ordenables = ['codigo', 'nombre', 'semestre', 'unidades_credito'];

    public static function listar(array $filtros, int $limite, int $desfase, string $orden): array
    {
        [$where, $params] = self::filtros($filtros);

        return self::filas(
            "SELECT m.*, c.`nombre` AS `carrera`, c.`codigo` AS `carrera_codigo`, c.`color` AS `carrera_color`,
                    (SELECT COUNT(*) FROM `profesor_materias` pm WHERE pm.`materia_id` = m.`materia_id`) AS `docentes_habilitados`
             FROM `materias` m
             JOIN `carreras` c ON c.`carrera_id` = m.`carrera_id`
             WHERE {$where}
             ORDER BY {$orden}
             LIMIT {$limite} OFFSET {$desfase}",
            $params
        );
    }

    public static function contarFiltrado(array $filtros): int
    {
        [$where, $params] = self::filtros($filtros);
        return (int) self::valor("SELECT COUNT(*) FROM `materias` m WHERE {$where}", $params);
    }

    private static function filtros(array $f): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (isset($f['activo'])) {
            $where[] = 'm.`activo` = ?';
            $params[] = (int) $f['activo'];
        }
        if (!empty($f['carrera_id'])) {
            $where[] = 'm.`carrera_id` = ?';
            $params[] = (int) $f['carrera_id'];
        }
        if (!empty($f['semestre'])) {
            $where[] = 'm.`semestre` = ?';
            $params[] = (int) $f['semestre'];
        }
        if (isset($f['requiere_laboratorio'])) {
            $where[] = 'm.`requiere_laboratorio` = ?';
            $params[] = (int) $f['requiere_laboratorio'];
        }
        if (!empty($f['buscar'])) {
            $where[] = '(m.`nombre` LIKE ? OR m.`codigo` LIKE ?)';
            $params[] = '%' . $f['buscar'] . '%';
            $params[] = '%' . $f['buscar'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    public static function detalle(int $id): ?array
    {
        $materia = self::fila(
            'SELECT m.*, c.`nombre` AS `carrera`, c.`codigo` AS `carrera_codigo`, c.`color` AS `carrera_color`
             FROM `materias` m
             JOIN `carreras` c ON c.`carrera_id` = m.`carrera_id`
             WHERE m.`materia_id` = ?',
            [$id]
        );

        if (!$materia) {
            return null;
        }

        $materia['habilidades'] = self::habilidades($id);
        $materia['prelaciones'] = self::filas(
            'SELECT r.`materia_id`, r.`codigo`, r.`nombre`
             FROM `materia_prelaciones` mp
             JOIN `materias` r ON r.`materia_id` = mp.`requisito_id`
             WHERE mp.`materia_id` = ?',
            [$id]
        );
        $materia['docentes'] = self::filas(
            'SELECT p.`profesor_id`, CONCAT(p.`nombres`," ",p.`apellidos`) AS `profesor`, pm.`afinidad`, pm.`confirmado`
             FROM `profesor_materias` pm
             JOIN `profesores` p ON p.`profesor_id` = pm.`profesor_id`
             WHERE pm.`materia_id` = ? AND p.`activo` = 1
             ORDER BY pm.`afinidad` DESC',
            [$id]
        );

        return $materia;
    }

    public static function habilidades(int $materiaId): array
    {
        return self::filas(
            'SELECT mh.`habilidad_id`, mh.`estrellas_minimas`, mh.`peso`,
                    h.`nombre`, c.`nombre` AS `categoria`, c.`categoria_id`
             FROM `materia_habilidades` mh
             JOIN `habilidades` h ON h.`habilidad_id` = mh.`habilidad_id`
             JOIN `categorias_habilidad` c ON c.`categoria_id` = h.`categoria_id`
             WHERE mh.`materia_id` = ?
             ORDER BY mh.`peso` DESC, h.`nombre`',
            [$materiaId]
        );
    }

    public static function sincronizarHabilidades(int $materiaId, array $habilidades): void
    {
        self::ejecutar('DELETE FROM `materia_habilidades` WHERE `materia_id` = ?', [$materiaId]);

        foreach ($habilidades as $h) {
            $habilidadId = (int) ($h['habilidad_id'] ?? 0);
            if ($habilidadId <= 0) {
                continue;
            }
            self::insertar([
                'materia_id'        => $materiaId,
                'habilidad_id'      => $habilidadId,
                'estrellas_minimas' => max(1, min(5, (int) ($h['estrellas_minimas'] ?? 3))),
                'peso'              => max(1, min(5, (int) ($h['peso'] ?? 1))),
            ], 'materia_habilidades');
        }
    }

    /**
     * Materias que le tocan a una seccion: las de su carrera y semestre
     * mas las de Estudios Generales del mismo semestre.
     */
    public static function delPlan(int $carreraId, int $semestre): array
    {
        return self::filas(
            'SELECT m.* FROM `materias` m
             WHERE m.`activo` = 1 AND m.`semestre` = ?
               AND (m.`carrera_id` = ? OR m.`carrera_id` = (SELECT `carrera_id` FROM `carreras` WHERE `codigo` = "EGE"))
             ORDER BY m.`es_electiva`, m.`codigo`',
            [$semestre, $carreraId]
        );
    }
}
