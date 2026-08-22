<?php

namespace App\Core;

/**
 * Arranque de la API: autoload, entorno, zona horaria, manejo global de
 * errores y despacho de la ruta.
 */
final class App
{
    public static function iniciar(): void
    {
        $raiz = dirname(__DIR__, 2);

        self::registrarAutoload($raiz);

        Env::cargar($raiz . '/.env');

        date_default_timezone_set((string) Env::get('APP_TIMEZONE', 'America/Caracas'));

        $debug = Env::bool('APP_DEBUG', false);
        ini_set('display_errors', $debug ? '1' : '0');
        error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);

        set_error_handler(static function (int $nivel, string $mensaje, string $archivo = '', int $linea = 0): bool {
            if (!(error_reporting() & $nivel)) {
                return false;
            }
            throw new \ErrorException($mensaje, 0, $nivel, $archivo, $linea);
        });

        $peticion = null;

        try {
            $peticion = Request::desdeGlobales();

            $router = new Router();
            require $raiz . '/routes/api.php'; // recibe $router

            $respuesta = $router->despachar($peticion);
        } catch (ApiException $e) {
            $respuesta = Response::error($e->estado(), $e->getMessage(), $e->codigoError(), $e->detalles());
        } catch (\Throwable $e) {
            Log::error($e->getMessage(), [
                'tipo'    => get_class($e),
                'archivo' => $e->getFile() . ':' . $e->getLine(),
                'ruta'    => $peticion?->ruta(),
            ]);

            $respuesta = Response::error(
                500,
                $debug ? $e->getMessage() : 'Ocurrio un error inesperado en el servidor.',
                'ERROR_INTERNO',
                $debug ? ['archivo' => $e->getFile() . ':' . $e->getLine()] : []
            );
        }

        // CORS tambien en las respuestas de error que no pasaron por el pipeline
        if (!headers_sent()) {
            $origenes = array_filter(array_map('trim', explode(',', (string) Env::get('CORS_ORIGINS', '*'))));
            if (!$origenes || in_array('*', $origenes, true)) {
                $respuesta->conHeader('Access-Control-Allow-Origin', '*');
            }
        }

        $respuesta->enviar();
    }

    /** Autoload PSR-4 minimo: App\ -> api/app/ */
    private static function registrarAutoload(string $raiz): void
    {
        spl_autoload_register(static function (string $clase) use ($raiz): void {
            $prefijo = 'App\\';
            if (!str_starts_with($clase, $prefijo)) {
                return;
            }
            $relativa = str_replace('\\', '/', substr($clase, strlen($prefijo)));
            $archivo  = $raiz . '/app/' . $relativa . '.php';
            if (is_file($archivo)) {
                require_once $archivo;
            }
        });
    }
}
