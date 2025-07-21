<?php
/*=============================================
CERRAR SESION
=============================================*/
if (isset($_POST["idUsuarioSesion"]) && isset($_POST["tokenSesion"])) {
    
    echo ControladorUsuarios::ctrCerrarSesion(intVal($_POST["idUsuarioSesion"]), $_POST["tokenSesion"]);
    exit;
    
} else {

    // Si no se ha iniciado sesión o los parámetros son incorrectos
    
    header('Content-Type: application/json; charset=utf-8'); //  Establecer cabeceras para JSON + UTF-8
    http_response_code(402);
    echo json_encode([
        "status" => 402,
        "success" => false,
        "mensaje" => "No se pudo cerrar la sesión.",
        "descripcion" => "Parametro invalido o sesion no iniciada."
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}