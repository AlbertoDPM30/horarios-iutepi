<?php

require_once "config/autenticacion.php";

class ControladorUsuarios {

    /*=============================================
    INICIAR SESIÓN (POST)
    =============================================*/
    static public function ctrIniciarSesion($username, $password) {
        try {
            // VALIDAR UN TOKEN EXISTENTE EN LA SOLICITUD
            $headers = apache_request_headers();
            $authHeader = $headers['Authorization'] ?? '';
            $existingToken = null;
            $isTokenValid = false;
            $decodedTokenData = null;

            if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $existingToken = $matches[1];

                $decodedTokenData = Autenticacion::validarToken($existingToken);

                if ($decodedTokenData !== false) { 
                    $isTokenValid = true;
                }
            }

            // SI UN TOKEN VÁLIDO YA ESTÁ PRESENTE EN LA SOLICITUD
            if ($isTokenValid) {
                
                $userIdFromToken = $decodedTokenData['user_id'] ?? null;
                
                $usuarioExistenteBD = null;
                if ($userIdFromToken) {
                    $usuarioExistenteBD = ModeloUsuarios::mdlMostrarUsuarios("users", "user_id", $userIdFromToken);
                }

                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "¡Alerta! Ya tienes una sesión iniciada con este token. No es necesario iniciar sesión de nuevo.",
                    "token" => $existingToken,
                    "user_id" => $userIdFromToken,
                    "data" => [
                        "username" => $usuarioExistenteBD['username'] ?? ($decodedTokenData['username'] ?? 'N/A'),
                        "first_name" => $usuarioExistenteBD['first_name'] ?? 'N/A',
                        "last_name" => $usuarioExistenteBD['last_name'] ?? 'N/A',
                        "message_detail" => "Token válido y activo, no se generó uno nuevo."
                    ]
                ];
            }

            // AUTENTICACIÓN POR USUARIO/CONTRASEÑA.
            if (empty($username) || empty($password)) {
                return [
                    "status" => 400,
                    "success" => false,
                    "message" => "El usuario y la contraseña son requeridos."
                ];
            }
            
            $usuario = ModeloUsuarios::mdlMostrarUsuarios("users", "username", $username);

            if ($usuario && password_verify($password, $usuario['password'])) {
                
                $token = Autenticacion::generarToken($usuario['user_id']); // generamos un nuevo token.

                // Validar si el usuario se encuentra activo o inactivo
                if ($usuario['status'] != 1) {
                    return [
                        "status" => 401,
                        "success" => false,
                        "message" => "Usuario existente no disponible. Comuniquese con un administrador"
                    ];
                }
                
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Autenticación exitosa. Se ha generado un nuevo token.",
                    "token" => $token,
                    "user_id" => $usuario['user_id'],
                    "data" => [
                        "username" => $usuario['username'],
                        "first_name" => $usuario['first_name'],
                        "last_name" => $usuario['last_name']
                    ]
                ];
            } else {
                // Credenciales incorrectas
                return [
                    "status" => 401,
                    "success" => false,
                    "message" => "Credenciales incorrectas. Por favor, verifica tu usuario y contraseña."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrIniciarSesion: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado. Inténtalo de nuevo más tarde."
            ];
        }
    }

    /*=============================================
    CERRAR SESIÓN (POST)
    =============================================*/

    static public function ctrCerrarSesion($user_id_from_token, $token_received = null) {
        
        error_log("Logout request for user_id: " . $user_id_from_token . " with token (first 10 chars): " . substr($token_received, 0, 10) . "...");
        
        return [
            "status" => 200,
            "success" => true,
            "message" => "Sesión cerrada correctamente."
        ];
    }

    /*=============================================
    MOSTRAR USUARIO(S) (GET)
    =============================================*/
    static public function ctrMostrarUsuarios($item = null, $valor = null) {
        try {
            $respuesta = ModeloUsuarios::MdlMostrarUsuarios("users", $item, $valor);
            
            if ($item !== null && $valor !== null && !$respuesta) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "Usuario no encontrado."
                ];
            }

            return [
                "status" => 200,
                "success" => true,
                "data" => $respuesta
            ];
            
        } catch (Exception $e) {
            error_log("Error en ctrMostrarUsuarios: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    CREAR USUARIO (POST)
    =============================================*/
    static public function ctrCrearUsuario($datos) {
        try {
            $camposRequeridos = ['username', 'password', 'nombres', 'apellidos', 'ci'];
            foreach ($camposRequeridos as $campo) {
                if (empty($datos[$campo])) {
                    return [
                        "status" => 400,
                        "success" => false,
                        "message" => "El campo '$campo' es requerido."
                    ];
                }
            }

            $usuarioExistente = ModeloUsuarios::MdlMostrarUsuarios("users", "username", $datos['username']);
            if ($usuarioExistente) {
                return [
                    "status" => 409,
                    "success" => false,
                    "message" => "El nombre de usuario ya está en uso. Por favor, elige otro."
                ];
            }

            $datos['password'] = password_hash($datos['password'], PASSWORD_BCRYPT);

            $datosModelo = [
                'nombres' => $datos['nombres'],
                'apellidos' => $datos['apellidos'],
                'ci' => $datos['ci'],
                'username' => $datos['username'],
                'password' => $datos['password']
            ];

            $respuesta = ModeloUsuarios::mdlCrearUsuario("users", $datosModelo);

            if ($respuesta === "ok") {
                return [
                    "status" => 201,
                    "success" => true,
                    "message" => "Usuario creado exitosamente."
                ];
            } else {
                error_log("Error en ModeloUsuarios::mdlCrearUsuario: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo crear el usuario. Inténtalo de nuevo.",
					"error" => "Error en ModeloUsuarios::mdlCrearUsuario: " . $respuesta
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrCrearUsuario: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    ACTUALIZAR USUARIO (PUT)
    =============================================*/
    static public function ctrEditarUsuario($datos) {
        try {
            $userIdToUpdate = isset($datos['user_id']) ? $datos['user_id'] : (isset($datos['id']) ? $datos['id'] : null);

			// Sí no se envió ningún ID a editar
            if ($userIdToUpdate === null) {
				return [
                    "status" => 400,
                    "success" => false,
                    "message" => "El ID del usuario es requerido para la actualización."
                ];
            }
            
			// No se puede modificar el usuario master
            if ($userIdToUpdate == 1) {
                return [
                    "status" => 403,
                    "success" => false,
                    "message" => "No se permite modificar este usuario."
                ];
            }

            // Sólo el Administrador (master) puede editar usuarios
            if ($GLOBALS['user_id'] != 1) {
				return [ "status" => 403, "success" => false, "message" => "No tienes permiso para editar este usuario."];
            }

            if (isset($datos['password']) && !empty($datos['password'])) {
                $datos['password'] = password_hash($datos['password'], PASSWORD_BCRYPT);
            }

            if (isset($datos['id'])) {
                $datos['user_id'] = $datos['id'];
                unset($datos['id']);
            }
            
            date_default_timezone_set('America/Caracas');
            $datos['updated_at'] = date('Y-m-d H:i:s');

            $respuesta = ModeloUsuarios::mdlEditarUsuario("users", $datos);

            if ($respuesta === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Usuario actualizado correctamente."
                ];
            } else {
                error_log("Error en ModeloUsuarios::mdlEditarUsuario: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo actualizar el usuario. Inténtalo de nuevo. $respuesta"
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrEditarUsuario: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    ACTUALIZAR STATUS (PATCH)
    =============================================*/
    static public function ctrActualizarStatusUsuario($user_id, $status) {
        try {
            if ($user_id == 1) {
                return [
                    "status" => 403,
                    "success" => false,
                    "message" => "No se permite modificar este usuario."
                ];
            }

            if (!in_array($status, ["0", "1"])) {
                return [
                    "status" => 400,
                    "success" => false,
                    "message" => "El valor para el status no es válido."
                ];
            }

            date_default_timezone_set('America/Caracas');
            $datosActualizar = [
                'user_id' => $user_id,
                'status' => $status,
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $respuesta = ModeloUsuarios::mdlEditarUsuario("users", $datosActualizar);

            if ($respuesta === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Estado del usuario actualizado correctamente."
                ];
            } else {
                error_log("Error en ModeloUsuarios::mdlEditarUsuario (status): " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo actualizar el estado del usuario. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrActualizarStatusUsuario: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }

    /*=============================================
    ELIMINAR USUARIO (DELETE)
    =============================================*/
    static public function ctrEliminarUsuario($user_id) {
        try {
            if ($user_id == 1) {
                return [
                    "status" => 403,
                    "success" => false,
                    "message" => "No se permite eliminar este usuario."
                ];
            }
            
            $usuarioExistente = ModeloUsuarios::MdlMostrarUsuarios("users", "user_id", $user_id);
            if (!$usuarioExistente) {
                return [
                    "status" => 404,
                    "success" => false,
                    "message" => "El usuario a eliminar no fue encontrado."
                ];
            }

            $respuesta = ModeloUsuarios::mdlEliminarUsuario("users", $user_id);

            if ($respuesta === "ok") {
                return [
                    "status" => 200,
                    "success" => true,
                    "message" => "Usuario eliminado correctamente."
                ];
            } else {
                error_log("Error en ModeloUsuarios::mdlEliminarUsuario: " . $respuesta);
                return [
                    "status" => 500,
                    "success" => false,
                    "message" => "No se pudo eliminar el usuario. Inténtalo de nuevo."
                ];
            }
        } catch (Exception $e) {
            error_log("Error en ctrEliminarUsuario: " . $e->getMessage());
            return [
                "status" => 500,
                "success" => false,
                "message" => "Ocurrió un error inesperado al procesar la solicitud."
            ];
        }
    }
}