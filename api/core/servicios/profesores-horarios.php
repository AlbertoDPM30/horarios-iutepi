<?php

$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$pathSegments = explode('/', trim($requestUri, '/'));

$resource = end($pathSegments);

if ($requestMethod == 'OPTIONS') {
    http_response_code(200);
    exit;
}

switch ($resource) {
    case 'profesores':
        $profesores = ModeloProfesores::mdlMostrarProfesores("teachers", null, null);
        echo json_encode(["status" => 200, "success" => true, "data" => $profesores]);
        break;

    // case 'profesores-materias':
    //     $response = [];
    //     if ($requestMethod === "GET") {
    //         $profesorId = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;
    //         $response = ControladorAsignacion::ctrMostrarMateriasElegibles($profesorId);
    //     } elseif ($requestMethod === "POST") {
    //         $data = json_decode(file_get_contents("php://input"), true);
    //         $profesorId = isset($data['teacher_id']) ? $data['teacher_id'] : null;
    //         $subjectIds = isset($data['subject_ids']) ? $data['subject_ids'] : [];
    //         $response = ControladorAsignacion::ctrGuardarAsignaciones($profesorId, $subjectIds);
    //     } else {
    //         http_response_code(405);
    //         $response = [
    //             "status" => 405,
    //             "success" => false,
    //             "message" => "Método no permitido."
    //         ];
    //     }
    //     echo json_encode($response);
    //     break;
        
    case 'materias-asignadas':
        if ($requestMethod === 'GET') {
            $profesorId = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;
            $response = ControladorHorario::ctrMostrarMateriasAsignadas($profesorId);
            echo json_encode($response);
        }
        break;

    case 'generar-horario':
        if ($requestMethod === 'GET') {
            $profesorId = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;
            $response = ControladorHorario::ctrGenerarHorario($profesorId);
            echo json_encode($response);
        }
        break;

    case 'confirmar-horario':
        if ($requestMethod === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $horario = isset($data['horario']) ? $data['horario'] : [];
            $teacherId = isset($data['teacher_id']) ? $data['teacher_id'] : null;
            $response = ControladorHorario::ctrConfirmarHorario($horario, $teacherId);
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        break;

    case 'horarios':
        if ($requestMethod === 'GET') {
            $teacherId = isset($_GET['teacher_id']) ? $_GET['teacher_id'] : null;
            $response = ControladorHorario::ctrMostrarHorarios($teacherId);
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        } elseif ($requestMethod === 'DELETE') {
            $scheduleId = isset($_GET['schedule_id']) ? $_GET['schedule_id'] : null;
            $response = ControladorHorario::ctrEliminarHorario($scheduleId);
            echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
        break;

    default:
        http_response_code(404);
        echo json_encode(["status" => 404, "success" => false, "message" => "Recurso no encontrado."]);
        break;
}
