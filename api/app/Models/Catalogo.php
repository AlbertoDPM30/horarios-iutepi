<?php

namespace App\Models;

use App\Core\Modelo;

/** Catalogos estables: carreras, bloques, habilidades y modulos de la app. */
class Catalogo extends Modelo
{
    /* ---- Carreras ------------------------------------------------ */

    public static function carreras(bool $soloActivas = true): array
    {
        $where = $soloActivas ? 'WHERE `activo` = 1' : '';
        return self::filas(
            "SELECT c.*, (SELECT COUNT(*) FROM `materias` m WHERE m.`carrera_id` = c.`carrera_id` AND m.`activo` = 1) AS `total_materias`
             FROM `carreras` c {$where} ORDER BY c.`carrera_id`"
        );
    }

    public static function carrera(int $id): ?array
    {
        return self::buscar($id, 'carreras', 'carrera_id');
    }

    /* ---- Bloques horarios ---------------------------------------- */

    public static function bloques(?string $modalidad = null): array
    {
        if ($modalidad) {
            return self::filas(
                'SELECT * FROM `bloques_horario` WHERE `modalidad` = ? ORDER BY `orden`',
                [$modalidad]
            );
        }
        return self::filas('SELECT * FROM `bloques_horario` ORDER BY `modalidad`, `orden`');
    }

    /** Solo los bloques donde se puede dictar clase (sin recesos). */
    public static function bloquesLectivos(string $modalidad): array
    {
        return self::filas(
            'SELECT * FROM `bloques_horario` WHERE `modalidad` = ? AND `es_receso` = 0 ORDER BY `orden`',
            [$modalidad]
        );
    }

    /** Dias habiles por modalidad: viernes y domingo estan bloqueados. */
    public static function dias(string $modalidad): array
    {
        return $modalidad === 'SABATINO'
            ? ['SABADO']
            : ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES'];
    }

    /* ---- Habilidades --------------------------------------------- */

    public static function categoriasConHabilidades(): array
    {
        $categorias = self::filas('SELECT * FROM `categorias_habilidad` ORDER BY `orden`, `nombre`');
        $habilidades = self::filas(
            'SELECT h.*, (SELECT COUNT(*) FROM `profesor_habilidades` ph WHERE ph.`habilidad_id` = h.`habilidad_id`) AS `docentes`,
                    (SELECT COUNT(*) FROM `materia_habilidades` mh WHERE mh.`habilidad_id` = h.`habilidad_id`) AS `materias`
             FROM `habilidades` h WHERE h.`activo` = 1 ORDER BY h.`nombre`'
        );

        $porCategoria = [];
        foreach ($habilidades as $h) {
            $porCategoria[(int) $h['categoria_id']][] = $h;
        }

        foreach ($categorias as &$c) {
            $c['habilidades'] = $porCategoria[(int) $c['categoria_id']] ?? [];
        }

        return $categorias;
    }

    public static function habilidades(): array
    {
        return self::filas(
            'SELECT h.*, c.`nombre` AS `categoria`, c.`icono` AS `categoria_icono`
             FROM `habilidades` h
             JOIN `categorias_habilidad` c ON c.`categoria_id` = h.`categoria_id`
             WHERE h.`activo` = 1
             ORDER BY c.`orden`, h.`nombre`'
        );
    }

    /* ---- Modulos de la aplicacion -------------------------------- */

    /**
     * Devuelve los modulos visibles para el rol con la bandera `vacio`,
     * que es la que el dashboard pinta en amarillo.
     */
    public static function modulos(string $rol): array
    {
        $modulos = self::filas(
            'SELECT * FROM `modulos_sistema` WHERE `activo` = 1 AND FIND_IN_SET(?, `roles`) ORDER BY `orden`',
            [$rol]
        );

        foreach ($modulos as &$m) {
            $tabla = preg_replace('/[^a-z_]/', '', $m['tabla_conteo']);
            $m['registros'] = (int) self::valor("SELECT COUNT(*) FROM `{$tabla}`");
            $m['vacio']     = $m['registros'] === 0;
            $m['roles']     = explode(',', $m['roles']);
        }

        return $modulos;
    }

    /* ---- Configuracion ------------------------------------------- */

    public static function configuracion(): array
    {
        $filas = self::filas('SELECT `clave`,`valor` FROM `configuracion`');
        return array_column($filas, 'valor', 'clave');
    }

    public static function config(string $clave, mixed $porDefecto = null): mixed
    {
        $valor = self::valor('SELECT `valor` FROM `configuracion` WHERE `clave` = ?', [$clave]);
        return $valor ?? $porDefecto;
    }
}
