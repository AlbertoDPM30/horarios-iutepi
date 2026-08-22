<?php

namespace App\Core;

/**
 * Validador por reglas en cadena.
 *
 *   $datos = Validador::hacer($req->cuerpo(), [
 *       'nombres'  => 'requerido|texto|min:2|max:80',
 *       'semestre' => 'requerido|entero|entre:1,6',
 *       'correo'   => 'opcional|correo',
 *   ]);
 *
 * Devuelve solo las claves declaradas y ya casteadas, de modo que nunca
 * llega al modelo un campo que no se valido.
 */
final class Validador
{
    private array $errores = [];
    private array $limpios = [];

    private function __construct(
        private array $datos,
        private array $reglas
    ) {
    }

    public static function hacer(array $datos, array $reglas): array
    {
        $v = new self($datos, $reglas);
        return $v->validar();
    }

    private function validar(): array
    {
        foreach ($this->reglas as $campo => $cadena) {
            $reglas   = explode('|', $cadena);
            $opcional = in_array('opcional', $reglas, true);
            $presente = array_key_exists($campo, $this->datos);
            $valor    = $this->datos[$campo] ?? null;

            if (is_string($valor)) {
                $valor = trim($valor);
            }

            if (!$presente || $valor === null || $valor === '') {
                if (in_array('requerido', $reglas, true)) {
                    $this->errores[$campo] = 'Este campo es obligatorio.';
                } elseif ($presente && $opcional) {
                    $this->limpios[$campo] = $this->valorVacio($reglas);
                }
                continue;
            }

            $this->aplicarReglas($campo, $valor, $reglas);
        }

        if ($this->errores) {
            throw ApiException::validacion($this->errores);
        }

        return $this->limpios;
    }

    private function valorVacio(array $reglas): mixed
    {
        foreach ($reglas as $regla) {
            if (in_array($regla, ['entero', 'decimal', 'booleano'], true)) {
                return null;
            }
        }
        return '';
    }

    private function aplicarReglas(string $campo, mixed $valor, array $reglas): void
    {
        foreach ($reglas as $regla) {
            [$nombre, $arg] = array_pad(explode(':', $regla, 2), 2, null);

            switch ($nombre) {
                case 'requerido':
                case 'opcional':
                    break;

                case 'entero':
                    if (!is_numeric($valor) || (string) (int) $valor !== (string) $valor && !is_int($valor)) {
                        if (!ctype_digit(ltrim((string) $valor, '-'))) {
                            $this->errores[$campo] = 'Debe ser un numero entero.';
                            return;
                        }
                    }
                    $valor = (int) $valor;
                    break;

                case 'decimal':
                    if (!is_numeric($valor)) {
                        $this->errores[$campo] = 'Debe ser un numero.';
                        return;
                    }
                    $valor = (float) $valor;
                    break;

                case 'booleano':
                    $valor = in_array($valor, [true, 1, '1', 'true', 'si', 'on'], true) ? 1 : 0;
                    break;

                case 'texto':
                    if (!is_scalar($valor)) {
                        $this->errores[$campo] = 'Debe ser texto.';
                        return;
                    }
                    $valor = (string) $valor;
                    break;

                case 'correo':
                    if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
                        $this->errores[$campo] = 'No parece un correo valido.';
                        return;
                    }
                    break;

                case 'fecha':
                    $d = \DateTime::createFromFormat('Y-m-d', (string) $valor);
                    if (!$d || $d->format('Y-m-d') !== (string) $valor) {
                        $this->errores[$campo] = 'Debe tener el formato AAAA-MM-DD.';
                        return;
                    }
                    break;

                case 'hora':
                    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', (string) $valor)) {
                        $this->errores[$campo] = 'Debe tener el formato HH:MM.';
                        return;
                    }
                    $valor = strlen((string) $valor) === 5 ? $valor . ':00' : $valor;
                    break;

                case 'min':
                    if (is_int($valor) || is_float($valor)) {
                        if ($valor < (float) $arg) {
                            $this->errores[$campo] = "El minimo es {$arg}.";
                            return;
                        }
                    } elseif (mb_strlen((string) $valor) < (int) $arg) {
                        $this->errores[$campo] = "Debe tener al menos {$arg} caracteres.";
                        return;
                    }
                    break;

                case 'max':
                    if (is_int($valor) || is_float($valor)) {
                        if ($valor > (float) $arg) {
                            $this->errores[$campo] = "El maximo es {$arg}.";
                            return;
                        }
                    } elseif (mb_strlen((string) $valor) > (int) $arg) {
                        $this->errores[$campo] = "No puede pasar de {$arg} caracteres.";
                        return;
                    }
                    break;

                case 'entre':
                    [$minimo, $maximo] = array_map('floatval', explode(',', (string) $arg));
                    if ((float) $valor < $minimo || (float) $valor > $maximo) {
                        $this->errores[$campo] = "Debe estar entre {$minimo} y {$maximo}.";
                        return;
                    }
                    break;

                case 'en':
                    $permitidos = explode(',', (string) $arg);
                    if (!in_array((string) $valor, $permitidos, true)) {
                        $this->errores[$campo] = 'Valor no permitido: ' . implode(', ', $permitidos) . '.';
                        return;
                    }
                    break;

                case 'arreglo':
                    if (!is_array($valor)) {
                        $this->errores[$campo] = 'Debe ser una lista.';
                        return;
                    }
                    break;

                default:
                    break;
            }
        }

        $this->limpios[$campo] = $valor;
    }
}
