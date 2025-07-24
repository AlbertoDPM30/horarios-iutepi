<?php

// Configurar cabeceras para respuestas JSON
header('Content-Type: application/json; charset=utf-8');

// Obtener método HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

$entrada = json_decode(file_get_contents('php://input'), true);

// Manejar cada método HTTP
switch ($metodo) {

    /*=============================================
    OBTENER HABILIDAD(ES) (GET)
    =============================================*/
    case 'GET':
        $item = null;
        $valor = null;

        if (isset($_GET['id'])) {
            $item = "skill_id";
            $valor = $_GET['id'];
        }

        // Llamar al método del controlador
        $response = ControladorHabilidades::ctrMostrarHabilidades($item, $valor);
        
        // El controlador ya devuelve el formato de respuesta adecuado
        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        break;

    /*=============================================
    CREAR NUEVA HABILIDAD (POST)
    =============================================*/
    case 'POST':
        // Validar que el campo 'skill_name' no esté vacío
        if (empty($entrada['skill_name'])) {
            http_response_code(400); 
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "El campo 'skill_name' es obligatorio para crear una habilidad."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break; 
        }
        
        // Los datos a enviar al controlador
        $datosParaCrear = [
            'skill_name' => $entrada['skill_name']
        ];
        
        // Llamar al método del controlador para crear la habilidad
        $response = ControladorHabilidades::ctrCrearHabilidad($datosParaCrear);

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
        // Validar que los campos 'skill_id' y 'skill_name' no estén vacíos
        if (empty($entrada['skill_id']) || empty($entrada['skill_name'])) {
            http_response_code(400); 
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "Los campos 'skill_id' y 'skill_name' son obligatorios para actualizar una habilidad."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }
        
        // Los datos a enviar al controlador
        $datosParaActualizar = [
            'skill_id' => $entrada['skill_id'],
            'skill_name' => $entrada['skill_name']
        ];
        
        // Llamar al método del controlador para actualizar la habilidad
        $response = ControladorHabilidades::ctrEditarHabilidad($datosParaActualizar);
        
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
        // Validar que el parámetro 'id' esté presente en la URL
        if (!isset($_GET['id'])) {
            http_response_code(400); 
            echo json_encode([
                "status" => 400,
                "success" => false,
                "message" => "Se requiere el parámetro 'id' para eliminar una habilidad."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
        }
        
        $idToDelete = $_GET['id'];
        
        // Llamar al método del controlador para eliminar la habilidad
        $response = ControladorHabilidades::ctrEliminarHabilidad($idToDelete);
        
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