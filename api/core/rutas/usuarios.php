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
    
    $encriptar = crypt($_POST["nuevoPassword"], '$2a$07$asxx54ahjppf45sd87a5a4dDDGsystemdev$'); // Encriptar la contraseña

    // Crear un array con los datos del nuevo usuario
    $datos = array(
      "first_name" => trim($_POST["nuevoNombres"]),
      "last_name" => trim($_POST["nuevoApellidos"]),
      "ci" => trim($_POST["nuevoCI"]),
      "username" => strtolower(trim($_POST["nuevoUsername"])),
      "password" => $encriptar
    );

    // Enviar los datos al controlador para crear el usuario
    $respuesta = ControladorUsuarios::ctrCrearUsuario($datos);

    // Verificar la respuesta del controlador
    if ($respuesta == "ok") {

      // Si es correcta mostrará los datos del usuario recien registrado
      http_response_code(201);
      echo json_encode([
        "status" => 201,
        "success" => true,
        "data" => [
          "id" => $datos["user_id"],
          "nombres" => $datos["first_name"],
          "apellidos" => $datos["last_name"],
          "cedula" => $datos["ci"],
          "usuario" => $datos["username"]
        ],
        "mensaje" => "usuario creado correctamente"
      ]);
    } else {

      // Si algo falla retornará un status 500
      http_response_code(500);
      echo json_encode([
        "status" => 500,
        "success" => false,
        "data" => null,
        "mensaje" => "error al crear el usuario"
      ]);
    }

  }

  /*=============================================
  EDITAR USUARIO (POST)
  =============================================*/
  if($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST["editarIdUsuario"])) {

    date_default_timezone_set('America/Caracas');

    $fechaActualizacion = date('Y-m-d H:i:s');

    // Crear un array con los datos del usuario a editar
    $datos = array(
      "user_id" => $_POST["editarIdUsuario"],
      "first_name" => trim($_POST["editarNombres"]),
      "last_name" => trim($_POST["editarApellidos"]),
      "ci" => trim($_POST["editarCI"]),
      "updated_at" => $fechaActualizacion
    );

    // Enviamos los datos al controlador
    $respuesta = ControladorUsuarios::ctrEditarUsuario($datos);

    //Recibimos la respuesta
    if ($respuesta == "ok") {
      http_response_code(201);
      echo json_encode([
        "status" => 201,
        "success" => true,
        "data" => [
          "id" => $datos["user_id"],
          "nombres" => $datos["first_name"],
          "apellidos" => $datos["last_name"],
          "ci" => $datos["ci"],
          "fecha_actualizacion" => $datos["updated_at"]
        ],
        "mensaje" => "Usuario actualizado"
      ]);
    } else {
      
      http_response_code(500);
      echo json_encode([
        "status" => 500,
        "success" => false,
        "Error" => "No se pudo actualizar el usuario"
      ]);
    }
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

  // Si hay una sesion iniciada, mostrará un error
  echo json_encode([
    "status" => 402,
    "success" => false,
    "error" => "Acceso no autorizado",
    "mensaje" => "Haz intentado a acceder a una ruta protegida, Inicie sesion y vuelva a intentarlo"
  ]);
}