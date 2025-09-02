<?php

/*=============================================
ENDPOINT PARA GESTIONAR HORARIOS Y PROFESORES
=============================================*/
header('Content-Type: application/json; charset=utf-8');

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        /*=============================================
        OBTENER LISTA DE PROFESORES (GET)
        =============================================*/
        try {
            // Llama a la función del controlador que obtiene la lista de todos los profesores
            $respuesta = ControladorProfesores::ctrMostrarProfesores(null, null);

            // Responde con la lista de profesores o un mensaje de error
            http_response_code($respuesta['status']);
            echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                "status" => 500,
                "success" => false,
                "message" => "Error del servidor: " . $e->getMessage()
            ]);
        }
        break;

    case 'POST':
        /*=============================================
        GENERAR HORARIO DE UN PROFESOR INDIVIDUAL (POST)
        =============================================*/
        $data = json_decode(file_get_contents('php://input'), true);

        // Validar que se ha enviado el ID del profesor
        if (!isset($data['teacher_id'])) {
            http_response_code(400);
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "ID de profesor no proporcionado en el cuerpo de la petición."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }

        $profesorId = $data['teacher_id'];

        // Llama al nuevo método del controlador que genera un horario para un profesor
        $horarioProfesor = ControladorGenerarHorarios::ctrGenerarHorarioProfesorIndividual($profesorId);

        // Responde con el horario o un mensaje de error
        http_response_code($horarioProfesor['status']);
        echo json_encode($horarioProfesor, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
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