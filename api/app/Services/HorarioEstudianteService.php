<?php

namespace App\Services;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\Modelo;
use App\Models\Asignacion;
use App\Models\Estudiante;

/**
 * El estudiante arma su propio horario.
 *
 * Como las materias de distintos semestres se cruzan a menudo (un
 * repitiente cursa 3ro y 4to a la vez), el servicio detecta el choque y
 * propone la salida que usa el instituto: dejar una de las materias en
 * modalidad VIRTUAL. Solo se bloquea si el choque no tiene salida.
 */
final class HorarioEstudianteService
{
    /** Oferta del periodo marcada con el estado de cada materia para este alumno. */
    public static function oferta(int $estudianteId, int $periodoId): array
    {
        $inscripcion = self::inscripcionValida($estudianteId, $periodoId);
        $estudiante  = Estudiante::buscarOFallar($estudianteId, 'Estudiante');

        $oferta = Asignacion::ofertaParaEstudiante(
            $periodoId,
            (int) $estudiante['carrera_id'],
            (int) $inscripcion['semestre']
        );

        $elegidas = [];
        foreach (Modelo::filas(
            'SELECT `asignacion_id`,`modalidad_cursada` FROM `estudiante_horario` WHERE `inscripcion_id` = ?',
            [(int) $inscripcion['inscripcion_id']]
        ) as $e) {
            $elegidas[(int) $e['asignacion_id']] = $e['modalidad_cursada'];
        }

        $bloquesPorAsignacion = self::bloquesPorAsignacion(array_column($oferta, 'asignacion_id'));
        $ocupacion = self::ocupacionActual($bloquesPorAsignacion, array_keys($elegidas));

        foreach ($oferta as &$item) {
            $id = (int) $item['asignacion_id'];
            $item['elegida']           = isset($elegidas[$id]);
            $item['modalidad_cursada'] = $elegidas[$id] ?? null;
            $item['bloques_detalle']   = $bloquesPorAsignacion[$id] ?? [];
            $item['choques']           = $item['elegida']
                ? []
                : self::detectarChoques($id, $bloquesPorAsignacion, $ocupacion);
        }

        return [
            'inscripcion' => $inscripcion,
            'oferta'      => $oferta,
        ];
    }

    /**
     * Agrega una materia. Si choca, devuelve 409 con la sugerencia de
     * virtualizar, salvo que el estudiante ya haya aceptado (`virtual`).
     */
    public static function agregar(int $estudianteId, int $periodoId, int $asignacionId, bool $virtual = false): array
    {
        $inscripcion = self::inscripcionEditable($estudianteId, $periodoId);
        $inscripcionId = (int) $inscripcion['inscripcion_id'];

        $asignacion = Modelo::fila(
            'SELECT a.*, m.`nombre` AS `materia`, m.`codigo` AS `materia_codigo`, s.`codigo` AS `seccion`
             FROM `asignaciones` a
             JOIN `materias` m ON m.`materia_id` = a.`materia_id`
             JOIN `secciones` s ON s.`seccion_id` = a.`seccion_id`
             WHERE a.`asignacion_id` = ? AND a.`periodo_id` = ?',
            [$asignacionId, $periodoId]
        );

        if (!$asignacion) {
            throw ApiException::noEncontrado('Materia de la oferta');
        }

        $yaTiene = Modelo::valor(
            'SELECT 1 FROM `estudiante_horario` eh
             JOIN `asignaciones` a ON a.`asignacion_id` = eh.`asignacion_id`
             WHERE eh.`inscripcion_id` = ? AND a.`materia_id` = ? AND a.`modulo` = ?',
            [$inscripcionId, (int) $asignacion['materia_id'], (int) $asignacion['modulo']]
        );
        if ($yaTiene) {
            throw ApiException::conflicto('Ya tienes esa materia en tu horario para ese modulo.');
        }

        $elegidas = Modelo::columna(
            'SELECT `asignacion_id` FROM `estudiante_horario` WHERE `inscripcion_id` = ?',
            [$inscripcionId]
        );

        $bloques   = self::bloquesPorAsignacion(array_merge([$asignacionId], array_map('intval', $elegidas)));
        $ocupacion = self::ocupacionActual($bloques, array_map('intval', $elegidas));
        $choques   = self::detectarChoques($asignacionId, $bloques, $ocupacion);

        if ($choques && !$virtual) {
            throw ApiException::conflicto(
                sprintf(
                    '%s choca con %s. Puedes cursarla en modalidad virtual para conservar ambas.',
                    $asignacion['materia'],
                    implode(', ', array_column($choques, 'materia'))
                ),
                [
                    'requiere_decision' => true,
                    'sugerencia'        => 'VIRTUALIZAR',
                    'materia'           => $asignacion['materia'],
                    'choques'           => $choques,
                ]
            );
        }

        Modelo::insertar([
            'inscripcion_id'    => $inscripcionId,
            'asignacion_id'     => $asignacionId,
            'modalidad_cursada' => $choques ? 'VIRTUAL' : 'PRESENCIAL',
            'motivo_virtual'    => $choques
                ? 'Cruce con ' . implode(', ', array_column($choques, 'materia'))
                : '',
        ], 'estudiante_horario');

        if ($choques) {
            NotificacionService::conflicto([
                'periodo_id'    => $periodoId,
                'tipo'          => 'ESTUDIANTE_SOLAPADO',
                'severidad'     => 'BAJA',
                'titulo'        => "Cruce resuelto como virtual: {$asignacion['materia']}",
                'descripcion'   => sprintf(
                    'El estudiante %s cursara %s de forma virtual por cruce con %s.',
                    $estudianteId,
                    $asignacion['materia'],
                    implode(', ', array_column($choques, 'materia'))
                ),
                'asignacion_id' => $asignacionId,
                'estudiante_id' => $estudianteId,
                'materia_id'    => (int) $asignacion['materia_id'],
                'contexto'      => ['choques' => $choques],
            ], false);
        }

        return [
            'agregada'          => true,
            'modalidad_cursada' => $choques ? 'VIRTUAL' : 'PRESENCIAL',
            'choques'           => $choques,
        ];
    }

    public static function quitar(int $estudianteId, int $periodoId, int $asignacionId): void
    {
        $inscripcion = self::inscripcionEditable($estudianteId, $periodoId);

        $borradas = Modelo::ejecutar(
            'DELETE FROM `estudiante_horario` WHERE `inscripcion_id` = ? AND `asignacion_id` = ?',
            [(int) $inscripcion['inscripcion_id'], $asignacionId]
        )->rowCount();

        if ($borradas === 0) {
            throw ApiException::noEncontrado('Materia en tu horario');
        }
    }

    public static function cambiarModalidad(int $estudianteId, int $periodoId, int $asignacionId, string $modalidad): void
    {
        $inscripcion = self::inscripcionEditable($estudianteId, $periodoId);

        Modelo::ejecutar(
            'UPDATE `estudiante_horario`
                SET `modalidad_cursada` = ?, `motivo_virtual` = IF(? = "VIRTUAL", `motivo_virtual`, "")
              WHERE `inscripcion_id` = ? AND `asignacion_id` = ?',
            [$modalidad, $modalidad, (int) $inscripcion['inscripcion_id'], $asignacionId]
        );
    }

    /** Cierra el horario: a partir de aqui el estudiante ya no lo toca. */
    public static function confirmar(int $estudianteId, int $periodoId): array
    {
        $inscripcion = self::inscripcionEditable($estudianteId, $periodoId);
        $inscripcionId = (int) $inscripcion['inscripcion_id'];

        $materias = Asignacion::materiasElegidas($inscripcionId);
        if (!$materias) {
            throw new ApiException('Debes elegir al menos una materia antes de confirmar.', 422, 'HORARIO_VACIO');
        }

        Modelo::ejecutar(
            'UPDATE `estudiante_inscripciones`
                SET `horario_confirmado` = 1, `confirmado_en` = NOW()
              WHERE `inscripcion_id` = ?',
            [$inscripcionId]
        );

        return ['confirmado' => true, 'materias' => count($materias)];
    }

    /** Genera automaticamente el horario de un alumno con el plan de su semestre. */
    public static function generarAutomatico(int $estudianteId, int $periodoId): array
    {
        $inscripcion = self::inscripcionValida($estudianteId, $periodoId);
        $estudiante  = Estudiante::buscarOFallar($estudianteId, 'Estudiante');
        $inscripcionId = (int) $inscripcion['inscripcion_id'];

        $oferta = Asignacion::ofertaParaEstudiante(
            $periodoId,
            (int) $estudiante['carrera_id'],
            (int) $inscripcion['semestre']
        );

        // Solo las materias de su propio semestre y de su seccion
        $candidatas = array_values(array_filter(
            $oferta,
            static fn ($o) => (int) $o['semestre'] === (int) $inscripcion['semestre']
                && (int) $o['seccion_id'] === (int) $inscripcion['seccion_id']
        ));

        // De cada grupo de electivas se cursa una sola: se propone la de
        // mayor afinidad (la primera que devuelve la oferta) y el alumno
        // puede cambiarla despues a mano.
        $gruposVistos = [];
        $candidatas = array_values(array_filter($candidatas, static function ($o) use (&$gruposVistos) {
            if ((int) $o['es_electiva'] !== 1) {
                return true;
            }
            $clave = ($o['grupo_electiva'] ?? 'ELECTIVA') . '|' . $o['modulo'];
            if (isset($gruposVistos[$clave])) {
                return false;
            }
            $gruposVistos[$clave] = true;
            return true;
        }));

        return Database::transaccion(static function () use ($candidatas, $inscripcionId, $estudianteId): array {
            Modelo::ejecutar('DELETE FROM `estudiante_horario` WHERE `inscripcion_id` = ?', [$inscripcionId]);

            $agregadas = 0;
            $virtuales = 0;
            $ids = [];

            foreach ($candidatas as $item) {
                $asignacionId = (int) $item['asignacion_id'];
                $bloques   = self::bloquesPorAsignacion(array_merge([$asignacionId], $ids));
                $ocupacion = self::ocupacionActual($bloques, $ids);
                $choques   = self::detectarChoques($asignacionId, $bloques, $ocupacion);

                Modelo::insertar([
                    'inscripcion_id'    => $inscripcionId,
                    'asignacion_id'     => $asignacionId,
                    'modalidad_cursada' => $choques ? 'VIRTUAL' : 'PRESENCIAL',
                    'motivo_virtual'    => $choques ? 'Cruce con ' . implode(', ', array_column($choques, 'materia')) : '',
                ], 'estudiante_horario');

                $ids[] = $asignacionId;
                $agregadas++;
                if ($choques) {
                    $virtuales++;
                }
            }

            return ['agregadas' => $agregadas, 'virtuales' => $virtuales];
        });
    }

    /* =================================================================
       Utilidades
       ================================================================= */

    private static function inscripcionValida(int $estudianteId, int $periodoId): array
    {
        $inscripcion = Estudiante::inscripcion($estudianteId, $periodoId);
        if (!$inscripcion) {
            throw ApiException::noEncontrado('Inscripcion del estudiante en ese periodo');
        }
        return $inscripcion;
    }

    private static function inscripcionEditable(int $estudianteId, int $periodoId): array
    {
        $inscripcion = self::inscripcionValida($estudianteId, $periodoId);

        if ($inscripcion['periodo_estado'] === 'FINALIZADO') {
            throw ApiException::prohibido('El periodo ya finalizo: el horario no se puede modificar.');
        }
        if ($inscripcion['periodo_estado'] === 'EN_CURSO') {
            throw ApiException::prohibido('El periodo ya comenzo: tu horario quedo cerrado.');
        }
        if ((int) $inscripcion['horario_confirmado'] === 1) {
            throw ApiException::prohibido('Ya confirmaste tu horario. Pide a coordinacion que lo reabra si necesitas cambiarlo.');
        }

        return $inscripcion;
    }

    /** @return array<int, array<int, array{dia:string,bloque_id:int,modulo:int}>> */
    private static function bloquesPorAsignacion(array $asignacionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $asignacionIds))));
        if (!$ids) {
            return [];
        }

        $marcas = implode(',', array_fill(0, count($ids), '?'));
        $filas = Modelo::filas(
            "SELECT hb.`asignacion_id`, hb.`dia`, hb.`bloque_id`, hb.`modulo`,
                    b.`etiqueta`, m.`nombre` AS `materia`, m.`codigo` AS `materia_codigo`
             FROM `horario_bloques` hb
             JOIN `bloques_horario` b ON b.`bloque_id` = hb.`bloque_id`
             JOIN `asignaciones` a ON a.`asignacion_id` = hb.`asignacion_id`
             JOIN `materias` m ON m.`materia_id` = a.`materia_id`
             WHERE hb.`asignacion_id` IN ({$marcas})",
            $ids
        );

        $salida = [];
        foreach ($filas as $f) {
            $salida[(int) $f['asignacion_id']][] = [
                'dia'       => $f['dia'],
                'bloque_id' => (int) $f['bloque_id'],
                'modulo'    => (int) $f['modulo'],
                'etiqueta'  => $f['etiqueta'],
                'materia'   => $f['materia'],
                'materia_codigo' => $f['materia_codigo'],
            ];
        }

        return $salida;
    }

    /** Mapa "modulo|dia|bloque" => datos de la materia que ya lo ocupa. */
    private static function ocupacionActual(array $bloquesPorAsignacion, array $elegidas): array
    {
        $ocupacion = [];

        foreach ($elegidas as $asignacionId) {
            foreach ($bloquesPorAsignacion[(int) $asignacionId] ?? [] as $b) {
                $clave = $b['modulo'] . '|' . $b['dia'] . '|' . $b['bloque_id'];
                $ocupacion[$clave] = [
                    'asignacion_id' => (int) $asignacionId,
                    'materia'       => $b['materia'],
                    'materia_codigo'=> $b['materia_codigo'],
                    'etiqueta'      => $b['etiqueta'],
                    'dia'           => $b['dia'],
                    'modulo'        => $b['modulo'],
                ];
            }
        }

        return $ocupacion;
    }

    private static function detectarChoques(int $asignacionId, array $bloquesPorAsignacion, array $ocupacion): array
    {
        $choques = [];

        foreach ($bloquesPorAsignacion[$asignacionId] ?? [] as $b) {
            $clave = $b['modulo'] . '|' . $b['dia'] . '|' . $b['bloque_id'];
            if (isset($ocupacion[$clave])) {
                $otra = $ocupacion[$clave];
                $choques[$otra['asignacion_id']] = $otra;
            }
        }

        return array_values($choques);
    }
}
