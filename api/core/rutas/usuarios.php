<?php

// require_once "../controladores/usuarios.controlador.php";
// require_once "../modelos/usuarios.modelo.php";

// class RutaUsuarios
// {

// 	/*=============================================
// 	EDITAR USUARIO
// 	=============================================*/

// 	public $idUsuario;

// 	public function ajaxEditarUsuario()
// 	{

// 		$item = "id";
// 		$valor = $this->idUsuario;

// 		$respuesta = ControladorUsuarios::ctrMostrarUsuarios($item, $valor);

// 		echo json_encode($respuesta);
// 	}

// 	/*=============================================
// 	ACTIVAR USUARIO
// 	=============================================*/

// 	public $activarUsuario;
// 	public $activarId;


// 	public function ajaxActivarUsuario()
// 	{

// 		$tabla = "usuarios";

// 		$item1 = "status";
// 		$valor1 = $this->activarUsuario;

// 		$item2 = "id";
// 		$valor2 = $this->activarId;

// 		$respuesta = ModeloUsuarios::mdlActualizarUsuario($tabla, $item1, $valor1, $item2, $valor2);
// 	}

// 	/*=============================================
// 	VALIDAR NO REPETIR USUARIO
// 	=============================================*/

// 	public $validarUsuario;

// 	public function ajaxValidarUsuario()
// 	{

// 		$item = "usuario";
// 		$valor = $this->validarUsuario;

// 		$respuesta = ControladorUsuarios::ctrMostrarUsuarios($item, $valor);

// 		echo json_encode($respuesta);
// 	}
	
// 	/*=============================================
// 	ELIMINAR USUARIO
// 	=============================================*/

// 	public $idEliminarUsuario;

// 	public function ajaxEliminarUsuario()
// 	{

// 		$respuesta = ControladorUsuarios::ctrEliminarUsuario();

// 		echo json_encode($respuesta);
// 	}

// }

/*=============================================
REGISTRAR NUEVO USUARIO
=============================================*/
if (isset($_POST["nuevoUsername"])) {

  $encriptar = crypt($_POST["nuevoPassword"], '$2a$07$asxx54ahjppf45sd87a5a4dDDGsystemdev$');

  $datos = array(
    "first_name" => trim($_POST["nuevoNombres"]),
    "last_name" => trim($_POST["nuevoApellidos"]),
    "ci" => strtolower(trim($_POST["nuevoCI"])),
    "username" => strtolower(trim($_POST["nuevoUsername"])),
    "password" => $encriptar
  );

  $respuesta = ControladorUsuarios::ctrCrearUsuario($datos);

  if ($respuesta == "ok") {
    echo json_encode(["mensaje" => "usuario creado correctamente"]);
  } else {
    echo json_encode(["mensaje" => "error al crear el usuario"]);
  }

}

/*=============================================
EDITAR USUARIO
=============================================*/
// if (isset($_POST["idUsuario"])) {

// 	$editar = new RutaUsuarios();
// 	$editar->idUsuario = $_POST["idUsuario"];
// 	$editar->ajaxEditarUsuario();
// }

/*=============================================
ACTIVAR USUARIO
=============================================*/

// if (isset($_POST["activarUsuario"])) {

// 	$activarUsuario = new RutaUsuarios();
// 	$activarUsuario->activarUsuario = $_POST["activarUsuario"];
// 	$activarUsuario->activarId = $_POST["activarId"];
// 	$activarUsuario->ajaxActivarUsuario();
// }

/*=============================================
VALIDAR NO REPETIR USUARIO
=============================================*/

// if (isset($_POST["validarUsuario"])) {

// 	$valUsuario = new RutaUsuarios();
// 	$valUsuario->validarUsuario = $_POST["validarUsuario"];
// 	$valUsuario->ajaxValidarUsuario();
// }

/*=============================================
ELMINAR USUARIO
=============================================*/
// if (isset($_POST["idEliminarUsuario"])) {

// 	$Eliminar = new RutaUsuarios();
// 	$Eliminar->idEliminarUsuario = $_POST["idEliminarUsuario"];
// 	$Eliminar->ajaxEliminarUsuario();
// }
