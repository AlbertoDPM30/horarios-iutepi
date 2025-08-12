<?php

// Configurar cabeceras para respuestas JSON
header('Content-Type: application/json; charset=utf-8');

// Obtener método HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

$entrada = json_decode(file_get_contents('php://input'), true);

// Manejar cada método HTTP
switch ($metodo) {

    /*=============================================
    OBTENER HABILIDAD(ES) PARA UNA(S) MATERIA(S) (GET)
    =============================================*/
    case 'GET':
        $item = null;
        $valor = null;

        if (isset($_GET['subject_skill_id'])) {
            $item = "subject_skill_id";
            $valor = $_GET['subject_skill_id'];
        }

        if (isset($_GET['subject_id'])) {
            $item = "subject_id";
            $valor = $_GET['subject_id'];
        }

        // Llamar al método del controlador
        $response = ControladorHabilidades::ctrMostrarMateriasHabilidades($item, $valor);
        
        // El controlador ya devuelve el formato de respuesta adecuado
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    /*=============================================
    CREAR NUEVA HABILIDAD (POST)
    =============================================*/
    case 'POST':
        // Validar que el campo 'skill_id' y el campo 'subject_id' no estén vacíos
        if (empty($entrada['skill_id']) || empty($entrada['subject_id'])) {
            http_response_code(400); 
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "El campo 'skill_id' y 'subject_id' son obligatorios para crear la habilidad de una materia."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break; 
        }
        
        // Los datos a enviar al controlador
        $datosParaCrear = [
            'subject_id' => $entrada['subject_id'],
            'skill_id' => $entrada['skill_id'],
            'min_stars' => $entrada['min_stars'],
        ];
        
        // Llamar al método del controlador para crear la habilidad de la materia
        $response = ControladorHabilidades::ctrCrearMateriasHabilidad($datosParaCrear);

        if ($response['success']) {
            http_response_code(201); 
        } else {
            http_response_code($response['status']);
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    /*=============================================
    ACTUALIZAR HABILIDAD (PUT)
    =============================================*/
    case 'PUT':
        // Validar que los campos 'skill_id' y 'subject_skill_id' no estén vacíos
        if (empty($entrada['subject_skill_id'])) {
            http_response_code(400); 
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "El campo 'subject_skill_id' es obligatorio para actualizar la habilidad de una materia."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }
        
        // Los datos a enviar al controlador
        $datosParaActualizar = [
            'subject_skill_id' => $entrada['subject_skill_id'],
            'subject_id' => $entrada['subject_id'],
            'skill_id' => $entrada['skill_id'],
            'min_stars' => $entrada['min_stars']
        ];
        
        // Llamar al método del controlador para actualizar la habilidad de la materia
        $response = ControladorHabilidades::ctrEditarMateriasHabilidad($datosParaActualizar);
        
        if ($response['success']) {
            http_response_code(200); 
        } else {
            http_response_code($response['status']);
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
        
    /*=============================================
    ELIMINAR HABILIDAD (DELETE)
    =============================================*/
    case 'DELETE':
        // Validar que el parámetro 'subject_skill_id' esté presente en la URL
        if (!isset($_GET['subject_skill_id'])) {
            http_response_code(400); 
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "Se requiere el parámetro 'subject_skill_id' para eliminar una habilidad."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }
        
        $idToDelete = $_GET['subject_skill_id'];
        
        // Llamar al método del controlador para eliminar la habilidad
        $response = ControladorHabilidades::ctrEliminarMateriasHabilidad($idToDelete);
        
        if ($response['success']) {
            http_response_code(200); 
        } else {
            http_response_code($response['status']);
        }
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    /*=============================================
    MÉTODO NO PERMITIDO
    =============================================*/
    default:
        http_response_code(405); 
        echo json_encode([
            "status" => 405,
            "success" => false,
            "message" => "Método no permitido para esta ruta."
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;
}

?>