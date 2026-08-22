<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Modelo;

/**
 * Salones y laboratorios.
 *
 * Comparten la tabla `espacios` (es la que referencian los horarios, de
 * modo que la integridad referencial es real) y cada tipo guarda sus
 * atributos propios en `salones` o `laboratorios`.
 */
class Espacio extends Modelo
{
    protected static string $tabla = 'espacios';
    protected static string $llave = 'espacio_id';
    protected static array $ordenables = ['codigo', 'nombre', 'capacidad', 'edificio'];

    public static function listar(string $tipo, array $filtros, int $limite, int $desfase, string $orden): array
    {
        [$where, $params] = self::filtros($tipo, $filtros);
        $join = $tipo === 'LABORATORIO'
            ? 'JOIN `laboratorios` d ON d.`espacio_id` = e.`espacio_id`'
            : 'JOIN `salones` d ON d.`espacio_id` = e.`espacio_id`';

        return self::filas(
            "SELECT e.*, d.*,
                    (SELECT COUNT(*) FROM `horario_bloques` hb
                       JOIN `periodos` p ON p.`periodo_id` = hb.`periodo_id` AND p.`estado` <> 'FINALIZADO'
                      WHERE hb.`espacio_id` = e.`espacio_id`) AS `bloques_ocupados`
             FROM `espacios` e
             {$join}
             WHERE {$where}
             ORDER BY {$orden}
             LIMIT {$limite} OFFSET {$desfase}",
            $params
        );
    }

    public static function contarFiltrado(string $tipo, array $filtros): int
    {
        [$where, $params] = self::filtros($tipo, $filtros);
        return (int) self::valor("SELECT COUNT(*) FROM `espacios` e WHERE {$where}", $params);
    }

    private static function filtros(string $tipo, array $f): array
    {
        $where  = ['e.`tipo` = ?'];
        $params = [$tipo];

        if (isset($f['activo'])) {
            $where[] = 'e.`activo` = ?';
            $params[] = (int) $f['activo'];
        }
        if (!empty($f['edificio'])) {
            $where[] = 'e.`edificio` = ?';
            $params[] = $f['edificio'];
        }
        if (!empty($f['buscar'])) {
            $where[] = '(e.`codigo` LIKE ? OR e.`nombre` LIKE ?)';
            $b = '%' . $f['buscar'] . '%';
            array_push($params, $b, $b);
        }

        return [implode(' AND ', $where), $params];
    }

    public static function detalle(int $id): ?array
    {
        $espacio = self::buscar($id);
        if (!$espacio) {
            return null;
        }

        $extra = $espacio['tipo'] === 'LABORATORIO'
            ? self::buscar($id, 'laboratorios', 'espacio_id')
            : self::buscar($id, 'salones', 'espacio_id');

        return array_merge($espacio, $extra ?? []);
    }

    public static function crear(string $tipo, array $comun, array $especifico): int
    {
        return Database::transaccion(static function () use ($tipo, $comun, $especifico): int {
            $comun['tipo'] = $tipo;
            $id = self::insertar($comun, 'espacios');

            $especifico['espacio_id'] = $id;
            self::insertar($especifico, $tipo === 'LABORATORIO' ? 'laboratorios' : 'salones');

            return $id;
        });
    }

    public static function actualizarCompleto(int $id, string $tipo, array $comun, array $especifico): void
    {
        Database::transaccion(static function () use ($id, $tipo, $comun, $especifico): void {
            if ($comun) {
                self::actualizar($id, $comun, 'espacios', 'espacio_id');
            }
            if ($especifico) {
                self::actualizar($id, $especifico, $tipo === 'LABORATORIO' ? 'laboratorios' : 'salones', 'espacio_id');
            }
        });
    }

    /** Espacios libres para un bloque concreto (lo usa el generador). */
    public static function disponibles(string $tipo, int $periodoId, int $modulo, string $dia, array $bloqueIds): array
    {
        if (!$bloqueIds) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($bloqueIds), '?'));

        return self::filas(
            "SELECT e.`espacio_id`, e.`codigo`, e.`capacidad`
             FROM `espacios` e
             WHERE e.`tipo` = ? AND e.`activo` = 1
               AND NOT EXISTS (
                   SELECT 1 FROM `horario_bloques` hb
                    WHERE hb.`espacio_id` = e.`espacio_id`
                      AND hb.`periodo_id` = ? AND hb.`modulo` = ? AND hb.`dia` = ?
                      AND hb.`bloque_id` IN ({$marcas}))
             ORDER BY e.`capacidad`, e.`codigo`",
            array_merge([$tipo, $periodoId, $modulo, $dia], $bloqueIds)
        );
    }

    public static function tipoDe(int $espacioId): ?string
    {
        $tipo = self::valor('SELECT `tipo` FROM `espacios` WHERE `espacio_id` = ?', [$espacioId]);
        return $tipo === null ? null : (string) $tipo;
    }
}
