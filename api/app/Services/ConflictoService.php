<?php

namespace App\Services;

use App\Core\ApiException;
use App\Core\Database;
use App\Core\Modelo;
use App\Models\Asignacion;
use App\Models\Conflicto;

/**
 * Resolucion manual de conflictos.
 *
 * El administrador elige una de cuatro salidas y el sistema aplica el
 * cambio correspondiente sobre la materia afectada:
 *
 *   REASIGNAR_DOCENTE  -> otro docente toma la materia, mismo bloque
 *   REGENERAR_HORARIO  -> se vuelve a resolver solo esa seccion
 *   DEJAR_SIN_DOCENTE  -> la materia queda en la parrilla sin docente
 *   NUEVO_BLOQUE       -> se mueve la materia a otro bloque horario
 */
final class ConflictoService
{
    public const OPCIONES = [
        'REASIGNAR_DOCENTE',
        'REGENERAR_HORARIO',
        'DEJAR_SIN_DOCENTE',
        'NUEVO_BLOQUE',
        'VIRTUALIZAR',
    ];

    public static function resolver(int $conflictoId, string $opcion, array $datos, ?int $usuarioId): array
    {
        if (!in_array($opcion, self::OPCIONES, true)) {
            throw ApiException::validacion(['resolucion' => 'Opcion no valida.']);
        }

        $conflicto = Conflicto::buscarOFallar($conflictoId, 'Conflicto');

        if ($conflicto['estado'] !== 'PENDIENTE') {
            throw ApiException::conflicto('Ese conflicto ya fue atendido.');
        }

        $resultado = Database::transaccion(static function () use ($conflicto, $opcion, $datos): array {
            return match ($opcion) {
                'REASIGNAR_DOCENTE' => self::reasignarDocente($conflicto, $datos),
                'REGENERAR_HORARIO' => self::regenerar($conflicto),
                'DEJAR_SIN_DOCENTE' => self::dejarSinDocente($conflicto),
                'NUEVO_BLOQUE'      => self::nuevoBloque($conflicto, $datos),
                'VIRTUALIZAR'       => self::virtualizar($conflicto),
            };
        });

        Conflicto::resolver($conflictoId, $opcion, $usuarioId, $resultado['nota'] ?? '');

        NotificacionService::aRol(
            'ADMIN',
            'Conflicto resuelto',
            ($conflicto['titulo'] ?? 'Conflicto') . ' - ' . ($resultado['nota'] ?? $opcion),
            'CONFLICTO',
            'EXITO',
            '/conflictos'
        );

        WebhookService::emitir('conflicto.resuelto', [
            'conflicto_id' => $conflictoId,
            'periodo_id'   => (int) $conflicto['periodo_id'],
            'resolucion'   => $opcion,
            'nota'         => $resultado['nota'] ?? '',
        ]);

        return $resultado;
    }

    /* ---------------------------------------------------------------- */

    private static function reasignarDocente(array $conflicto, array $datos): array
    {
        $asignacionId = (int) ($conflicto['asignacion_id'] ?? 0);
        $profesorId   = (int) ($datos['profesor_id'] ?? 0);

        if ($asignacionId <= 0) {
            throw ApiException::validacion(['asignacion_id' => 'El conflicto no apunta a ninguna materia.']);
        }
        if ($profesorId <= 0) {
            throw ApiException::validacion(['profesor_id' => 'Indica que docente tomara la materia.']);
        }

        $candidatos = array_column(Asignacion::candidatos($asignacionId), null, 'profesor_id');
        if (!isset($candidatos[$profesorId])) {
            throw ApiException::validacion([
                'profesor_id' => 'Ese docente no esta habilitado para la materia. Revisa sus habilidades.',
            ]);
        }
        if (empty($candidatos[$profesorId]['libre'])) {
            throw ApiException::conflicto('Ese docente ya tiene clase en alguno de esos bloques.');
        }

        Asignacion::reasignarDocente($asignacionId, $profesorId);

        return [
            'accion' => 'REASIGNAR_DOCENTE',
            'nota'   => 'Materia reasignada a ' . $candidatos[$profesorId]['profesor'],
        ];
    }

    private static function dejarSinDocente(array $conflicto): array
    {
        $asignacionId = (int) ($conflicto['asignacion_id'] ?? 0);
        if ($asignacionId <= 0) {
            throw ApiException::validacion(['asignacion_id' => 'El conflicto no apunta a ninguna materia.']);
        }

        Asignacion::reasignarDocente($asignacionId, null);

        return [
            'accion' => 'DEJAR_SIN_DOCENTE',
            'nota'   => 'La materia queda en la parrilla sin docente asignado.',
        ];
    }

    private static function regenerar(array $conflicto): array
    {
        $seccionId = $conflicto['seccion_id'] !== null ? (int) $conflicto['seccion_id'] : null;

        $generador = new GeneradorHorarios((int) $conflicto['periodo_id']);
        $resultado = $generador->generar([
            'limpiar'            => true,
            'reasignar_docentes' => true,
            'seccion_id'         => $seccionId,
        ]);

        return [
            'accion'  => 'REGENERAR_HORARIO',
            'nota'    => $seccionId
                ? 'Se regenero el horario de la seccion afectada.'
                : 'Se regenero el horario completo del periodo.',
            'resumen' => $resultado['resumen'],
        ];
    }

    private static function nuevoBloque(array $conflicto, array $datos): array
    {
        $asignacionId = (int) ($conflicto['asignacion_id'] ?? 0);
        $dia          = strtoupper((string) ($datos['dia'] ?? ''));
        $bloqueIds    = array_map('intval', (array) ($datos['bloques'] ?? []));

        if ($asignacionId <= 0) {
            throw ApiException::validacion(['asignacion_id' => 'El conflicto no apunta a ninguna materia.']);
        }
        if (!in_array($dia, ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'SABADO'], true)) {
            throw ApiException::validacion(['dia' => 'Dia no valido (viernes y domingo estan bloqueados).']);
        }
        if (!$bloqueIds) {
            throw ApiException::validacion(['bloques' => 'Selecciona al menos un bloque.']);
        }

        $asignacion = Modelo::fila(
            'SELECT a.*, m.`es_electiva`, m.`requiere_laboratorio`, s.`espacio_id` AS `espacio_base`
             FROM `asignaciones` a
             JOIN `materias` m ON m.`materia_id` = a.`materia_id`
             JOIN `secciones` s ON s.`seccion_id` = a.`seccion_id`
             WHERE a.`asignacion_id` = ?',
            [$asignacionId]
        );

        if (!$asignacion) {
            throw ApiException::noEncontrado('Asignacion');
        }

        $espacioId = isset($datos['espacio_id']) && $datos['espacio_id'] !== null
            ? (int) $datos['espacio_id']
            : self::buscarEspacio($asignacion, $dia, $bloqueIds);

        Asignacion::limpiarBloques($asignacionId);

        foreach ($bloqueIds as $bloqueId) {
            Asignacion::insertarBloque([
                'asignacion_id' => $asignacionId,
                'periodo_id'    => (int) $asignacion['periodo_id'],
                'modulo'        => (int) $asignacion['modulo'],
                'dia'           => $dia,
                'bloque_id'     => $bloqueId,
                'seccion_id'    => (int) $asignacion['seccion_id'],
                'slot_seccion'  => (int) $asignacion['es_electiva'] === 1 ? null : (int) $asignacion['seccion_id'],
                'profesor_id'   => $asignacion['profesor_id'] !== null ? (int) $asignacion['profesor_id'] : null,
                'espacio_id'    => $espacioId,
            ]);
        }

        Modelo::ejecutar(
            'UPDATE `asignaciones`
                SET `espacio_id` = ?, `estado` = IF(`profesor_id` IS NULL, "SIN_DOCENTE", "CONFIRMADA")
              WHERE `asignacion_id` = ?',
            [$espacioId, $asignacionId]
        );

        return [
            'accion' => 'NUEVO_BLOQUE',
            'nota'   => 'La materia se movio al ' . ucfirst(strtolower($dia)) . ' (' . count($bloqueIds) . ' bloque(s)).',
        ];
    }

    private static function virtualizar(array $conflicto): array
    {
        $asignacionId = (int) ($conflicto['asignacion_id'] ?? 0);
        if ($asignacionId <= 0) {
            throw ApiException::validacion(['asignacion_id' => 'El conflicto no apunta a ninguna materia.']);
        }

        Modelo::ejecutar(
            'UPDATE `asignaciones` SET `modalidad_clase` = "VIRTUAL", `espacio_id` = NULL WHERE `asignacion_id` = ?',
            [$asignacionId]
        );
        Modelo::ejecutar(
            'UPDATE `horario_bloques` SET `espacio_id` = NULL WHERE `asignacion_id` = ?',
            [$asignacionId]
        );

        return ['accion' => 'VIRTUALIZAR', 'nota' => 'La materia pasa a dictarse de forma virtual.'];
    }

    private static function buscarEspacio(array $asignacion, string $dia, array $bloqueIds): ?int
    {
        $tipo = (int) $asignacion['requiere_laboratorio'] === 1 ? 'LABORATORIO' : 'SALON';
        $marcas = implode(',', array_fill(0, count($bloqueIds), '?'));

        $libre = Modelo::valor(
            "SELECT e.`espacio_id`
             FROM `espacios` e
             WHERE e.`tipo` = ? AND e.`activo` = 1
               AND NOT EXISTS (
                   SELECT 1 FROM `horario_bloques` hb
                    WHERE hb.`espacio_id` = e.`espacio_id`
                      AND hb.`periodo_id` = ? AND hb.`modulo` = ? AND hb.`dia` = ?
                      AND hb.`bloque_id` IN ({$marcas})
                      AND hb.`asignacion_id` <> ?)
             ORDER BY (e.`espacio_id` = ?) DESC, e.`capacidad`
             LIMIT 1",
            array_merge(
                [$tipo, (int) $asignacion['periodo_id'], (int) $asignacion['modulo'], $dia],
                $bloqueIds,
                [(int) $asignacion['asignacion_id'], (int) ($asignacion['espacio_base'] ?? 0)]
            )
        );

        return $libre !== null ? (int) $libre : null;
    }
}
