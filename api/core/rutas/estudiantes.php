<?php

/*=============================================
ENDPOINT DE ESTUDIANTES
=============================================*/
if (isset($_GET["ruta"]) && $_GET["ruta"] === "estudiantes") {

    header('Content-Type: application/json; charset=utf-8');

    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            /*=============================================
            OBTENER ESTUDIANTE(ES)
            =============================================*/
            $item = null;
            $valor = null;

            if (isset($_GET['id'])) {
                // Obtener un Estudiante por su ID
                $item = "student_id";
                $valor = $_GET['id'];
            } elseif (isset($_GET['ci'])) {
                // Obtener un Estudiante por la cédula o codigo
                $item = "ci_code";
                $valor = $_GET['ci'];
            }

            $respuesta = ControladorEstudiantes::ctrMostrarEstudiantes($item, $valor);
            echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'POST':
            /*=============================================
            REGISTRAR NUEVO ESTUDIANTE
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
            $cedulaEstudianteExistente = ControladorEstudiantes::ctrMostrarEstudiantes("ci_code", $datos['ci_code']);
            if ($cedulaEstudianteExistente['success'] !== true && !empty($cedulaEstudianteExistente['data'])) {
                echo json_encode([
                    "status" => 409,
                    "success" => false,
                    "message" => "Esta Cédula de Identidad ya se encuentra registrada para otro Estudiante."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }
            
            $respuesta = ControladorEstudiantes::ctrCrearEstudiante($datos); // Enviar datos al controlador
            echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'PUT':
            /*=============================================
            EDITAR ESTUDIANTE
            =============================================*/
            $datos = json_decode(file_get_contents('php://input'), true);

            if (empty($datos['student_id'])) {
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "El 'student_id' es obligatorio para actualizar un Estudiante."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            $respuesta = ControladorEstudiantes::ctrEditarEstudiante($datos); // Enviar datos al controlador
            echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;

        case 'DELETE':
            /*=============================================
            ELIMINAR ESTUDIANTE
            =============================================*/
            if (!isset($_GET['id'])) {
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "El 'id' del Estudiante es obligatorio para eliminar."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                break;
            }

            $respuesta = ControladorEstudiantes::ctrEliminarEstudiante($_GET['id']); // Enviar ID al controlador
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