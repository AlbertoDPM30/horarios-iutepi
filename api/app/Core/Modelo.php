<?php

namespace App\Core;

use PDO;
use PDOException;

/**
 * Base de todos los modelos: consultas preparadas y helpers de CRUD.
 *
 * No es un ORM. Las consultas complejas se escriben en SQL (que es donde
 * se leen mejor) y estos helpers cubren el 80% repetitivo. Todo valor de
 * usuario viaja como parametro enlazado; los nombres de tabla/columna
 * nunca vienen del request sin pasar por una lista blanca.
 */
abstract class Modelo
{
    protected static string $tabla = '';
    protected static string $llave = '';
    /** @var string[] Columnas que se pueden usar en ORDER BY */
    protected static array $ordenables = [];

    protected static function pdo(): PDO
    {
        return Database::conexion();
    }

    /* ---- Consultas crudas -------------------------------------- */

    public static function filas(string $sql, array $params = []): array
    {
        return self::ejecutar($sql, $params)->fetchAll();
    }

    public static function fila(string $sql, array $params = []): ?array
    {
        $fila = self::ejecutar($sql, $params)->fetch();
        return $fila === false ? null : $fila;
    }

    public static function valor(string $sql, array $params = []): mixed
    {
        $valor = self::ejecutar($sql, $params)->fetchColumn();
        return $valor === false ? null : $valor;
    }

    public static function columna(string $sql, array $params = []): array
    {
        return self::ejecutar($sql, $params)->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function ejecutar(string $sql, array $params = []): \PDOStatement
    {
        try {
            $stmt = self::pdo()->prepare($sql);
            foreach ($params as $clave => $valor) {
                $nombre = is_int($clave) ? $clave + 1 : (str_starts_with((string) $clave, ':') ? $clave : ':' . $clave);
                $tipo = match (true) {
                    is_int($valor)  => PDO::PARAM_INT,
                    is_bool($valor) => PDO::PARAM_BOOL,
                    is_null($valor) => PDO::PARAM_NULL,
                    default         => PDO::PARAM_STR,
                };
                $stmt->bindValue($nombre, $valor, $tipo);
            }
            $stmt->execute();
            return $stmt;
        } catch (PDOException $e) {
            throw self::traducirError($e, $sql);
        }
    }

    /* ---- CRUD ---------------------------------------------------- */

    public static function buscar(int|string $id, ?string $tabla = null, ?string $llave = null): ?array
    {
        $tabla = $tabla ?? static::$tabla;
        $llave = $llave ?? static::$llave;
        return self::fila("SELECT * FROM `{$tabla}` WHERE `{$llave}` = ? LIMIT 1", [$id]);
    }

    public static function buscarOFallar(int|string $id, string $recurso = 'Registro'): array
    {
        $fila = static::buscar($id);
        if (!$fila) {
            throw ApiException::noEncontrado($recurso);
        }
        return $fila;
    }

    public static function insertar(array $datos, ?string $tabla = null): int
    {
        $tabla = $tabla ?? static::$tabla;
        if (!$datos) {
            throw new ApiException('No hay datos que insertar.', 400, 'SIN_DATOS');
        }

        $columnas = array_keys($datos);
        $marcas   = implode(',', array_fill(0, count($columnas), '?'));
        $sql = "INSERT INTO `{$tabla}` (`" . implode('`,`', $columnas) . "`) VALUES ({$marcas})";

        self::ejecutar($sql, array_values($datos));
        return (int) self::pdo()->lastInsertId();
    }

    public static function actualizar(int|string $id, array $datos, ?string $tabla = null, ?string $llave = null): bool
    {
        $tabla = $tabla ?? static::$tabla;
        $llave = $llave ?? static::$llave;
        if (!$datos) {
            return false;
        }

        $asignaciones = implode(', ', array_map(static fn ($c) => "`{$c}` = ?", array_keys($datos)));
        $sql = "UPDATE `{$tabla}` SET {$asignaciones} WHERE `{$llave}` = ?";

        $params = array_values($datos);
        $params[] = $id;

        return self::ejecutar($sql, $params)->rowCount() >= 0;
    }

    public static function eliminar(int|string $id, ?string $tabla = null, ?string $llave = null): bool
    {
        $tabla = $tabla ?? static::$tabla;
        $llave = $llave ?? static::$llave;
        return self::ejecutar("DELETE FROM `{$tabla}` WHERE `{$llave}` = ?", [$id])->rowCount() > 0;
    }

    public static function existe(string $columna, mixed $valor, ?int $exceptoId = null, ?string $tabla = null, ?string $llave = null): bool
    {
        $tabla = $tabla ?? static::$tabla;
        $llave = $llave ?? static::$llave;

        $sql = "SELECT 1 FROM `{$tabla}` WHERE `{$columna}` = ?";
        $params = [$valor];

        if ($exceptoId !== null) {
            $sql .= " AND `{$llave}` <> ?";
            $params[] = $exceptoId;
        }

        return self::valor($sql . ' LIMIT 1', $params) !== null;
    }

    /**
     * Cuenta filas. `$tabla` admite un FROM completo con JOIN (se deja
     * tal cual); un identificador simple se entrecomilla.
     */
    public static function contar(string $tabla, string $where = '1', array $params = []): int
    {
        $from = preg_match('/^[a-zA-Z0-9_]+$/', $tabla) ? "`{$tabla}`" : $tabla;
        return (int) self::valor("SELECT COUNT(*) FROM {$from} WHERE {$where}", $params);
    }

    /* ---- Utilidades --------------------------------------------- */

    /** Devuelve "columna DIR" solo si la columna esta en la lista blanca. */
    public static function orden(?string $campo, ?string $direccion, string $porDefecto): string
    {
        $campo = in_array((string) $campo, static::$ordenables, true) ? $campo : $porDefecto;
        $dir   = strtoupper((string) $direccion) === 'DESC' ? 'DESC' : 'ASC';
        return "`{$campo}` {$dir}";
    }

    /** Normaliza pagina/por_pagina con topes sanos. */
    public static function paginacion(Request $peticion, int $porDefecto = 25, int $maximo = 200): array
    {
        $pagina    = max(1, (int) ($peticion->queryInt('pagina', 1) ?? 1));
        $porPagina = (int) ($peticion->queryInt('por_pagina', $porDefecto) ?? $porDefecto);
        $porPagina = max(1, min($maximo, $porPagina));
        return [$pagina, $porPagina, ($pagina - 1) * $porPagina];
    }

    /** Convierte errores de integridad de MySQL en mensajes utiles. */
    private static function traducirError(PDOException $e, string $sql): \Throwable
    {
        $codigo  = $e->errorInfo[1] ?? 0;
        $mensaje = $e->getMessage();

        Log::error('SQL: ' . $mensaje, ['sql' => substr($sql, 0, 300)]);

        return match ($codigo) {
            1062 => ApiException::conflicto(
                self::mensajeDuplicado($mensaje),
                ['restriccion' => self::nombreIndice($mensaje)]
            ),
            1451 => ApiException::conflicto('No se puede eliminar: hay registros que dependen de este.'),
            1452 => new ApiException('Referencia invalida: el registro relacionado no existe.', 422, 'FK_INVALIDA'),
            1406 => new ApiException('Alguno de los textos enviados es demasiado largo.', 422, 'DATO_LARGO'),
            default => new ApiException(
                Env::bool('APP_DEBUG', false) ? $mensaje : 'Error al consultar la base de datos.',
                500,
                'ERROR_BD'
            ),
        };
    }

    private static function nombreIndice(string $mensaje): string
    {
        return preg_match("/for key '([^']+)'/", $mensaje, $m) ? $m[1] : '';
    }

    private static function mensajeDuplicado(string $mensaje): string
    {
        $indice = self::nombreIndice($mensaje);

        return match (true) {
            str_contains($indice, 'uq_hb_profesor') => 'El docente ya tiene clase en ese bloque horario.',
            str_contains($indice, 'uq_hb_espacio')  => 'Ese salon o laboratorio ya esta ocupado en ese bloque.',
            str_contains($indice, 'uq_hb_seccion')  => 'La seccion ya tiene otra materia en ese bloque.',
            str_contains($indice, 'cedula')         => 'Ya existe un registro con esa cedula.',
            str_contains($indice, 'codigo')         => 'Ya existe un registro con ese codigo.',
            str_contains($indice, 'correo')         => 'Ya existe un registro con ese correo.',
            str_contains($indice, 'identificador')  => 'Ese usuario ya esta registrado.',
            default => 'Ya existe un registro con esos datos.',
        };
    }
}
