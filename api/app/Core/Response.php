<?php

namespace App\Core;

/**
 * Respuesta JSON con envoltura unica.
 *
 *   exito:  { "exito": true,  "datos": ..., "meta": { ... } }
 *   error:  { "exito": false, "error": { "codigo", "mensaje", "detalles" } }
 *
 * Tener siempre la misma forma evita que el frontend tenga que adivinar
 * como viene cada endpoint.
 */
final class Response
{
    private array $headers = [];

    private function __construct(
        private int $estado,
        private array $cuerpo
    ) {
    }

    public static function ok(mixed $datos = null, array $meta = []): self
    {
        return self::exito(200, $datos, $meta);
    }

    public static function creado(mixed $datos = null, array $meta = []): self
    {
        return self::exito(201, $datos, $meta);
    }

    public static function sinContenido(): self
    {
        return new self(204, []);
    }

    public static function exito(int $estado, mixed $datos = null, array $meta = []): self
    {
        $cuerpo = ['exito' => true, 'datos' => $datos];
        if ($meta) {
            $cuerpo['meta'] = $meta;
        }
        return new self($estado, $cuerpo);
    }

    public static function error(int $estado, string $mensaje, string $codigo = 'ERROR', array $detalles = []): self
    {
        return new self($estado, [
            'exito' => false,
            'error' => array_filter([
                'codigo'   => $codigo,
                'mensaje'  => $mensaje,
                'detalles' => $detalles ?: null,
            ], static fn ($v) => $v !== null),
        ]);
    }

    /** Lista paginada con metadatos de paginacion. */
    public static function paginado(array $filas, int $total, int $pagina, int $porPagina, array $extra = []): self
    {
        return self::exito(200, $filas, array_merge([
            'total'      => $total,
            'pagina'     => $pagina,
            'por_pagina' => $porPagina,
            'paginas'    => $porPagina > 0 ? (int) ceil($total / $porPagina) : 1,
        ], $extra));
    }

    public function conHeader(string $nombre, string $valor): self
    {
        $this->headers[$nombre] = $valor;
        return $this;
    }

    public function conHeaders(array $headers): self
    {
        foreach ($headers as $n => $v) {
            $this->headers[$n] = (string) $v;
        }
        return $this;
    }

    /**
     * Agrega cabeceras solo si no vienen ya puestas.
     *
     * Lo usa el limitador: cuando una ruta tiene dos cubos (el general y
     * el de login), el que rechaza la peticion es el que debe describir
     * el limite, no el de fuera.
     */
    public function conHeadersSiFaltan(array $headers): self
    {
        foreach ($headers as $n => $v) {
            if (!isset($this->headers[$n])) {
                $this->headers[$n] = (string) $v;
            }
        }
        return $this;
    }

    public function estado(): int
    {
        return $this->estado;
    }

    public function enviar(): void
    {
        if (!headers_sent()) {
            http_response_code($this->estado);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            foreach ($this->headers as $nombre => $valor) {
                header("{$nombre}: {$valor}");
            }
        }

        if ($this->estado !== 204) {
            echo json_encode(
                $this->cuerpo,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
            );
        }
    }
}
