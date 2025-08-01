<?php

/*=============================================
ENDPOINT DE PROFESORES-HABILIDADES
=============================================*/
header('Content-Type: application/json; charset=utf-8');

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        /*=============================================
        OBTENER HABILIDADES DEL PROFESOR
        =============================================*/

        $itemTeacher = null;
        $valorTeacher = null;
        $itemSkill = null;
        $valorSkill = null;

        if (isset($_GET['teacher_id'])) {

            $itemTeacher = "teacher_id";
            $valorTeacher = $_GET['teacher_id'];
        }

        if (isset($_GET['skill_id'])) {

            $itemSkill ="skill_id";
            $valorSkill = $_GET['skill_id'];
        }

        $respuesta = ControladorHabilidades::ctrMostrarHabilidadesProfesores($itemTeacher, $itemSkill, $valorTeacher, $valorSkill);

        // Enviamos los datos completos al cliente
        foreach ($respuesta['data'] AS $key => $data) {
            
            $respuestaProfesor = ControladorProfesores::ctrMostrarProfesores("teacher_id", $data['teacher_id']);
            $respuestaHabilidad = ControladorHabilidades::ctrMostrarHabilidades("skill_id", $data['skill_id']);

            echo json_encode([
                "status" => $respuesta["status"],
                "success" => $respuesta["success"],
                "data" => [
                    "teacher_skill_id" => $data['teacher_skill_id'],
                    "teacher_id" => $data['teacher_id'],
                    "profesor" => $respuestaProfesor['data']["name"],
                    "skill_id" => $data['skill_id'],
                    "habilidad" => $respuestaHabilidad['data']["skill_name"],
                    "stars" => $data['stars']
                ]
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        break;

    case 'POST':
        /*=============================================
        REGISTRAR NUEVA HABILIDAD AL PROFESOR
        =============================================*/
        $datos = json_decode(file_get_contents('php://input'), true);

        // Validar campos requeridos
        if (empty($datos['teacher_id']) || empty($datos['skill_id'])) {
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "Los campos 'teacher_id' y 'skill_id' son obligatorios."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }

        $validarItemTeacher = isset($_GET['teacher_id']) ? "teacher_id" : null;
        $validarValorTeacher = $datos['teacher_id'];
        $validarItemSkill = isset($_GET['skill_id']) ? "skill_id" : null;
        $validarValorSkill = $datos['skill_id'];

        // Validar si la habilidad ya fue asignada
        $habilidadProfesorAsignada = ControladorHabilidades::ctrMostrarHabilidadesProfesores($validarItemTeacher, $validarItemSkill, $validarValorTeacher, $validarValorSkill);
        if ($habilidadProfesorAsignada['success'] !== true && !empty($habilidadProfesorAsignada['data'])) {
            echo json_encode([
                "status" => 409,
                "success" => false,
                "message" => "Esta Habilidad ya se encuentra registrada para este profesor."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }
        
        $respuesta = ControladorHabilidades::ctrCrearHabilidadProfesor($datos); // Enviar datos al controlador
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    case 'PUT':
        /*=============================================
        EDITAR HABILIDAD DEL PROFESOR
        =============================================*/
        $datos = json_decode(file_get_contents('php://input'), true);

        if (empty($datos['teacher_id']) || empty($datos['skill_id'])) {
            
            http_response_code(400);
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "El 'teacher_id' es obligatorio para actualizar un profesor."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }

        $respuesta = ControladorHabilidades::ctrEditarHabilidadProfesor($datos); // Enviar datos al controlador        
        http_response_code(200);
        echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    case 'DELETE':
        /*=============================================
        ELIMINAR PROFESOR
        =============================================*/
        if (!isset($_GET['teacher_skill_id'])) {
            
            http_response_code(400);
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "El 'id' del profesor y el 'id' de la habilidad es obligatorio para eliminar."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }

        $respuesta = ControladorHabilidades::ctrEliminarHabilidadProfesor($_GET['teacher_skill_id']); // Enviar ID's al controlador
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