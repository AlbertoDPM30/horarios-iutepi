<?php

// Configurar cabeceras para respuestas JSON
header('Content-Type: application/json; charset=utf-8');

// Obtener método HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

// Procesar datos de entrada (JSON)
$entrada = json_decode(file_get_contents('php://input'), true);

// ID del usuario autenticado:
$idUsuarioAutenticado = $GLOBALS['user_id'] ?? null; 

/*=========================================
MANEJO DE MÉTODOS HTTP PARA /usuarios
==========================================*/
switch ($metodo) {

    /*=========================================
    OBTENER USUARIO(S)
    ==========================================*/
    case 'GET':
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        $respuesta = ControladorUsuarios::ctrMostrarUsuarios($id ? "user_id" : null, $id);
        http_response_code($respuesta['status']);
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    /*=========================================
    REGISTRAR USUARIO
    ==========================================*/
    case 'POST':
        if (empty($entrada['nombres']) || empty($entrada['apellidos']) || empty($entrada['ci']) || empty($entrada['password']) || empty($entrada['username'])) {
            echo json_encode(["mensaje" => "Todos los campos son obligatorios"], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            exit;
        }

        $respuesta = ControladorUsuarios::ctrCrearUsuario($entrada);
        http_response_code($respuesta['status']);
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    /*=========================================
    EDITAR USUARIO
    ==========================================*/
    case 'PUT':
        $respuesta = ControladorUsuarios::ctrEditarUsuario($entrada);
        http_response_code($respuesta['status']);
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    /*=========================================
    ELIMINAR USUARIO
    ==========================================*/
    case 'DELETE':
        $id = isset($_GET['id']) ? $_GET['id'] : null;
        if (!$id) {
            http_response_code(400);
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "Se requiere el ID para eliminar un usuario."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }
        $respuesta = ControladorUsuarios::ctrEliminarUsuario($id);
        http_response_code($respuesta['status']);
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
    
    /*=========================================
    ACTUALIZAR USUARIO
    ==========================================*/
    case 'PATCH':
        $id = $entrada['id'] ?? null;
        $status = $entrada['status'] ?? null;

        if ($id === null || $status === null) {
            http_response_code(400);
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "Se requiere 'id' y 'status' para actualizar el estado del usuario."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }

        $respuesta = ControladorUsuarios::ctrActualizarStatusUsuario($id, $status);
        http_response_code($respuesta['status']);
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    /*=========================================
    MÉTODO NO PERMITIDO
    ==========================================*/
    default:
        http_response_code(405);
        echo json_encode([
            "status" => 405,
            "success" => false,
            "message" => "Método no permitido para la ruta de usuarios."
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
}