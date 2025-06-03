<?php
/*=============================================
REGISTRAR NUEVO USUARIO
=============================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["nuevoUsername"])) {
  
  $encriptar = crypt($_POST["nuevoPassword"], '$2a$07$asxx54ahjppf45sd87a5a4dDDGsystemdev$'); // Encriptar la contraseña

  // Validar que los campos no estén vacíos
  if (empty($_POST["nuevoNombres"]) || empty($_POST["nuevoApellidos"]) || empty($_POST["nuevoCI"]) || empty($_POST["nuevoUsername"]) || empty($_POST["nuevoPassword"])) {
    echo json_encode(["mensaje" => "Todos los campos son obligatorios."]);
    exit;
  }

  // Crear un array con los datos del nuevo usuario
  $datos = array(
    "first_name" => trim($_POST["nuevoNombres"]),
    "last_name" => trim($_POST["nuevoApellidos"]),
    "ci" => strtolower(trim($_POST["nuevoCI"])),
    "username" => strtolower(trim($_POST["nuevoUsername"])),
    "password" => $encriptar
  );

  // Enviar los datos al controlador para crear el usuario
  $respuesta = ControladorUsuarios::ctrCrearUsuario($datos);

  // Verificar la respuesta del controlador
  if ($respuesta == "ok") {
    echo json_encode([
      "status" => 201,
      "success" => true,
      "data" => [
        "nombres" => $datos["first_name"],
        "apellidos" => $datos["last_name"],
        "cedula" => $datos["ci"],
        "usuario" => $datos["username"]
      ],
      "mensaje" => "usuario creado correctamente"
    ]);
  } else {

    echo json_encode([
      "status" => 400,
      "success" => false,
      "data" => null,
      "mensaje" => "error al crear el usuario"
    ]);
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
