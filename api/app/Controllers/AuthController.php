<?php

namespace App\Controllers;

use App\Core\ApiException;
use App\Core\Controlador;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validador;
use App\Models\Usuario;
use App\Services\AuditoriaService;
use App\Services\AuthService;

class AuthController extends Controlador
{
    /** POST /auth/login/estudiante  { codigo } */
    public function loginEstudiante(Request $peticion): Response
    {
        $datos = Validador::hacer($peticion->cuerpo(), [
            'codigo' => 'requerido|texto|min:4|max:20',
        ]);

        $sesion = AuthService::loginEstudiante($peticion, $datos['codigo']);
        AuditoriaService::registrar($peticion, 'login', 'estudiante', $datos['codigo']);

        return Response::ok($sesion);
    }

    /** POST /auth/login/docente  { cedula } */
    public function loginDocente(Request $peticion): Response
    {
        $datos = Validador::hacer($peticion->cuerpo(), [
            'cedula' => 'requerido|texto|min:5|max:20',
        ]);

        $sesion = AuthService::loginDocente($peticion, $datos['cedula']);
        AuditoriaService::registrar($peticion, 'login', 'docente', $datos['cedula']);

        return Response::ok($sesion);
    }

    /** POST /auth/login/admin  { correo, password } */
    public function loginAdmin(Request $peticion): Response
    {
        $datos = Validador::hacer($peticion->cuerpo(), [
            'correo'   => 'requerido|correo',
            'password' => 'requerido|texto|min:6|max:100',
        ]);

        $sesion = AuthService::loginAdmin($peticion, $datos['correo'], $datos['password']);
        AuditoriaService::registrar($peticion, 'login', 'admin', $datos['correo']);

        return Response::ok($sesion);
    }

    /** POST /auth/refresh  { refresh_token } */
    public function refrescar(Request $peticion): Response
    {
        $datos = Validador::hacer($peticion->cuerpo(), [
            'refresh_token' => 'requerido|texto|min:32|max:128',
        ]);

        return Response::ok(AuthService::refrescar($peticion, $datos['refresh_token']));
    }

    /** GET /auth/yo */
    public function yo(Request $peticion): Response
    {
        $usuario = Usuario::buscar((int) $peticion->usuarioId());
        if (!$usuario) {
            throw ApiException::noAutorizado();
        }

        return Response::ok(['usuario' => Usuario::perfil($usuario)]);
    }

    /** POST /auth/logout */
    public function logout(Request $peticion): Response
    {
        AuthService::cerrarSesion($peticion->input('refresh_token'), $peticion->usuarioId());
        AuditoriaService::registrar($peticion, 'logout', 'usuario', (string) $peticion->usuarioId());

        return Response::ok(['mensaje' => 'Sesion cerrada.']);
    }
}
