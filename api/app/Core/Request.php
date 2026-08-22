<?php

namespace App\Core;

/**
 * Envoltorio de la peticion HTTP.
 *
 * Los headers se leen sin `apache_request_headers()` porque esa funcion
 * no existe en nginx + php-fpm ni en CGI, y la API tiene que correr en
 * cualquier hosting.
 */
final class Request
{
    private array $headers;
    private array $cuerpo;
    /** @var array<string,string> Parametros de la ruta ({id} -> "12") */
    private array $params = [];
    private ?array $usuario = null;

    public function __construct(
        private string $metodo,
        private string $ruta,
        private array $query,
        string $cuerpoCrudo
    ) {
        $this->headers = self::leerHeaders();
        $this->cuerpo  = self::decodificarCuerpo($cuerpoCrudo, $this->header('content-type') ?? '');
    }

    public static function desdeGlobales(): self
    {
        $metodo = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        // La ruta llega por ?ruta=... (rewrite de .htaccess) o por PATH_INFO
        $ruta = $_GET['ruta'] ?? null;
        if ($ruta === null) {
            $uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
            $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
            $ruta = $base !== '' && str_starts_with($uri, $base) ? substr($uri, strlen($base)) : $uri;
        }

        $query = $_GET;
        unset($query['ruta']);

        return new self($metodo, '/' . trim((string) $ruta, '/'), $query, file_get_contents('php://input') ?: '');
    }

    /* ----------------------------------------------------------- */

    public function metodo(): string
    {
        return $this->metodo;
    }

    public function ruta(): string
    {
        return $this->ruta === '/' ? '/' : rtrim($this->ruta, '/');
    }

    public function header(string $nombre): ?string
    {
        return $this->headers[strtolower($nombre)] ?? null;
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $clave) {
            if (!empty($_SERVER[$clave])) {
                $ip = trim(explode(',', (string) $_SERVER[$clave])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250);
    }

    public function tokenBearer(): ?string
    {
        $auth = $this->header('authorization') ?? '';
        if (preg_match('/Bearer\s+(\S+)/i', $auth, $m)) {
            return $m[1];
        }
        return null;
    }

    /* ---- Parametros ------------------------------------------- */

    public function setParams(array $params): void
    {
        $this->params = $params;
    }

    public function param(string $nombre, mixed $porDefecto = null): mixed
    {
        return $this->params[$nombre] ?? $porDefecto;
    }

    public function paramInt(string $nombre): int
    {
        $valor = $this->param($nombre);
        if ($valor === null || !ctype_digit((string) $valor)) {
            throw new ApiException("El parametro '{$nombre}' debe ser un numero entero.", 400, 'PARAM_INVALIDO');
        }
        return (int) $valor;
    }

    public function query(string $nombre, mixed $porDefecto = null): mixed
    {
        $valor = $this->query[$nombre] ?? null;
        return ($valor === null || $valor === '') ? $porDefecto : $valor;
    }

    public function queryInt(string $nombre, ?int $porDefecto = null): ?int
    {
        $valor = $this->query($nombre);
        return is_numeric($valor) ? (int) $valor : $porDefecto;
    }

    public function queryBool(string $nombre, bool $porDefecto = false): bool
    {
        $valor = $this->query($nombre);
        if ($valor === null) {
            return $porDefecto;
        }
        return in_array(strtolower((string) $valor), ['1', 'true', 'si', 'yes'], true);
    }

    public function todaLaQuery(): array
    {
        return $this->query;
    }

    /* ---- Cuerpo ----------------------------------------------- */

    public function cuerpo(): array
    {
        return $this->cuerpo;
    }

    public function input(string $clave, mixed $porDefecto = null): mixed
    {
        $valor = $this->cuerpo[$clave] ?? null;
        return $valor === null ? $porDefecto : $valor;
    }

    /* ---- Usuario autenticado ---------------------------------- */

    public function setUsuario(?array $usuario): void
    {
        $this->usuario = $usuario;
    }

    public function usuario(): ?array
    {
        return $this->usuario;
    }

    public function usuarioId(): ?int
    {
        return isset($this->usuario['usuario_id']) ? (int) $this->usuario['usuario_id'] : null;
    }

    public function rol(): ?string
    {
        return $this->usuario['rol'] ?? null;
    }

    public function esRol(string ...$roles): bool
    {
        return in_array($this->rol(), $roles, true);
    }

    /* ----------------------------------------------------------- */

    private static function leerHeaders(): array
    {
        $headers = [];

        foreach ($_SERVER as $clave => $valor) {
            if (str_starts_with($clave, 'HTTP_')) {
                $nombre = strtolower(str_replace('_', '-', substr($clave, 5)));
                $headers[$nombre] = $valor;
            }
        }

        foreach (['CONTENT_TYPE' => 'content-type', 'CONTENT_LENGTH' => 'content-length'] as $server => $header) {
            if (isset($_SERVER[$server])) {
                $headers[$header] = $_SERVER[$server];
            }
        }

        // Algunos hostings mueven el Authorization a REDIRECT_HTTP_AUTHORIZATION
        if (!isset($headers['authorization'])) {
            foreach (['REDIRECT_HTTP_AUTHORIZATION', 'HTTP_X_AUTHORIZATION'] as $alt) {
                if (!empty($_SERVER[$alt])) {
                    $headers['authorization'] = $_SERVER[$alt];
                    break;
                }
            }
        }

        return $headers;
    }

    private static function decodificarCuerpo(string $crudo, string $contentType): array
    {
        if ($crudo === '') {
            return $_POST ?: [];
        }

        if (str_contains($contentType, 'application/json')) {
            $datos = json_decode($crudo, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ApiException('El cuerpo de la peticion no es un JSON valido.', 400, 'JSON_INVALIDO');
            }
            return is_array($datos) ? $datos : [];
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            parse_str($crudo, $datos);
            return $datos;
        }

        $datos = json_decode($crudo, true);
        return is_array($datos) ? $datos : ($_POST ?: []);
    }
}
