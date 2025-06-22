<?php

/*=============================================
   CONTROLADORES
=============================================*/

require_once "controladores/usuarios.controlador.php"; // controlador de usuarios
require_once "controladores/profesores.controlador.php"; // controlador de profesores
require_once "controladores/router.controlador.php"; // controlador del enrutador

/*=============================================
   MODELOS
=============================================*/

require_once "modelos/usuarios.modelo.php"; // controlador de usuarios
require_once "modelos/profesores.modelo.php"; // controlador de profesores


$router = new ControladorRouter();
$router->ctrRouter();
