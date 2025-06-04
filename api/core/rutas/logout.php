<?php
/*=============================================
CERRAR SESION
=============================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION["logged"]) == "ok") {
    
    // Cerrar sesión y limpiar las variables de sesión
    $_SESSION["logged"] = null;
    $_SESSION["user_id"] = null;
    $_SESSION["nombres"] = null;
    $_SESSION["apellidos"] = null;
    $_SESSION["username"] = null;
    $_SESSION["ci"] = null;

    session_destroy();

    http_response_code(201);
    echo json_encode([
        "status" => 201,
        "success" => true,
        "message" => "Sesion cerrada correctamente."
    ]);

} else {
    
    // Si no se ha iniciado sesión o los parámetros son incorrectos
    http_response_code(402);
    echo json_encode([
        "status" => 402,
        "success" => false,
        "mensaje" => "No se pudo cerrar la sesión.",
        "descripcion" => "Parametro invalido o sesion no iniciada."
    ]);
}