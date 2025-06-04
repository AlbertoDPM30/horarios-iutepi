<?php
// Protegemos la ruta
if(isset($_SESSION["logged"]) == "ok") {

  /*=============================================
  OBTENER USUARIO(S) (POST || GET)
  =============================================*/
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['obtenerIdUsuario'])) {

    // Si se quiere obtener un solo usuario se le da valor a los parametros
    $item = "user_id"; // Columna de la DB
    $valor = $_POST['obtenerIdUsuario']; // ID del usuario que se quiere obtener

    // Enviar los datos al controlador para obtener el usuario
    $respuesta = ControladorUsuarios::ctrMostrarUsuarios($item, $valor);

    echo json_encode($respuesta); // Enviamos la respuesta al cliente

  } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_POST['obtenerIdUsuario'])) {

    $item = null;
    $valor = null;

    // Enviar los datos al controlador para obtener los usuarios
    $respuesta = ControladorUsuarios::ctrMostrarUsuarios($item, $valor);

    echo json_encode($respuesta); // Enviamos la respuesta al cliente

  } else {

    json_encode([
      "status" => 401,
      "success" => false,
      "error" => "Parametro o dato incorrectos"
    ]);
  }

  /*=============================================
  REGISTRAR NUEVO USUARIO (POST)
  =============================================*/
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["nuevoUsername"])) {

    // Validar que los campos no estén vacíos
    if (empty($_POST["nuevoNombres"]) || empty($_POST["nuevoApellidos"]) || empty($_POST["nuevoCI"]) || empty($_POST["nuevoUsername"]) || empty($_POST["nuevoPassword"])) {

      echo json_encode(["mensaje" => "Todos los campos son obligatorios."]);
      exit;
    }
    
    // Mostramos los datos desde el controlador usuario del usuario creado
    echo ControladorUsuarios::ctrCrearUsuario();

  }

  /*=============================================
  EDITAR USUARIO (POST)
  =============================================*/
  if($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST["editarIdUsuario"])) {

    // Se muestran los datos recibidos del controlador
    echo ControladorUsuarios::ctrEditarUsuario();

  }

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

} else {

  // Si NO hay una sesion iniciada, mostrará un error
  echo json_encode([
    "status" => 402,
    "success" => false,
    "error" => "Acceso no autorizado",
    "mensaje" => "Haz intentado a acceder a una ruta protegida, Inicie sesion y vuelva a intentarlo"
  ]);
}