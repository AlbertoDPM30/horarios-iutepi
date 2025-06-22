<?php

/*=============================================
   CONTROLADORES
=============================================*/

require_once "controladores/usuarios.controlador.php"; // controlador de usuarios
require_once "controladores/profesores.controlador.php"; // controlador de profesores
require_once "controladores/plantilla.controlador.php";

/*=============================================
   MODELOS
=============================================*/

require_once "modelos/usuarios.modelo.php"; // controlador de usuarios
require_once "modelos/profesores.modelo.php"; // controlador de profesores


$plantilla = new ControladorPlantilla();
$plantilla->ctrPlantilla();
