<?php

// Configurar cabeceras para respuestas JSON
header('Content-Type: application/json; charset=utf-8');

// Obtener método HTTP
$metodo = $_SERVER['REQUEST_METHOD'];

// Procesar datos de entrada (JSON)
$entrada = json_decode(file_get_contents('php://input'), true);

if ($metodo === 'POST') {
    $username = $entrada['username'] ?? null;
    $password = $entrada['password'] ?? null;
    
    $respuesta = ControladorUsuarios::ctrIniciarSesion($username, $password);
    
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