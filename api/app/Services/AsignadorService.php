<?php

namespace App\Services;

use App\Core\Modelo;

/**
 * Emparejamiento materia <-> docente por habilidades.
 *
 * La afinidad es el porcentaje de estrellas que aporta el docente sobre
 * el maximo exigible, ponderado por la importancia de cada habilidad:
 *
 *     afinidad = SUM(peso_i * estrellas_docente_i) / SUM(peso_i * 5) * 100
 *
 * Si al docente le falta una habilidad obligatoria (esta por debajo del
 * minimo) la materia se marca `cumple = false`: puede sugerirse igual,
 * pero se muestra en amarillo para que el administrador decida.
 */
final class AsignadorService
{
    /** Materias sugeridas para un docente, ordenadas por afinidad. */
    public static function sugerirMaterias(int $profesorId, bool $soloQueCumplen = false): array
    {
        $skills = self::skillsDe($profesorId);

        $requisitos = Modelo::filas(
            'SELECT mh.`materia_id`, mh.`habilidad_id`, mh.`estrellas_minimas`, mh.`peso`,
                    h.`nombre` AS `habilidad`
             FROM `materia_habilidades` mh
             JOIN `habilidades` h ON h.`habilidad_id` = mh.`habilidad_id`'
        );

        $porMateria = [];
        foreach ($requisitos as $r) {
            $porMateria[(int) $r['materia_id']][] = $r;
        }

        $materias = Modelo::filas(
            'SELECT m.`materia_id`, m.`codigo`, m.`nombre`, m.`semestre`, m.`unidades_credito`,
                    m.`requiere_laboratorio`, m.`es_electiva`,
                    c.`codigo` AS `carrera_codigo`, c.`nombre` AS `carrera`, c.`color` AS `carrera_color`
             FROM `materias` m
             JOIN `carreras` c ON c.`carrera_id` = m.`carrera_id`
             WHERE m.`activo` = 1'
        );

        $yaConfirmadas = array_flip(Modelo::columna(
            'SELECT `materia_id` FROM `profesor_materias` WHERE `profesor_id` = ? AND `confirmado` = 1',
            [$profesorId]
        ));

        $salida = [];

        foreach ($materias as $materia) {
            $materiaId = (int) $materia['materia_id'];
            $reqs = $porMateria[$materiaId] ?? [];

            if (!$reqs) {
                continue; // materia sin perfil definido: no se sugiere sola
            }

            $pesoTotal = 0;
            $obtenido  = 0;
            $cumple    = true;
            $faltantes = [];

            foreach ($reqs as $r) {
                $peso      = (int) $r['peso'];
                $minimo    = (int) $r['estrellas_minimas'];
                $estrellas = $skills[(int) $r['habilidad_id']] ?? 0;

                $pesoTotal += $peso * 5;
                $obtenido  += $peso * $estrellas;

                if ($estrellas < $minimo) {
                    $cumple = false;
                    $faltantes[] = [
                        'habilidad'  => $r['habilidad'],
                        'requerido'  => $minimo,
                        'tiene'      => $estrellas,
                    ];
                }
            }

            $afinidad = $pesoTotal > 0 ? round($obtenido / $pesoTotal * 100, 2) : 0.0;

            if ($afinidad <= 0) {
                continue;
            }
            if ($soloQueCumplen && !$cumple) {
                continue;
            }

            $salida[] = array_merge($materia, [
                'afinidad'   => $afinidad,
                'cumple'     => $cumple,
                'faltantes'  => $faltantes,
                'sugerida'   => $cumple && $afinidad >= 60,
                'confirmada' => isset($yaConfirmadas[$materiaId]),
            ]);
        }

        usort($salida, static fn ($a, $b) => [$b['cumple'], $b['afinidad']] <=> [$a['cumple'], $a['afinidad']]);

        return $salida;
    }

    /** Docentes sugeridos para una materia, ordenados por afinidad. */
    public static function sugerirDocentes(int $materiaId): array
    {
        $reqs = Modelo::filas(
            'SELECT mh.`habilidad_id`, mh.`estrellas_minimas`, mh.`peso`, h.`nombre` AS `habilidad`
             FROM `materia_habilidades` mh
             JOIN `habilidades` h ON h.`habilidad_id` = mh.`habilidad_id`
             WHERE mh.`materia_id` = ?',
            [$materiaId]
        );

        if (!$reqs) {
            return [];
        }

        $profesores = Modelo::filas(
            'SELECT p.`profesor_id`, CONCAT(p.`nombres`," ",p.`apellidos`) AS `profesor`,
                    p.`titulo`, p.`tipo_contrato`, p.`telefono`
             FROM `profesores` p WHERE p.`activo` = 1'
        );

        $skills = [];
        foreach (Modelo::filas('SELECT `profesor_id`,`habilidad_id`,`estrellas` FROM `profesor_habilidades`') as $s) {
            $skills[(int) $s['profesor_id']][(int) $s['habilidad_id']] = (int) $s['estrellas'];
        }

        $salida = [];

        foreach ($profesores as $p) {
            $pid = (int) $p['profesor_id'];
            $pesoTotal = 0;
            $obtenido  = 0;
            $cumple    = true;

            foreach ($reqs as $r) {
                $peso      = (int) $r['peso'];
                $estrellas = $skills[$pid][(int) $r['habilidad_id']] ?? 0;
                $pesoTotal += $peso * 5;
                $obtenido  += $peso * $estrellas;
                if ($estrellas < (int) $r['estrellas_minimas']) {
                    $cumple = false;
                }
            }

            $afinidad = $pesoTotal > 0 ? round($obtenido / $pesoTotal * 100, 2) : 0.0;
            if ($afinidad <= 0) {
                continue;
            }

            $salida[] = array_merge($p, ['afinidad' => $afinidad, 'cumple' => $cumple]);
        }

        usort($salida, static fn ($a, $b) => [$b['cumple'], $b['afinidad']] <=> [$a['cumple'], $a['afinidad']]);

        return $salida;
    }

    /** Guarda la seleccion del paso 4 del formulario de docente. */
    public static function confirmarMaterias(int $profesorId, array $materiaIds): int
    {
        $sugerencias = [];
        foreach (self::sugerirMaterias($profesorId) as $s) {
            $sugerencias[(int) $s['materia_id']] = (float) $s['afinidad'];
        }

        Modelo::ejecutar('DELETE FROM `profesor_materias` WHERE `profesor_id` = ?', [$profesorId]);

        $guardadas = 0;
        foreach (array_unique(array_map('intval', $materiaIds)) as $materiaId) {
            if ($materiaId <= 0) {
                continue;
            }
            Modelo::insertar([
                'profesor_id' => $profesorId,
                'materia_id'  => $materiaId,
                'afinidad'    => $sugerencias[$materiaId] ?? 0,
                'origen'      => isset($sugerencias[$materiaId]) ? 'AUTO' : 'MANUAL',
                'confirmado'  => 1,
            ], 'profesor_materias');
            $guardadas++;
        }

        return $guardadas;
    }

    private static function skillsDe(int $profesorId): array
    {
        $filas = Modelo::filas(
            'SELECT `habilidad_id`,`estrellas` FROM `profesor_habilidades` WHERE `profesor_id` = ?',
            [$profesorId]
        );

        $skills = [];
        foreach ($filas as $f) {
            $skills[(int) $f['habilidad_id']] = (int) $f['estrellas'];
        }
        return $skills;
    }
}
