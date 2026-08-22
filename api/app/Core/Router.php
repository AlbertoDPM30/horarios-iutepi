<?php

namespace App\Core;

/**
 * Router declarativo con parametros y pipeline de middlewares.
 *
 * Las rutas viven en routes/api.php, no repartidas en includes ni en un
 * switch gigante. Cada ruta declara sus middlewares, asi los permisos se
 * leen de un vistazo.
 */
final class Router
{
    /** @var array<string, array<int, array{patron:string, params:string[], accion:callable|array, middlewares:array}>> */
    private array $rutas = [];
    private array $middlewaresGlobales = [];

    public function global(callable ...$middlewares): void
    {
        foreach ($middlewares as $m) {
            $this->middlewaresGlobales[] = $m;
        }
    }

    public function get(string $ruta, array|callable $accion, array $middlewares = []): void
    {
        $this->agregar('GET', $ruta, $accion, $middlewares);
    }

    public function post(string $ruta, array|callable $accion, array $middlewares = []): void
    {
        $this->agregar('POST', $ruta, $accion, $middlewares);
    }

    public function put(string $ruta, array|callable $accion, array $middlewares = []): void
    {
        $this->agregar('PUT', $ruta, $accion, $middlewares);
    }

    public function patch(string $ruta, array|callable $accion, array $middlewares = []): void
    {
        $this->agregar('PATCH', $ruta, $accion, $middlewares);
    }

    public function delete(string $ruta, array|callable $accion, array $middlewares = []): void
    {
        $this->agregar('DELETE', $ruta, $accion, $middlewares);
    }

    /** Atajo para declarar el CRUD completo de un recurso. */
    public function recurso(string $base, string $controlador, array $middlewares = [], array $solo = []): void
    {
        $mapa = [
            'index'   => ['GET',    "/{$base}",       'index'],
            'ver'     => ['GET',    "/{$base}/{id}",  'ver'],
            'crear'   => ['POST',   "/{$base}",       'crear'],
            'editar'  => ['PUT',    "/{$base}/{id}",  'editar'],
            'borrar'  => ['DELETE', "/{$base}/{id}",  'borrar'],
        ];

        foreach ($mapa as $clave => [$metodo, $ruta, $accion]) {
            if ($solo && !in_array($clave, $solo, true)) {
                continue;
            }
            $this->agregar($metodo, $ruta, [$controlador, $accion], $middlewares);
        }
    }

    private function agregar(string $metodo, string $ruta, array|callable $accion, array $middlewares): void
    {
        $params = [];
        $patron = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static function ($m) use (&$params) {
                $params[] = $m[1];
                return '([^/]+)';
            },
            '/' . trim($ruta, '/')
        );

        $this->rutas[$metodo][] = [
            'patron'      => '#^' . $patron . '$#',
            'params'      => $params,
            'accion'      => $accion,
            'middlewares' => $middlewares,
        ];
    }

    /**
     * Los middlewares globales envuelven TODA la peticion, tambien las
     * que no encuentran ruta. Asi el preflight OPTIONS y los 404 salen
     * igualmente con las cabeceras CORS, que es lo que espera el
     * navegador antes de dejar pasar la peticion real.
     */
    public function despachar(Request $peticion): Response
    {
        return $this->ejecutarPipeline(
            $peticion,
            $this->middlewaresGlobales,
            fn (Request $req): Response => $this->resolver($req)
        );
    }

    private function resolver(Request $peticion): Response
    {
        $metodo = $peticion->metodo();
        $ruta   = $peticion->ruta();

        foreach ($this->rutas[$metodo] ?? [] as $definicion) {
            if (!preg_match($definicion['patron'], $ruta, $coincidencias)) {
                continue;
            }

            array_shift($coincidencias);
            $peticion->setParams(array_combine($definicion['params'], $coincidencias) ?: []);

            return $this->ejecutarPipeline($peticion, $definicion['middlewares'], $definicion['accion']);
        }

        // La ruta existe pero con otro verbo -> 405 en vez de 404
        foreach ($this->rutas as $otroMetodo => $definiciones) {
            if ($otroMetodo === $metodo) {
                continue;
            }
            foreach ($definiciones as $definicion) {
                if (preg_match($definicion['patron'], $ruta)) {
                    return Response::error(405, "El metodo {$metodo} no aplica para esta ruta.", 'METODO_NO_PERMITIDO');
                }
            }
        }

        return Response::error(404, "La ruta {$metodo} {$ruta} no existe.", 'RUTA_NO_ENCONTRADA');
    }

    /**
     * Ejecuta los middlewares en cadena (estilo cebolla) y al final la
     * accion del controlador.
     */
    private function ejecutarPipeline(Request $peticion, array $middlewares, array|callable $accion): Response
    {
        $siguiente = function (Request $req) use ($accion): Response {
            if (is_array($accion)) {
                [$clase, $metodo] = $accion;
                $controlador = new $clase();
                $resultado = $controlador->$metodo($req);
            } else {
                $resultado = $accion($req);
            }

            return $resultado instanceof Response ? $resultado : Response::ok($resultado);
        };

        foreach (array_reverse($middlewares) as $middleware) {
            $anterior  = $siguiente;
            $siguiente = static fn (Request $req): Response => $middleware($req, $anterior);
        }

        return $siguiente($peticion);
    }
}
