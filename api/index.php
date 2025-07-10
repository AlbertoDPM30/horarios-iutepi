<?php
// Permitir origen específico o cualquiera 
header("Access-Control-Allow-Origin: *"); 
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
/*=============================================
   CONTROLADORES
=============================================*/

require_once "controladores/usuarios.controlador.php"; // controlador de usuarios
require_once "controladores/profesores.controlador.php"; // controlador de profesores
require_once "controladores/habilidades.controlador.php"; // controlador de habilidades
require_once "controladores/router.controlador.php"; // controlador del enrutador

/*=============================================
   MODELOS
=============================================*/

require_once "modelos/usuarios.modelo.php"; // controlador de usuarios
require_once "modelos/profesores.modelo.php"; // controlador de profesores
require_once "modelos/habilidades.modelo.php"; // controlador de habilidades


$router = new ControladorRouter();
$router->ctrRouter();
