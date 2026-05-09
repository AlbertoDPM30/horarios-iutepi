<?php

/*=============================================
ENDPOINT DE MODULOS
=============================================*/
if (isset($_GET["ruta"]) && $_GET["ruta"] === "modulos") {

    header('Content-Type: application/json; charset=utf-8');

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            /*=============================================
            OBTENER MODULO(ES)
            =============================================*/
            $item = null;
            $valor = null;

            if (isset($_GET['id'])) {
                // Obtener un Modulo por su ID
                $item = "module_id";
                $valor = $_GET['id'];
            }

            $respuesta = ControladorModulos::ctrMostrarModulos($item, $valor);
            echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'POST':
            /*=============================================
            REGISTRAR NUEVO MODULO
            =============================================*/
            $datos = json_decode(file_get_contents('php://input'), true);

            // Validar campos requeridos
            if (empty($datos['name']) || empty($datos['description']) || empty($datos['route'])) {
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "Los campos 'name' y 'description' y 'route' son obligatorios."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            // Validar si el modulo ya existe
            $rutaModuloExistente = ControladorModulos::ctrMostrarModulos("route", $datos['route']);
            if ($rutaModuloExistente['success'] !== true && !empty($rutaModuloExistente['data'])) {
                echo json_encode([
                    "status" => 409,
                    "success" => false,
                    "message" => "Esta Ruta ya se encuentra registrada para otro Modulo."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }
            
            $respuesta = ControladorModulos::ctrCrearModulo($datos); // Enviar datos al controlador
            echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'PUT':
            /*=============================================
            EDITAR MODULO
            =============================================*/
            $datos = json_decode(file_get_contents('php://input'), true);

            if (empty($datos['module_id'])) {
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "El 'module_id' es obligatorio para actualizar un Modulo."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            $respuesta = ControladorModulos::ctrEditarModulo($datos); // Enviar datos al controlador
            echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'DELETE':
            /*=============================================
            ELIMINAR MODULO
            =============================================*/
            if (!isset($_GET['id'])) {
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "El 'id' del Modulo es obligatorio para eliminar."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            $respuesta = ControladorModulos::ctrEliminarModulo($_GET['id']); // Enviar ID al controlador
            echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        default:
            // Método no permitido
            http_respuesta_code(405);
            echo json_encode([
                "status" => 405,
                "success" => false,
                "message" => "Método no permitido para esta ruta."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
    }
}