<?php

namespace App\Models;

use App\Core\Modelo;

class Asignacion extends Modelo
{
    protected static string $tabla = 'asignaciones';
    protected static string $llave = 'asignacion_id';

    /* =============================================================
       ASIGNACIONES
       ============================================================= */

    public static function listar(array $filtros): array
    {
        $where  = ['1 = 1'];
        $params = [];

        foreach ([
            'periodo_id'  => 'a.`periodo_id`',
            'seccion_id'  => 'a.`seccion_id`',
            'materia_id'  => 'a.`materia_id`',
            'profesor_id' => 'a.`profesor_id`',
            'modulo'      => 'a.`modulo`',
        ] as $clave => $columna) {
            if (!empty($filtros[$clave])) {
                $where[]  = "{$columna} = ?";
                $params[] = (int) $filtros[$clave];
            }
        }

        if (!empty($filtros['estado'])) {
            $where[]  = 'a.`estado` = ?';
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['sin_docente'])) {
            $where[] = 'a.`profesor_id` IS NULL';
        }

        return self::filas(
            'SELECT a.*,
                    m.`codigo` AS `materia_codigo`, m.`nombre` AS `materia`, m.`semestre`,
                    m.`requiere_laboratorio`, m.`es_electiva`, m.`grupo_electiva`,
                    m.`sesiones_semana`, m.`bloques_sesion`,
                    s.`codigo` AS `seccion`, s.`carrera_id`,
                    c.`codigo` AS `carrera_codigo`, c.`nombre` AS `carrera`, c.`color` AS `carrera_color`,
                    p.`profesor_id`, CONCAT(p.`nombres`," ",p.`apellidos`) AS `profesor`, p.`telefono` AS `profesor_telefono`,
                    e.`codigo` AS `espacio`, e.`tipo` AS `espacio_tipo`,
                    (SELECT COUNT(*) FROM `horario_bloques` hb WHERE hb.`asignacion_id` = a.`asignacion_id`) AS `bloques_ubicados`,
                    (SELECT COUNT(*) FROM `estudiante_horario` eh WHERE eh.`asignacion_id` = a.`asignacion_id`) AS `estudiantes_inscritos`
             FROM `asignaciones` a
             JOIN `materias` m ON m.`materia_id` = a.`materia_id`
             JOIN `secciones` s ON s.`seccion_id` = a.`seccion_id`
             JOIN `carreras` c ON c.`carrera_id` = s.`carrera_id`
             LEFT JOIN `profesores` p ON p.`profesor_id` = a.`profesor_id`
             LEFT JOIN `espacios` e ON e.`espacio_id` = a.`espacio_id`
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY a.`modulo`, s.`semestre`, s.`codigo`, m.`codigo`',
            $params
        );
    }

    public static function detalle(int $id): ?array
    {
        $fila = self::listar(['asignacion_id' => $id]);
        $asignacion = self::fila(
            'SELECT a.*, m.`codigo` AS `materia_codigo`, m.`nombre` AS `materia`, m.`requiere_laboratorio`,
                    m.`sesiones_semana`, m.`bloques_sesion`, m.`es_electiva`,
                    s.`codigo` AS `seccion`, s.`semestre`, s.`carrera_id`, s.`espacio_id` AS `espacio_base`,
                    p.`nombres`, p.`apellidos`, e.`codigo` AS `espacio`,
                    per.`modalidad`, per.`estado` AS `periodo_estado`
             FROM `asignaciones` a
             JOIN `materias` m ON m.`materia_id` = a.`materia_id`
             JOIN `secciones` s ON s.`seccion_id` = a.`seccion_id`
             JOIN `periodos` per ON per.`periodo_id` = a.`periodo_id`
             LEFT JOIN `profesores` p ON p.`profesor_id` = a.`profesor_id`
             LEFT JOIN `espacios` e ON e.`espacio_id` = a.`espacio_id`
             WHERE a.`asignacion_id` = ?',
            [$id]
        );

        if (!$asignacion) {
            return null;
        }

        $asignacion['bloques'] = self::bloquesDe($id);
        unset($fila);

        return $asignacion;
    }

    public static function bloquesDe(int $asignacionId): array
    {
        return self::filas(
            'SELECT hb.*, b.`orden`, b.`hora_inicio`, b.`hora_fin`, b.`etiqueta`, e.`codigo` AS `espacio`
             FROM `horario_bloques` hb
             JOIN `bloques_horario` b ON b.`bloque_id` = hb.`bloque_id`
             LEFT JOIN `espacios` e ON e.`espacio_id` = hb.`espacio_id`
             WHERE hb.`asignacion_id` = ?
             ORDER BY FIELD(hb.`dia`,"LUNES","MARTES","MIERCOLES","JUEVES","SABADO"), b.`orden`',
            [$asignacionId]
        );
    }

    /* =============================================================
       HORARIOS (rejilla)
       ============================================================= */

    /** Filtros aceptados: periodo_id, modulo, seccion_id, profesor_id, espacio_id, tipo_espacio, carrera_id, semestre. */
    public static function horario(array $filtros): array
    {
        $where  = ['1 = 1'];
        $params = [];

        foreach ([
            'periodo_id' => 'v.`periodo_id`',
            'modulo'     => 'v.`modulo`',
            'seccion_id' => 'v.`seccion_id`',
            'profesor_id'=> 'v.`profesor_id`',
            'espacio_id' => 'v.`espacio_id`',
            'carrera_id' => 'v.`carrera_id`',
            'semestre'   => 'v.`semestre`',
            'materia_id' => 'v.`materia_id`',
        ] as $clave => $columna) {
            if (isset($filtros[$clave]) && $filtros[$clave] !== null && $filtros[$clave] !== '') {
                $where[]  = "{$columna} = ?";
                $params[] = (int) $filtros[$clave];
            }
        }

        if (!empty($filtros['tipo_espacio'])) {
            $where[]  = 'v.`espacio_tipo` = ?';
            $params[] = $filtros['tipo_espacio'];
        }
        if (!empty($filtros['asignacion_ids'])) {
            $ids = array_map('intval', $filtros['asignacion_ids']);
            $where[] = 'v.`asignacion_id` IN (' . implode(',', $ids ?: [0]) . ')';
        }

        return self::filas(
            'SELECT v.* FROM `v_horario_detalle` v
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY v.`modulo`, FIELD(v.`dia`,"LUNES","MARTES","MIERCOLES","JUEVES","SABADO"), v.`bloque_orden`',
            $params
        );
    }

    /** Bloques ya ocupados por seccion / profesor / espacio en un periodo. */
    public static function ocupacion(int $periodoId, int $modulo): array
    {
        return self::filas(
            'SELECT `dia`,`bloque_id`,`seccion_id`,`profesor_id`,`espacio_id`
             FROM `horario_bloques`
             WHERE `periodo_id` = ? AND `modulo` = ?',
            [$periodoId, $modulo]
        );
    }

    public static function insertarBloque(array $datos): int
    {
        return self::insertar($datos, 'horario_bloques');
    }

    public static function limpiarBloques(int $asignacionId): void
    {
        self::ejecutar('DELETE FROM `horario_bloques` WHERE `asignacion_id` = ?', [$asignacionId]);
    }

    public static function limpiarHorarioPeriodo(int $periodoId, ?int $modulo = null): int
    {
        if ($modulo === null) {
            return self::ejecutar('DELETE FROM `horario_bloques` WHERE `periodo_id` = ?', [$periodoId])->rowCount();
        }
        return self::ejecutar(
            'DELETE FROM `horario_bloques` WHERE `periodo_id` = ? AND `modulo` = ?',
            [$periodoId, $modulo]
        )->rowCount();
    }

    /** Cambia el docente de una asignacion y arrastra sus bloques. */
    public static function reasignarDocente(int $asignacionId, ?int $profesorId): void
    {
        self::ejecutar(
            'UPDATE `asignaciones`
                SET `profesor_id` = ?, `estado` = IF(? IS NULL, "SIN_DOCENTE", "CONFIRMADA")
              WHERE `asignacion_id` = ?',
            [$profesorId, $profesorId, $asignacionId]
        );

        self::ejecutar(
            'UPDATE `horario_bloques` SET `profesor_id` = ? WHERE `asignacion_id` = ?',
            [$profesorId, $asignacionId]
        );
    }

    /**
     * Docentes que podrian tomar una asignacion sin generar choque:
     * habilitados para la materia, disponibles en esos bloques y libres.
     */
    public static function candidatos(int $asignacionId): array
    {
        $asignacion = self::fila(
            'SELECT a.`asignacion_id`, a.`periodo_id`, a.`modulo`, a.`materia_id`, a.`profesor_id`
             FROM `asignaciones` a WHERE a.`asignacion_id` = ?',
            [$asignacionId]
        );

        if (!$asignacion) {
            return [];
        }

        $bloques = self::bloquesDe($asignacionId);

        $candidatos = self::filas(
            'SELECT p.`profesor_id`, CONCAT(p.`nombres`," ",p.`apellidos`) AS `profesor`,
                    p.`telefono`, p.`tipo_contrato`, p.`max_bloques_semana`, pm.`afinidad`,
                    (SELECT COUNT(*) FROM `horario_bloques` hb
                      WHERE hb.`profesor_id` = p.`profesor_id` AND hb.`periodo_id` = ? AND hb.`modulo` = ?) AS `carga_actual`
             FROM `profesor_materias` pm
             JOIN `profesores` p ON p.`profesor_id` = pm.`profesor_id`
             WHERE pm.`materia_id` = ? AND p.`activo` = 1 AND p.`profesor_id` <> COALESCE(?, 0)
             ORDER BY pm.`afinidad` DESC',
            [$asignacion['periodo_id'], $asignacion['modulo'], $asignacion['materia_id'], $asignacion['profesor_id']]
        );

        if (!$bloques) {
            foreach ($candidatos as &$c) {
                $c['libre'] = true;
                $c['motivo'] = '';
            }
            return $candidatos;
        }

        $pares = array_map(static fn ($b) => [$b['dia'], (int) $b['bloque_id']], $bloques);

        foreach ($candidatos as &$c) {
            $choques = 0;
            foreach ($pares as [$dia, $bloqueId]) {
                $ocupado = self::valor(
                    'SELECT 1 FROM `horario_bloques`
                      WHERE `profesor_id` = ? AND `periodo_id` = ? AND `modulo` = ? AND `dia` = ? AND `bloque_id` = ?
                      LIMIT 1',
                    [$c['profesor_id'], $asignacion['periodo_id'], $asignacion['modulo'], $dia, $bloqueId]
                );
                if ($ocupado) {
                    $choques++;
                }
            }
            $c['libre']  = $choques === 0;
            $c['motivo'] = $choques > 0 ? "Ya tiene clase en {$choques} de los bloques" : '';
            $c['sobrecargado'] = (int) $c['carga_actual'] >= (int) $c['max_bloques_semana'];
        }

        usort($candidatos, static function ($a, $b) {
            if ($a['libre'] !== $b['libre']) {
                return $a['libre'] ? -1 : 1;
            }
            return (float) $b['afinidad'] <=> (float) $a['afinidad'];
        });

        return $candidatos;
    }

    /* =============================================================
       HORARIO DEL ESTUDIANTE
       ============================================================= */

    /** Oferta disponible para el estudiante en su periodo. */
    public static function ofertaParaEstudiante(int $periodoId, int $carreraId, int $semestre): array
    {
        return self::filas(
            'SELECT a.`asignacion_id`, a.`modulo`, a.`modalidad_clase`,
                    m.`materia_id`, m.`codigo` AS `materia_codigo`, m.`nombre` AS `materia`,
                    m.`semestre`, m.`unidades_credito`, m.`es_electiva`, m.`grupo_electiva`,
                    m.`requiere_laboratorio`,
                    s.`seccion_id`, s.`codigo` AS `seccion`,
                    c.`codigo` AS `carrera_codigo`, c.`nombre` AS `carrera`, c.`color` AS `carrera_color`,
                    p.`profesor_id`, CONCAT(p.`nombres`," ",p.`apellidos`) AS `profesor`,
                    e.`codigo` AS `espacio`, e.`tipo` AS `espacio_tipo`,
                    (SELECT COUNT(*) FROM `horario_bloques` hb WHERE hb.`asignacion_id` = a.`asignacion_id`) AS `bloques`
             FROM `asignaciones` a
             JOIN `materias` m ON m.`materia_id` = a.`materia_id`
             JOIN `secciones` s ON s.`seccion_id` = a.`seccion_id`
             JOIN `carreras` c ON c.`carrera_id` = s.`carrera_id`
             LEFT JOIN `profesores` p ON p.`profesor_id` = a.`profesor_id`
             LEFT JOIN `espacios` e ON e.`espacio_id` = a.`espacio_id`
             WHERE a.`periodo_id` = ?
               AND (s.`carrera_id` = ? OR c.`codigo` = "EGE")
               AND m.`semestre` BETWEEN ? AND ?
             ORDER BY m.`semestre`, a.`modulo`, m.`codigo`',
            [$periodoId, $carreraId, max(1, $semestre - 1), $semestre]
        );
    }

    public static function horarioEstudiante(int $inscripcionId): array
    {
        return self::filas(
            'SELECT eh.`estudiante_horario_id`, eh.`asignacion_id`, eh.`modalidad_cursada`, eh.`motivo_virtual`,
                    v.*
             FROM `estudiante_horario` eh
             JOIN `v_horario_detalle` v ON v.`asignacion_id` = eh.`asignacion_id`
             WHERE eh.`inscripcion_id` = ?
             ORDER BY v.`modulo`, FIELD(v.`dia`,"LUNES","MARTES","MIERCOLES","JUEVES","SABADO"), v.`bloque_orden`',
            [$inscripcionId]
        );
    }

    public static function materiasElegidas(int $inscripcionId): array
    {
        return self::filas(
            'SELECT eh.*, m.`codigo` AS `materia_codigo`, m.`nombre` AS `materia`, m.`unidades_credito`,
                    a.`modulo`, s.`codigo` AS `seccion`,
                    CONCAT(COALESCE(p.`nombres`,"Sin"), " ", COALESCE(p.`apellidos`,"docente")) AS `profesor`
             FROM `estudiante_horario` eh
             JOIN `asignaciones` a ON a.`asignacion_id` = eh.`asignacion_id`
             JOIN `materias` m ON m.`materia_id` = a.`materia_id`
             JOIN `secciones` s ON s.`seccion_id` = a.`seccion_id`
             LEFT JOIN `profesores` p ON p.`profesor_id` = a.`profesor_id`
             WHERE eh.`inscripcion_id` = ?
             ORDER BY a.`modulo`, m.`codigo`',
            [$inscripcionId]
        );
    }
}
