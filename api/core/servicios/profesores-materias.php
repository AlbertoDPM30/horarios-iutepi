<?php

/*=============================================
ENDPOINT DE PROFESORES-HABILIDADES
=============================================*/
header('Content-Type: application/json; charset=utf-8');

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        /*=============================================
        OBTENER HORARIO DEL PROFESOR (GET)
        =============================================*/

        $itemSubject = null;
        $valorSubject = null;
        $itemTeacher = null;
        $valorTeacher = null;
        $itemSkill = null;
        $valorSkill = null;

        if (isset($_GET['is_saturday']) && $_GET['is_saturday'] == 'true') {

            $respuesta = ControladorGenerarHorarios::ctrMostrarHabilidadesProfesores($itemTeacher, $itemSkill, $valorTeacher, $valorSkill);
            $itemTeacher = "teacher_id";
            $valorTeacher = $_GET['teacher_id'];
        }

        $respuesta = ControladorHabilidades::ctrMostrarHabilidadesProfesores($itemTeacher, $itemSkill, $valorTeacher, $valorSkill);

        // Se crea un array para almacenar todas las respuestas
        $all_responses = [];

        // Se recorre el array de resultados
        foreach ($respuesta['data'] AS $key => $data) {
            
            $respuestaProfesor = ControladorProfesores::ctrMostrarProfesores("teacher_id", $data['teacher_id']);
            $respuestaHabilidad = ControladorHabilidades::ctrMostrarHabilidades("skill_id", $data['skill_id']);

            // Se añade cada conjunto de datos al array
            $all_responses[] = [
                "teacher_skill_id" => $data['teacher_skill_id'],
                "teacher_id" => $data['teacher_id'],
                "profesor" => $respuestaProfesor['data']["name"],
                "skill_id" => $data['skill_id'],
                "habilidad" => $respuestaHabilidad['data']["skill_name"],
                "stars" => $data['stars']
            ];
        }

        // Se envía un solo objeto JSON con todos los datos
        echo json_encode([
            "status" => $respuesta["status"],
            "success" => $respuesta["success"],
            "data" => $all_responses
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    case 'POST':
            /*=============================================
            GENERAR HORARIOS DE PROFESORES (POST)
            =============================================*/
            $datos = json_decode(file_get_contents('php://input'), true);

            // Validar campos requeridos
            if (isset($datos['is_saturday']) && $datos['is_saturday'] === true) {

                // $respuesta = ControladorGenerarHorarios::ctrGenerarHorariosProfesoresSabatino($datos); // Enviar datos al controlador
                echo json_encode([
                    "status" => 400,
                    "success" => false,
                    "message" => "No se puede generar horarios para el día sábado."
                ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                exit;
                
            } else {

                $horariosProfesoresSemana = ControladorGenerarHorarios::ctrGenerarHorariosProfesoresSemana();

                http_response_code($horariosProfesoresSemana['status']);
                echo json_encode($horariosProfesoresSemana, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

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