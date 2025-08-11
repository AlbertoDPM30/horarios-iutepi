<?php

/*=============================================
ENDPOINT DE PROFESORES-DIPONIBILIDAD
=============================================*/
header('Content-Type: application/json; charset=utf-8');

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        /*=============================================
        OBTENER DIPONIBILIDAD DEL PROFESOR
        =============================================*/

        $itemTeacher = null;
        $valorTeacher = null;
        $itemAvailability = null;
        $valorAvailability = null;

        if (isset($_GET['teacher_id'])) {

            $itemTeacher = "teacher_id";
            $valorTeacher = $_GET['teacher_id'];
        }

        $respuesta = ControladorProfesores::ctrMostrarDisponibilidadProfesores($itemTeacher, $itemAvailability, $valorTeacher, $valorAvailability);

        // Enviamos los datos completos al cliente
        foreach ($respuesta['data'] AS $key => $data) {
            
            $respuestaProfesor = ControladorProfesores::ctrMostrarProfesores("teacher_id", $data['teacher_id']);

            echo json_encode([
                "status" => $respuesta["status"],
                "success" => $respuesta["success"],
                "data" => [
                    "availability_id" => $data['availability_id'],
                    "teacher_id" => $data['teacher_id'],
                    "profesor" => $respuestaProfesor['data']["name"],
                    "dia_semana" => $data["day_of_week"],
                    "hora_inicio" => $data["start_time"],
                    "hora_final" => $data["end_time"]
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        break;

    case 'POST':
        /*=============================================
        REGISTRAR NUEVA DISPONIBILIDAD AL PROFESOR
        =============================================*/
        $datos = json_decode(file_get_contents('php://input'), true);

        // Validar campos requeridos
        if (empty($datos['teacher_id']) || empty($datos['day_of_week']) || empty($datos['start_time']) || empty($datos['end_time'])) {
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "Los campos 'teacher_id', 'day_of_week', 'start_time', 'end_time' son obligatorios."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }

        $respuesta = ControladorProfesores::ctrCrearDisponibilidadProfesor($datos); // Enviar datos al controlador
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    case 'PUT':
        /*=============================================
        EDITAR DISPONIBILIDAD DEL PROFESOR
        =============================================*/
        $datos = json_decode(file_get_contents('php://input'), true);

        if (empty($datos['teacher_id']) || empty($datos['availability_id'])) {
            
            http_response_code(400);
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "El 'teacher_id' es obligatorio para actualizar la disponibilidad de un profesor."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }

        $respuesta = ControladorProfesores::ctrEditarDisponibilidadProfesor($datos); // Enviar datos al controlador        
        http_response_code(200);
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    case 'DELETE':
        /*=============================================
        ELIMINAR DISPONIBILIDAD DEL PROFESOR
        =============================================*/
        if (!isset($_GET['availability_id'])) {
            
            http_response_code(400);
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "El 'id' del profesor es obligatorio para eliminar."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }

        $respuesta = ControladorProfesores::ctrEliminarDisponibilidadProfesor($_GET['availability_id']); // Enviar ID's al controlador
        http_response_code(200);
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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