<?php

/*=============================================
   CONTROLADORES
=============================================*/

require_once "controladores/usuarios.controlador.php";
require_once "controladores/locales.controlador.php";
require_once "controladores/planes.controlador.php";
require_once "controladores/plantilla.controlador.php";

/*=============================================
   MODELOS
=============================================*/

require_once "modelos/usuarios.modelo.php";
require_once "modelos/locales.modelo.php";
require_once "modelos/planes.modelo.php";


$plantilla = new ControladorPlantilla();
$plantilla->ctrPlantilla();
