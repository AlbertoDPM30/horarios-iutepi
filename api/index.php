<?php
/*=============================================
   CONTROLADORES
=============================================*/

require_once "controladores/usuarios.controlador.php"; // controlador de usuarios
require_once "controladores/estudiantes.controlador.php"; // controlador de estudiantes
require_once "controladores/profesores.controlador.php"; // controlador de profesores
require_once "controladores/habilidades.controlador.php"; // controlador de habilidades
require_once "controladores/materias.controlador.php"; // controlador de materias
require_once "controladores/router.controlador.php"; // controlador del enrutador

/*=============================================
   MODELOS
=============================================*/

require_once "modelos/usuarios.modelo.php"; // controlador de usuarios
require_once "modelos/estudiantes.modelo.php"; // controlador de estudiantes
require_once "modelos/profesores.modelo.php"; // controlador de profesores
require_once "modelos/habilidades.modelo.php"; // controlador de habilidades
require_once "modelos/materias.modelo.php"; // controlador de materias


$router = new ControladorRouter();
$router->ctrRouter();
