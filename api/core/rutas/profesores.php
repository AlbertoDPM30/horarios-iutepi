<?php

/*=============================================
ENDPOINT DE PROFESORES
=============================================*/
if (isset($_GET["ruta"]) && $_GET["ruta"] === "profesores") {

    header('Content-Type: application/json; charset=utf-8');

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            /*=============================================
            OBTENER PROFESOR(ES)
            =============================================*/
            $item = null;
            $valor = null;

            if (isset($_GET['id'])) {
                // Obtener un profesor por su ID
                $item = "teacher_id";
                $valor = $_GET['id'];
            } elseif (isset($_GET['ci'])) {
                // Obtener un profesor por la cédula o codigo
                $item = "ci_code";
                $valor = $_GET['ci'];
            }

            $response = ControladorProfesores::ctrMostrarProfesores($item, $valor);
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'POST':
            /*=============================================
            REGISTRAR NUEVO PROFESOR
            =============================================*/
            $datos = json_decode(file_get_contents('php://input'), true);

            // Validar campos requeridos
            if (empty($datos['name']) || empty($datos['ci_code'])) {
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "Los campos 'name' y 'ci_code' son obligatorios."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            // Validar si la CI existe
            $cedulaProfesorExistente = ControladorProfesores::ctrMostrarProfesores("ci_code", $datos['ci_code']);
            if ($cedulaProfesorExistente['success'] !== true && !empty($cedulaProfesorExistente['data'])) {
                echo json_encode([
                    "status" => 409,
                    "success" => false,
                    "message" => "Esta Cédula de Identidad ya se encuentra registrada para otro profesor."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }
            
            $response = ControladorProfesores::ctrCrearProfesor($datos); // Enviar datos al controlador
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'PUT':
            /*=============================================
            EDITAR PROFESOR
            =============================================*/
            $datos = json_decode(file_get_contents('php://input'), true);

            if (empty($datos['teacher_id'])) {
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "El 'teacher_id' es obligatorio para actualizar un profesor."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            $response = ControladorProfesores::ctrEditarProfesor($datos); // Enviar datos al controlador
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'DELETE':
            /*=============================================
            ELIMINAR PROFESOR
            =============================================*/
            if (!isset($_GET['id'])) {
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "El 'id' del profesor es obligatorio para eliminar."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            $response = ControladorProfesores::ctrEliminarProfesor($_GET['id']); // Enviar ID al controlador
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        default:
            // Método no permitido
            http_response_code(405);
            echo json_encode([
                "status" => 405,
                "success" => false,
                "message" => "Método no permitido para esta ruta."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
    }
}