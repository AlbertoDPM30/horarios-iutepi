<?php

// Protegemos la ruta
if(isset($_SESSION["logged"]) == "ok") {

  /*=============================================
  OBTENER HABILIDAD(ES) (POST || GET)
  =============================================*/
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['obtenerIdHabilidad'])) {

    header('Content-Type: application/json; charset=utf-8');

    // Si se quiere obtener una sola habilidad se le da valor a los parametros
    $item = "skill_id"; // Columna de la DB
    $valor = $_POST['obtenerIdHabilidad']; // ID de la habilidad que se quiere obtener

    // Enviar los datos al controlador para obtener la hablidad
    $respuesta = ControladorHabilidades::ctrMostrarHabilidades($item, $valor);

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); // Enviamos la respuesta al cliente

  } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_POST['obtenerIdHabilidad'])) {

    $item = null;
    $valor = null;

    // Enviar los datos al controlador para obtener las habilidades
    $respuesta = ControladorHabilidades::ctrMostrarHabilidades($item, $valor);

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); // Enviamos la respuesta al cliente

  } else {

    json_encode([
      "status" => 401,
      "success" => false,
      "error" => "Parametros o datos incorrectos"
    ]);
  }

  /*=============================================
  REGISTRAR NUEVA HABILIDAD (POST)
  =============================================*/
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["nuevoNombreHabilidad"])) {

    // Validar que los campos no estén vacíos
    if (empty($_POST["nuevoNombreHabilidad"])) {

      echo json_encode(["mensaje" => "Todos los campos son obligatorios."]);
      exit;
    }
    
    $item = "name";
    $valor = $_POST["nuevoNombreHabilidad"];

    // Enviar los datos al controlador para obtener las habilidades
    $respuesta = ControladorHabilidades::ctrMostrarHabilidades($item, $valor);

    if ($respuesta) {
      // Si la habilidad ya existe, se retorna un mensaje
      http_response_code(400);
      echo json_encode([
        "status" => 00,
        "success" => false,
        "aviso" => "Esta Habilidad ya se encuentra registrada"
      ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      exit;
    }

    // Mostramos los datos desde el controlador de la habilidad creada
    echo ControladorHabilidades::ctrCrearHabilidad();

  }

  /*=============================================
  EDITAR HABILIDAD (POST)
  =============================================*/
  if($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST["editarIdHabilidad"])) {

    // Se muestran los datos recibidos del controlador
    echo ControladorHabilidades::ctrEditarHabilidad();
  }

  /*=============================================
  VALIDAR NO REPETIR HABILIDAD
  =============================================*/

  if ($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST["validarHabilidad"])) {

    $item = "skill_name"; // Columna de la DB
    $valor = $_POST['validarHabilidad']; // habilidad a validar

    // Enviar los datos al controlador para obtener la habilidad
    $respuesta = ControladorHabilidades::ctrMostrarHabilidades($item, $valor);

    // Enviamos la respuesta al cliente si ya existe esa habilidad
    if ($respuesta) {
      
      echo json_encode([
        "status" => 200,
        "success" => true,
        "aviso" => "Esta Habilidad ya se encuentra registrada"
      ]);
    }

  }

  /*=============================================
  ELMINAR Habilidad
  =============================================*/
  if ($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST["EliminarIdHabilidad"])) {

    echo ControladorHabilidades::ctrEliminarHabilidad();
  }

} else {

  // Si NO hay una sesion iniciada, mostrará un error
  echo json_encode([
    "status" => 402,
    "success" => false,
    "error" => "Acceso no autorizado",
    "mensaje" => "Haz intentado a acceder a una ruta protegida, Inicie sesion y vuelva a intentarlo"
  ]);
}