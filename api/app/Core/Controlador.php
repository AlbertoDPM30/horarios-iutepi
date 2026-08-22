<?php

namespace App\Core;

use App\Models\Estudiante;
use App\Models\Periodo;
use App\Models\Profesor;

/** Utilidades compartidas por todos los controladores. */
abstract class Controlador
{
    /** Filtros de listado tomados de la query string. */
    protected function filtros(Request $peticion, array $permitidos): array
    {
        $filtros = [];
        foreach ($permitidos as $clave) {
            $valor = $peticion->query($clave);
            if ($valor !== null && $valor !== '') {
                $filtros[$clave] = $valor;
            }
        }
        return $filtros;
    }

    /** Exige que el periodo exista y devuelve su fila. */
    protected function periodo(int $periodoId): array
    {
        $periodo = Periodo::buscar($periodoId);
        if (!$periodo) {
            throw ApiException::noEncontrado('Periodo');
        }
        return $periodo;
    }

    /**
     * Corta la operacion si el periodo no admite ese tipo de cambio.
     * Es la contraparte en servidor de los botones deshabilitados del
     * frontend: nunca se confia solo en la interfaz.
     */
    protected function exigirPermisoPeriodo(array $periodo, string $permiso): void
    {
        $permisos = Periodo::permisos($periodo);

        if (empty($permisos[$permiso])) {
            $motivo = match ($periodo['estado']) {
                'FINALIZADO' => 'El periodo ya finalizo: solo se puede consultar.',
                'EN_CURSO'   => 'El periodo esta en curso: esta accion alteraria horarios ya publicados.',
                default      => 'Esta accion no esta permitida para el estado actual del periodo.',
            };
            throw ApiException::prohibido($motivo);
        }
    }

    /** Perfil de docente del usuario autenticado. */
    protected function perfilDocente(Request $peticion): array
    {
        $profesor = Profesor::porUsuario((int) $peticion->usuarioId());
        if (!$profesor) {
            throw ApiException::prohibido('Tu usuario no tiene un registro de docente asociado.');
        }
        return $profesor;
    }

    /** Perfil de estudiante del usuario autenticado. */
    protected function perfilEstudiante(Request $peticion): array
    {
        $estudiante = Estudiante::porUsuario((int) $peticion->usuarioId());
        if (!$estudiante) {
            throw ApiException::prohibido('Tu usuario no tiene un registro de estudiante asociado.');
        }
        return $estudiante;
    }

    /**
     * Un docente solo ve lo suyo y un estudiante solo lo suyo; el
     * administrador ve todo.
     */
    protected function exigirPropio(Request $peticion, string $rol, int $idSolicitado): void
    {
        if ($peticion->esRol('ADMIN')) {
            return;
        }

        $propio = match ($rol) {
            'DOCENTE'    => (int) $this->perfilDocente($peticion)['profesor_id'],
            'ESTUDIANTE' => (int) $this->perfilEstudiante($peticion)['estudiante_id'],
            default      => -1,
        };

        if ($propio !== $idSolicitado) {
            throw ApiException::prohibido('Solo puedes consultar tu propia informacion.');
        }
    }
}
