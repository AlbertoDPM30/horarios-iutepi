<?php

namespace App\Models;

use App\Core\Modelo;

class Estudiante extends Modelo
{
    protected static string $tabla = 'estudiantes';
    protected static string $llave = 'estudiante_id';
    protected static array $ordenables = ['apellidos', 'nombres', 'codigo', 'semestre_actual', 'creado_en'];

    public static function listar(array $filtros, int $limite, int $desfase, string $orden): array
    {
        [$where, $params] = self::filtros($filtros);

        return self::filas(
            "SELECT e.*,
                    CONCAT(e.`nombres`,' ',e.`apellidos`) AS `nombre_completo`,
                    c.`nombre` AS `carrera`, c.`codigo` AS `carrera_codigo`, c.`color` AS `carrera_color`,
                    i.`inscripcion_id`, i.`horario_confirmado`, i.`periodo_id`,
                    s.`seccion_id`, s.`codigo` AS `seccion`
             FROM `estudiantes` e
             JOIN `carreras` c ON c.`carrera_id` = e.`carrera_id`
             LEFT JOIN `estudiante_inscripciones` i
                    ON i.`inscripcion_id` = (SELECT i2.`inscripcion_id`
                                               FROM `estudiante_inscripciones` i2
                                               JOIN `periodos` p2 ON p2.`periodo_id` = i2.`periodo_id`
                                              WHERE i2.`estudiante_id` = e.`estudiante_id` AND i2.`estado` = 'INSCRITO'
                                              ORDER BY p2.`fecha_inicio` DESC LIMIT 1)
             LEFT JOIN `secciones` s ON s.`seccion_id` = i.`seccion_id`
             WHERE {$where}
             ORDER BY {$orden}
             LIMIT {$limite} OFFSET {$desfase}",
            $params
        );
    }

    public static function contarFiltrado(array $filtros): int
    {
        [$where, $params] = self::filtros($filtros, true);
        return (int) self::valor(
            "SELECT COUNT(*) FROM `estudiantes` e
             LEFT JOIN `estudiante_inscripciones` i
                    ON i.`inscripcion_id` = (SELECT i2.`inscripcion_id`
                                               FROM `estudiante_inscripciones` i2
                                               JOIN `periodos` p2 ON p2.`periodo_id` = i2.`periodo_id`
                                              WHERE i2.`estudiante_id` = e.`estudiante_id` AND i2.`estado` = 'INSCRITO'
                                              ORDER BY p2.`fecha_inicio` DESC LIMIT 1)
             LEFT JOIN `secciones` s ON s.`seccion_id` = i.`seccion_id`
             WHERE {$where}",
            $params
        );
    }

    private static function filtros(array $f): array
    {
        $where  = ['1 = 1'];
        $params = [];

        if (!empty($f['estado'])) {
            $where[] = 'e.`estado` = ?';
            $params[] = $f['estado'];
        }
        if (!empty($f['carrera_id'])) {
            $where[] = 'e.`carrera_id` = ?';
            $params[] = (int) $f['carrera_id'];
        }
        if (!empty($f['semestre'])) {
            $where[] = 'e.`semestre_actual` = ?';
            $params[] = (int) $f['semestre'];
        }
        if (!empty($f['modalidad'])) {
            $where[] = 'e.`modalidad` = ?';
            $params[] = $f['modalidad'];
        }
        if (!empty($f['seccion_id'])) {
            $where[] = 'i.`seccion_id` = ?';
            $params[] = (int) $f['seccion_id'];
        }
        if (!empty($f['periodo_id'])) {
            $where[] = 'i.`periodo_id` = ?';
            $params[] = (int) $f['periodo_id'];
        }
        if (!empty($f['buscar'])) {
            $where[] = "(CONCAT(e.`nombres`,' ',e.`apellidos`) LIKE ? OR e.`codigo` LIKE ? OR e.`cedula` LIKE ?)";
            $b = '%' . $f['buscar'] . '%';
            array_push($params, $b, $b, $b);
        }

        return [implode(' AND ', $where), $params];
    }

    public static function detalle(int $id): ?array
    {
        $estudiante = self::fila(
            "SELECT e.*, CONCAT(e.`nombres`,' ',e.`apellidos`) AS `nombre_completo`,
                    c.`nombre` AS `carrera`, c.`codigo` AS `carrera_codigo`, c.`color` AS `carrera_color`
             FROM `estudiantes` e
             JOIN `carreras` c ON c.`carrera_id` = e.`carrera_id`
             WHERE e.`estudiante_id` = ?",
            [$id]
        );

        if (!$estudiante) {
            return null;
        }

        $estudiante['inscripciones'] = self::inscripciones($id);
        $estudiante['seccion'] = $estudiante['inscripciones'][0]['seccion'] ?? null;

        return $estudiante;
    }

    public static function inscripciones(int $estudianteId): array
    {
        return self::filas(
            'SELECT i.*, s.`codigo` AS `seccion`, s.`semestre` AS `seccion_semestre`,
                    p.`codigo` AS `periodo`, p.`nombre` AS `periodo_nombre`, p.`estado` AS `periodo_estado`,
                    p.`modalidad`, p.`fecha_inicio`, p.`fecha_fin`,
                    (SELECT COUNT(*) FROM `estudiante_horario` eh WHERE eh.`inscripcion_id` = i.`inscripcion_id`) AS `materias_elegidas`
             FROM `estudiante_inscripciones` i
             JOIN `secciones` s ON s.`seccion_id` = i.`seccion_id`
             JOIN `periodos` p ON p.`periodo_id` = i.`periodo_id`
             WHERE i.`estudiante_id` = ?
             ORDER BY p.`fecha_inicio` DESC',
            [$estudianteId]
        );
    }

    public static function porUsuario(int $usuarioId): ?array
    {
        return self::fila('SELECT * FROM `estudiantes` WHERE `usuario_id` = ?', [$usuarioId]);
    }

    public static function porCodigo(string $codigo): ?array
    {
        return self::fila('SELECT * FROM `estudiantes` WHERE `codigo` = ?', [$codigo]);
    }

    public static function inscripcion(int $estudianteId, int $periodoId): ?array
    {
        return self::fila(
            'SELECT i.*, s.`codigo` AS `seccion`, s.`carrera_id`, s.`semestre` AS `seccion_semestre`,
                    p.`estado` AS `periodo_estado`, p.`modalidad`
             FROM `estudiante_inscripciones` i
             JOIN `secciones` s ON s.`seccion_id` = i.`seccion_id`
             JOIN `periodos` p ON p.`periodo_id` = i.`periodo_id`
             WHERE i.`estudiante_id` = ? AND i.`periodo_id` = ?',
            [$estudianteId, $periodoId]
        );
    }

    /**
     * Codigo de estudiante: seis digitos `AARRNN`.
     *
     *   AA  anio de ingreso     26 = 2026
     *   RR  referencia (lote)   pasa de 42 a 43 cuando NN supera 99
     *   NN  correlativo         01 .. 99
     *
     * `RR` y `NN` son en realidad un contador de cuatro digitos dentro del
     * anio, asi que el acarreo de NN sobre RR sale solo al sumar uno.
     *
     * Lo escribe el administrador a mano al inscribir; esto solo calcula
     * la sugerencia que aparece precargada en el formulario.
     */
    public static function siguienteCodigo(int $anio): string
    {
        $prefijo = substr((string) $anio, 2);

        $ultimo = (string) self::valor(
            'SELECT `codigo` FROM `estudiantes`
              WHERE `codigo` LIKE ? AND CHAR_LENGTH(`codigo`) = 6
              ORDER BY `codigo` DESC LIMIT 1',
            [$prefijo . '%']
        );

        if ($ultimo === '') {
            // Primer estudiante del anio: se arranca en la referencia base
            $correlativo = (int) \App\Models\Catalogo::config('estudiante.referencia_base', 1);
        } else {
            $correlativo = ((int) substr($ultimo, 2)) + 1;
        }

        if ($correlativo > 9999) {
            throw new \App\Core\ApiException(
                "Se agotaron los codigos del anio {$anio} (llego a 9999).",
                409,
                'CODIGOS_AGOTADOS'
            );
        }

        return $prefijo . str_pad((string) $correlativo, 4, '0', STR_PAD_LEFT);
    }

    /** Valida la forma del codigo y que el anio sea razonable. */
    public static function codigoValido(string $codigo): bool
    {
        if (!preg_match('/^\d{6}$/', $codigo)) {
            return false;
        }

        $anio = 2000 + (int) substr($codigo, 0, 2);

        return $anio >= 2000 && $anio <= (int) date('Y') + 1;
    }
}
