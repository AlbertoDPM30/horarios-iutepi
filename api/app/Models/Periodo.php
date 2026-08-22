<?php

namespace App\Models;

use App\Core\Modelo;

class Periodo extends Modelo
{
    protected static string $tabla = 'periodos';
    protected static string $llave = 'periodo_id';
    protected static array $ordenables = ['fecha_inicio', 'codigo', 'anio', 'estado'];

    /**
     * Listado para el dashboard. Trae de una sola vez los contadores que
     * la tarjeta necesita, para no disparar N+1 desde el frontend.
     */
    public static function listar(array $filtros = []): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (!empty($filtros['estado'])) {
            $where[] = 'p.`estado` = ?';
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['modalidad'])) {
            $where[] = 'p.`modalidad` = ?';
            $params[] = $filtros['modalidad'];
        }
        if (!empty($filtros['anio'])) {
            $where[] = 'p.`anio` = ?';
            $params[] = (int) $filtros['anio'];
        }
        if (!empty($filtros['buscar'])) {
            $where[] = '(p.`codigo` LIKE ? OR p.`nombre` LIKE ?)';
            $params[] = '%' . $filtros['buscar'] . '%';
            $params[] = '%' . $filtros['buscar'] . '%';
        }

        $sql = 'SELECT p.*,
                  DATEDIFF(p.`fecha_inicio`, CURDATE()) AS `dias_para_iniciar`,
                  DATEDIFF(p.`fecha_fin`, CURDATE())    AS `dias_para_terminar`,
                  (SELECT COUNT(*) FROM `secciones` s WHERE s.`periodo_id` = p.`periodo_id`) AS `total_secciones`,
                  (SELECT COUNT(*) FROM `estudiante_inscripciones` i WHERE i.`periodo_id` = p.`periodo_id` AND i.`estado` = "INSCRITO") AS `total_estudiantes`,
                  (SELECT COUNT(*) FROM `asignaciones` a WHERE a.`periodo_id` = p.`periodo_id`) AS `total_asignaciones`,
                  (SELECT COUNT(*) FROM `asignaciones` a WHERE a.`periodo_id` = p.`periodo_id` AND a.`profesor_id` IS NULL) AS `asignaciones_sin_docente`,
                  (SELECT COUNT(*) FROM `horario_bloques` h WHERE h.`periodo_id` = p.`periodo_id`) AS `total_bloques`,
                  (SELECT COUNT(*) FROM `conflictos` c WHERE c.`periodo_id` = p.`periodo_id` AND c.`estado` = "PENDIENTE") AS `conflictos_pendientes`
                FROM `periodos` p
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY FIELD(p.`estado`, "EN_CURSO", "PLANIFICACION", "FINALIZADO"), p.`fecha_inicio` DESC';

        return self::filas($sql, $params);
    }

    public static function detalle(int $id): ?array
    {
        $periodo = self::fila(
            'SELECT p.*,
               DATEDIFF(p.`fecha_inicio`, CURDATE()) AS `dias_para_iniciar`,
               DATEDIFF(p.`fecha_fin`, CURDATE())    AS `dias_para_terminar`,
               u.`nombre_completo` AS `creado_por_nombre`
             FROM `periodos` p
             LEFT JOIN `usuarios` u ON u.`usuario_id` = p.`creado_por`
             WHERE p.`periodo_id` = ?',
            [$id]
        );

        if (!$periodo) {
            return null;
        }

        $periodo['modulos']  = self::modulos($id);
        $periodo['permisos'] = self::permisos($periodo);

        return $periodo;
    }

    public static function modulos(int $periodoId): array
    {
        return self::filas(
            'SELECT * FROM `periodo_modulos` WHERE `periodo_id` = ? ORDER BY `numero`',
            [$periodoId]
        );
    }

    /**
     * Que se puede hacer segun el estado. El frontend usa esto para
     * deshabilitar botones, y la API lo vuelve a validar en el servidor.
     */
    public static function permisos(array $periodo): array
    {
        $estado = $periodo['estado'];

        return [
            'editar_datos'        => $estado !== 'FINALIZADO',
            'editar_estructura'   => $estado === 'PLANIFICACION',
            'generar_horarios'    => $estado === 'PLANIFICACION',
            'reasignar_docente'   => $estado !== 'FINALIZADO',
            'extender_fechas'     => $estado !== 'FINALIZADO',
            'inscribir_estudiantes' => $estado !== 'FINALIZADO',
            'estudiante_arma_horario' => $estado === 'PLANIFICACION',
            'eliminar'            => $estado === 'PLANIFICACION',
        ];
    }

    public static function porCodigo(string $codigo): ?array
    {
        return self::fila('SELECT * FROM `periodos` WHERE `codigo` = ?', [$codigo]);
    }

    /** Sincroniza el estado con las fechas reales (lo llama el dashboard). */
    public static function refrescarEstados(): int
    {
        $cambios = self::ejecutar(
            'UPDATE `periodos`
                SET `estado` = "EN_CURSO"
              WHERE `estado` = "PLANIFICACION" AND CURDATE() >= `fecha_inicio` AND CURDATE() <= `fecha_fin`'
        )->rowCount();

        $cambios += self::ejecutar(
            'UPDATE `periodos`
                SET `estado` = "FINALIZADO", `inscripcion_abierta` = 0
              WHERE `estado` <> "FINALIZADO" AND CURDATE() > `fecha_fin`'
        )->rowCount();

        return $cambios;
    }

    /* ---- Secciones ------------------------------------------------ */

    public static function secciones(int $periodoId, array $filtros = []): array
    {
        $where  = ['s.`periodo_id` = ?'];
        $params = [$periodoId];

        if (!empty($filtros['carrera_id'])) {
            $where[] = 's.`carrera_id` = ?';
            $params[] = (int) $filtros['carrera_id'];
        }
        if (!empty($filtros['semestre'])) {
            $where[] = 's.`semestre` = ?';
            $params[] = (int) $filtros['semestre'];
        }

        return self::filas(
            'SELECT s.*, c.`nombre` AS `carrera`, c.`codigo` AS `carrera_codigo`, c.`color` AS `carrera_color`,
                    e.`codigo` AS `espacio`, e.`nombre` AS `espacio_nombre`,
                    p.`modalidad`, p.`codigo` AS `periodo_codigo`,
                    (SELECT COUNT(*) FROM `estudiante_inscripciones` i
                      WHERE i.`seccion_id` = s.`seccion_id` AND i.`estado` = "INSCRITO") AS `inscritos`,
                    (SELECT COUNT(*) FROM `asignaciones` a WHERE a.`seccion_id` = s.`seccion_id`) AS `materias_asignadas`
             FROM `secciones` s
             JOIN `carreras` c ON c.`carrera_id` = s.`carrera_id`
             JOIN `periodos` p ON p.`periodo_id` = s.`periodo_id`
             LEFT JOIN `espacios` e ON e.`espacio_id` = s.`espacio_id`
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY s.`semestre`, c.`codigo`, s.`codigo`',
            $params
        );
    }

    public static function seccion(int $seccionId): ?array
    {
        return self::fila(
            'SELECT s.*, c.`nombre` AS `carrera`, c.`codigo` AS `carrera_codigo`, c.`color` AS `carrera_color`,
                    e.`codigo` AS `espacio`, p.`modalidad`, p.`codigo` AS `periodo_codigo`, p.`estado` AS `periodo_estado`
             FROM `secciones` s
             JOIN `carreras` c ON c.`carrera_id` = s.`carrera_id`
             JOIN `periodos` p ON p.`periodo_id` = s.`periodo_id`
             LEFT JOIN `espacios` e ON e.`espacio_id` = s.`espacio_id`
             WHERE s.`seccion_id` = ?',
            [$seccionId]
        );
    }
}
