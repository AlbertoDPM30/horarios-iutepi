<?php

// Configurar cabeceras para respuestas JSON
header('Content-Type: application/json; charset=utf-8');

// Obtener método HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

// Procesar datos de entrada (JSON)
$entrada = json_decode(file_get_contents('php://input'), true);

if ($metodo === 'POST') {
    
    $headers = apache_request_headers();
    $authHeader = $headers['Authorization'] ?? '';
    $token_from_header = null;
    if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
        $token_from_header = $matches[1];
    }

    $user_id_from_client = $entrada['user_id'] ?? null; 
    
    $respuesta = ControladorUsuarios::ctrCerrarSesion($user_id_from_client, $token_from_header);
    
    http_response_code($respuesta['status']);
    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} else {

    // Si no es un método POST, responder con error 405
    http_response_code(405);
    echo json_encode([
        "status" => 405,
        "success" => false,
        "message" => "Método no permitido para esta ruta."
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}