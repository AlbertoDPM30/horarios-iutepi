<?php
// Protegemos la ruta
if(isset($_SESSION["logged"]) == "ok") {

  /*=============================================
  OBTENER MATERIA(S) (POST || GET)
  =============================================*/
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['obtenerIdMateria'])) {

    header('Content-Type: application/json; charset=utf-8');

    // Si se quiere obtener una sola materia se le da valor a los parametros
    $item = "subject_id"; // Columna de la DB
    $valor = $_POST['obtenerIdMateria']; // ID de la materia que se quiere obtener

    // Enviar los datos al controlador para obtener la materia
    $respuesta = ControladorMaterias::ctrMostrarMaterias($item, $valor);

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); // Enviamos la respuesta al cliente

  } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_POST['obtenerIdMateria'])) {

    header('Content-Type: application/json; charset=utf-8');

    $item = null;
    $valor = null;

    // Enviar los datos al controlador para obtener las materias
    $respuesta = ControladorMaterias::ctrMostrarMaterias($item, $valor);

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); // Enviamos la respuesta al cliente

  } else {

    json_encode([
      "status" => 401,
      "success" => false,
      "error" => "Parametros o datos incorrectos"
    ]);
  }

  /*=============================================
  REGISTRAR NUEVA MATERIA (POST)
  =============================================*/
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["nuevaMateria"])) {

    // Validar que los campos no estén vacíos
    if (empty($_POST["nuevaMateria"])) {

      echo json_encode(["mensaje" => "Todos los campos son obligatorios."]);
      exit;
    }
    
    // Mostramos los datos desde el controlador de la materia creada
    echo ControladorMaterias::ctrCrearMateria();

  }

  /*=============================================
  EDITAR MATERIA (POST)
  =============================================*/
  if($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST["editarIdMateria"])) {

    // Se muestran los datos recibidos del controlador
    echo ControladorMaterias::ctrEditarMateria();
  }

  /*=============================================
  VALIDAR NO REPETIR MATERIA
  =============================================*/

  if ($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST["validarMateria"])) {

    $item = "name"; // Columna de la DB
    $valor = $_POST['validarMateria']; // Materia a validar

    // Enviar los datos al controlador para obtener la Materia
    $respuesta = ControladorMaterias::ctrMostrarMaterias($item, $valor);

    // Enviamos la respuesta al cliente si ya existe esa Materia
    if ($respuesta) {
      
      echo json_encode([
        "status" => 200,
        "success" => true,
        "aviso" => "Esta Materia ya se encuentra registrada"
      ]);
    }

  }

  /*=============================================
  ELMINAR MATERIA
  =============================================*/
  if ($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST["EliminarIdMateria"])) {

    echo ControladorMaterias::ctrEliminarMateria();
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