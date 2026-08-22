<?php

namespace App\Models;

use App\Core\Modelo;

class Profesor extends Modelo
{
    protected static string $tabla = 'profesores';
    protected static string $llave = 'profesor_id';
    protected static array $ordenables = ['apellidos', 'nombres', 'cedula', 'tipo_contrato', 'creado_en'];

    public static function listar(array $filtros, int $limite, int $desfase, string $orden): array
    {
        [$where, $params] = self::filtros($filtros);

        return self::filas(
            "SELECT p.*,
                    CONCAT(p.`nombres`,' ',p.`apellidos`) AS `nombre_completo`,
                    (SELECT COUNT(*) FROM `profesor_habilidades` ph WHERE ph.`profesor_id` = p.`profesor_id`) AS `total_habilidades`,
                    (SELECT COUNT(*) FROM `profesor_disponibilidad` pd WHERE pd.`profesor_id` = p.`profesor_id`) AS `total_disponibilidad`,
                    (SELECT COUNT(*) FROM `profesor_materias` pm WHERE pm.`profesor_id` = p.`profesor_id` AND pm.`confirmado` = 1) AS `total_materias`,
                    (SELECT COUNT(*) FROM `horario_bloques` hb
                       JOIN `periodos` per ON per.`periodo_id` = hb.`periodo_id` AND per.`estado` <> 'FINALIZADO'
                      WHERE hb.`profesor_id` = p.`profesor_id`) AS `bloques_asignados`
             FROM `profesores` p
             WHERE {$where}
             ORDER BY {$orden}
             LIMIT {$limite} OFFSET {$desfase}",
            $params
        );
    }

    public static function contarFiltrado(array $filtros): int
    {
        [$where, $params] = self::filtros($filtros);
        return (int) self::valor("SELECT COUNT(*) FROM `profesores` p WHERE {$where}", $params);
    }

    private static function filtros(array $f): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (isset($f['activo'])) {
            $where[] = 'p.`activo` = ?';
            $params[] = (int) $f['activo'];
        }
        if (!empty($f['tipo_contrato'])) {
            $where[] = 'p.`tipo_contrato` = ?';
            $params[] = $f['tipo_contrato'];
        }
        if (!empty($f['buscar'])) {
            $where[] = "(CONCAT(p.`nombres`,' ',p.`apellidos`) LIKE ? OR p.`cedula` LIKE ? OR p.`correo` LIKE ?)";
            $b = '%' . $f['buscar'] . '%';
            array_push($params, $b, $b, $b);
        }
        if (!empty($f['materia_id'])) {
            $where[] = 'EXISTS (SELECT 1 FROM `profesor_materias` pm WHERE pm.`profesor_id` = p.`profesor_id` AND pm.`materia_id` = ?)';
            $params[] = (int) $f['materia_id'];
        }
        if (!empty($f['incompletos'])) {
            $where[] = 'p.`paso_registro` < 5';
        }

        return [implode(' AND ', $where), $params];
    }

    public static function detalle(int $id): ?array
    {
        $profesor = self::fila(
            "SELECT p.*, CONCAT(p.`nombres`,' ',p.`apellidos`) AS `nombre_completo`
             FROM `profesores` p WHERE p.`profesor_id` = ?",
            [$id]
        );

        if (!$profesor) {
            return null;
        }

        $profesor['habilidades']    = self::habilidades($id);
        $profesor['disponibilidad'] = self::disponibilidad($id);
        $profesor['materias']       = self::materias($id);

        return $profesor;
    }

    /* ---- Habilidades --------------------------------------------- */

    public static function habilidades(int $profesorId): array
    {
        return self::filas(
            'SELECT ph.`habilidad_id`, ph.`estrellas`, h.`nombre`,
                    c.`categoria_id`, c.`nombre` AS `categoria`, c.`icono` AS `categoria_icono`
             FROM `profesor_habilidades` ph
             JOIN `habilidades` h ON h.`habilidad_id` = ph.`habilidad_id`
             JOIN `categorias_habilidad` c ON c.`categoria_id` = h.`categoria_id`
             WHERE ph.`profesor_id` = ?
             ORDER BY c.`orden`, h.`nombre`',
            [$profesorId]
        );
    }

    public static function sincronizarHabilidades(int $profesorId, array $habilidades): void
    {
        self::ejecutar('DELETE FROM `profesor_habilidades` WHERE `profesor_id` = ?', [$profesorId]);

        foreach ($habilidades as $h) {
            $habilidadId = (int) ($h['habilidad_id'] ?? 0);
            if ($habilidadId <= 0) {
                continue;
            }
            self::insertar([
                'profesor_id'  => $profesorId,
                'habilidad_id' => $habilidadId,
                'estrellas'    => max(1, min(5, (int) ($h['estrellas'] ?? 3))),
            ], 'profesor_habilidades');
        }
    }

    /* ---- Disponibilidad ------------------------------------------ */

    public static function disponibilidad(int $profesorId, ?int $periodoId = null): array
    {
        if ($periodoId !== null) {
            $especifica = self::filas(
                'SELECT * FROM `profesor_disponibilidad`
                  WHERE `profesor_id` = ? AND `periodo_id` = ?
                  ORDER BY FIELD(`dia`,"LUNES","MARTES","MIERCOLES","JUEVES","SABADO"), `hora_inicio`',
                [$profesorId, $periodoId]
            );
            if ($especifica) {
                return $especifica;
            }
        }

        return self::filas(
            'SELECT * FROM `profesor_disponibilidad`
              WHERE `profesor_id` = ? AND `periodo_id` IS NULL
              ORDER BY FIELD(`dia`,"LUNES","MARTES","MIERCOLES","JUEVES","SABADO"), `hora_inicio`',
            [$profesorId]
        );
    }

    public static function sincronizarDisponibilidad(int $profesorId, array $franjas, ?int $periodoId = null): void
    {
        if ($periodoId === null) {
            self::ejecutar('DELETE FROM `profesor_disponibilidad` WHERE `profesor_id` = ? AND `periodo_id` IS NULL', [$profesorId]);
        } else {
            self::ejecutar('DELETE FROM `profesor_disponibilidad` WHERE `profesor_id` = ? AND `periodo_id` = ?', [$profesorId, $periodoId]);
        }

        foreach ($franjas as $f) {
            self::insertar([
                'profesor_id' => $profesorId,
                'periodo_id'  => $periodoId,
                'dia'         => $f['dia'],
                'hora_inicio' => $f['hora_inicio'],
                'hora_fin'    => $f['hora_fin'],
            ], 'profesor_disponibilidad');
        }
    }

    /* ---- Materias habilitadas ------------------------------------ */

    public static function materias(int $profesorId): array
    {
        return self::filas(
            'SELECT pm.*, m.`codigo`, m.`nombre`, m.`semestre`, m.`requiere_laboratorio`,
                    c.`codigo` AS `carrera_codigo`, c.`nombre` AS `carrera`, c.`color` AS `carrera_color`
             FROM `profesor_materias` pm
             JOIN `materias` m ON m.`materia_id` = pm.`materia_id`
             JOIN `carreras` c ON c.`carrera_id` = m.`carrera_id`
             WHERE pm.`profesor_id` = ?
             ORDER BY pm.`afinidad` DESC, m.`codigo`',
            [$profesorId]
        );
    }

    public static function sincronizarMaterias(int $profesorId, array $materias): void
    {
        self::ejecutar('DELETE FROM `profesor_materias` WHERE `profesor_id` = ?', [$profesorId]);

        foreach ($materias as $m) {
            $materiaId = (int) ($m['materia_id'] ?? 0);
            if ($materiaId <= 0) {
                continue;
            }
            self::insertar([
                'profesor_id' => $profesorId,
                'materia_id'  => $materiaId,
                'afinidad'    => round((float) ($m['afinidad'] ?? 0), 2),
                'origen'      => in_array(($m['origen'] ?? 'MANUAL'), ['AUTO', 'MANUAL'], true) ? $m['origen'] : 'MANUAL',
                'confirmado'  => !empty($m['confirmado']) ? 1 : 0,
            ], 'profesor_materias');
        }
    }

    public static function avanzarPaso(int $profesorId, int $paso): void
    {
        self::ejecutar(
            'UPDATE `profesores` SET `paso_registro` = GREATEST(`paso_registro`, ?) WHERE `profesor_id` = ?',
            [max(1, min(5, $paso)), $profesorId]
        );
    }

    public static function porUsuario(int $usuarioId): ?array
    {
        return self::fila('SELECT * FROM `profesores` WHERE `usuario_id` = ?', [$usuarioId]);
    }

    /** Carga actual (bloques ocupados) de un docente en un periodo. */
    public static function carga(int $profesorId, int $periodoId): array
    {
        return self::filas(
            'SELECT hb.`modulo`, COUNT(*) AS `bloques`, COUNT(DISTINCT hb.`asignacion_id`) AS `materias`
             FROM `horario_bloques` hb
             WHERE hb.`profesor_id` = ? AND hb.`periodo_id` = ?
             GROUP BY hb.`modulo`',
            [$profesorId, $periodoId]
        );
    }
}
