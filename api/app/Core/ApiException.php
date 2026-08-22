<?php

namespace App\Core;

use RuntimeException;

/**
 * Excepcion de dominio: el manejador global la convierte en una
 * respuesta JSON con el codigo HTTP correcto. Cualquier otra excepcion
 * termina en un 500 generico sin filtrar detalles internos.
 */
class ApiException extends RuntimeException
{
    public function __construct(
        string $mensaje,
        private int $estado = 400,
        private string $codigoError = 'ERROR',
        private array $detalles = []
    ) {
        parent::__construct($mensaje, $estado);
    }

    public function estado(): int
    {
        return $this->estado;
    }

    public function codigoError(): string
    {
        return $this->codigoError;
    }

    public function detalles(): array
    {
        return $this->detalles;
    }

    public static function noEncontrado(string $recurso = 'Recurso'): self
    {
        return new self("{$recurso} no encontrado.", 404, 'NO_ENCONTRADO');
    }

    public static function noAutorizado(string $mensaje = 'No has iniciado sesion.'): self
    {
        return new self($mensaje, 401, 'NO_AUTENTICADO');
    }

    public static function prohibido(string $mensaje = 'No tienes permiso para esta accion.'): self
    {
        return new self($mensaje, 403, 'PROHIBIDO');
    }

    public static function validacion(array $errores, string $mensaje = 'Revisa los datos enviados.'): self
    {
        return new self($mensaje, 422, 'VALIDACION', $errores);
    }

    public static function conflicto(string $mensaje, array $detalles = []): self
    {
        return new self($mensaje, 409, 'CONFLICTO', $detalles);
    }
}
