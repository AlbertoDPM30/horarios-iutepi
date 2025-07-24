<?php

require_once "config/autenticacion.php";
require_once "config/authMiddleware.php";

/*=============================================
MANEJO DE CORS
=============================================*/
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/*=============================================
RUTAS PÚBLICAS
=============================================*/
$publicRoutes = ["login", "logout", "status"]; 

/*=============================================
VALIDAR TOKEN PARA RUTAS PRIVADAS
=============================================*/
$ruta = isset($_GET["ruta"]) ? $_GET["ruta"] : null;

// Si la ruta no es pública y existe
if ($ruta && !in_array($ruta, $publicRoutes)) {
    AuthMiddleware::handle(); 
}

/*=============================================
ENRUTAMIENTO DE SOLICITUDES
=============================================*/

if (isset($ruta)) {
    switch ($ruta) {

        // Rutas Públicas
        case "login":
            include "rutas/login.php";
            break;
            
        case "logout":
            include "rutas/logout.php";
            break;

        case "usuarios":
            include "rutas/usuarios.php";
            break;

        // Rutas Protegidas
        case "profesores":
            AuthMiddleware::handle();
            include "rutas/profesores.php"; 
            break;

        case "habilidades":
            AuthMiddleware::handle(); 
            include "rutas/habilidades.php";
            break;

        case "materias":
            AuthMiddleware::handle();
            include "rutas/materias.php";
            break;

        // Validar estado de la API
        case "status":
            echo json_encode(["status" => "API is running"]);
            break;

        default:
            http_response_code(404);
            echo json_encode([
                "status" => "404",
                "success" => false,
                "message" => "Ruta no encontrada."
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            break;
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "400",
        "success" => false,
        "message" => "Ruta no especificada."
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}