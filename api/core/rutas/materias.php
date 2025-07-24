<?php

/*=============================================
RUTAS PARA EL RECURSO DE MATERIAS
=============================================*/
if (isset($_GET["ruta"]) && $_GET["ruta"] === "materias") {

    header('Content-Type: application/json; charset=utf-8');

    switch ($_SERVER['REQUEST_METHOD']) {
        
        /*=========================================
        OBTENER MATERIA(S)
        ==========================================*/
        case 'GET':
            $item = null;
            $valor = null;

            if (isset($_GET['id'])) {
                // Si se solicita una materia específica por ID
                $item = "subject_id";
                $valor = $_GET['id'];
            } elseif (isset($_GET['name'])) {
                // Si se solicita una materia específica por nombre (útil para validación)
                $item = "name";
                $valor = $_GET['name'];
            }

            $response = ControladorMaterias::ctrMostrarMaterias($item, $valor);
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        /*=========================================
        REGISTRAR NUEVA MATERIA
        ==========================================*/
        case 'POST':
            $datos = json_decode(file_get_contents('php://input'), true);

            // Validar campos obligatorios
            if (empty($datos['name']) || empty($datos['duration_hours']) || empty($datos['semester'])) {
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "Los campos 'name', 'duration_hours' y 'semester' son obligatorios."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            // Validar si la materia ya existe por nombre
            $materiaExistente = ControladorMaterias::ctrMostrarMaterias("name", $datos['name']);
            if ($materiaExistente['success'] && $materiaExistente['data']) {
                echo json_encode([
                    "status" => 409, 
                    "success" => false,
                    "message" => "Esta Materia ya se encuentra registrada."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            $response = ControladorMaterias::ctrCrearMateria($datos); // Pasar los datos al controlador
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        /*=========================================
        EDITAR MATERIA
        ==========================================*/
        case 'PUT':
            $datos = json_decode(file_get_contents('php://input'), true);

            if (empty($datos['subject_id'])) {
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "El 'subject_id' es obligatorio para actualizar una materia."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            $response = ControladorMaterias::ctrEditarMateria($datos); // Enviar los datos al controlador
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        /*=========================================
        ACTUALIZAR ESTADO ASIGNACIÓN MATERIA
        ==========================================*/
        case 'PATCH':
            $datos = json_decode(file_get_contents('php://input'), true);

            if ($datos['id'] === null || $datos['is_assigned'] === null) {
                http_response_code(400);
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "Se requiere 'id' e 'is_assigned' para actualizar el estado del asignación de la materia."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            $respuesta = ControladorMaterias::ctrEditarMateria($id, $asignar);
            http_response_code($respuesta['status']);
            echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        /*=========================================
        ELIMINAR MATERIA
        ==========================================*/
        case 'DELETE':
            if (!isset($_GET['id'])) {
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "El 'id' de la materia es obligatorio para eliminar."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            $response = ControladorMaterias::ctrEliminarMateria($_GET['id']); // Enviar el ID al controlador
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        default:
            // Método no permitido
            http_response_code(405); // Method Not Allowed
            echo json_encode([
                "status" => 405,
                "success" => false,
                "message" => "Método no permitido para esta ruta."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
    }
}