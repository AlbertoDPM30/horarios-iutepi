<?php
// Protegemos la ruta
if(isset($_SESSION["logged"]) == "ok") {

  /*=============================================
  OBTENER PROFESOR(ES) (POST || GET)
  =============================================*/
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['obtenerIdProfesor'])) {

    header('Content-Type: application/json; charset=utf-8'); //  Establecer cabeceras para JSON + UTF-8

    // Si se quiere obtener un solo profesor se le da valor a los parametros
    $item = "teacher_id"; // Columna de la DB
    $valor = $_POST['obtenerIdProfesor']; // ID del profesor que se quiere obtener

    // Enviar los datos al controlador para obtener el profesor
    $respuesta = ControladorProfesores::ctrMostrarProfesores($item, $valor);

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); // Enviamos la respuesta al cliente

  } elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_POST['obtenerIdProfesor'])) {

    header('Content-Type: application/json; charset=utf-8'); //  Establecer cabeceras para JSON + UTF-8

    $item = null;
    $valor = null;

    // Enviar los datos al controlador para obtener los profesores
    $respuesta = ControladorProfesores::ctrMostrarProfesores($item, $valor);

    echo json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); // Enviamos la respuesta al cliente

  } else {

    header('Content-Type: application/json; charset=utf-8'); //  Establecer cabeceras para JSON + UTF-8

    json_encode([
      "status" => 401,
      "success" => false,
      "error" => "Parametros o datos incorrectos"
    ]);
  }

  /*=============================================
  REGISTRAR NUEVO PROFESOR (POST)
  =============================================*/
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["nuevoNombreProfesor"])) {

    // Validar que los campos no estén vacíos
    if (empty($_POST["nuevoNombreProfesor"]) || empty($_POST["nuevoCIProfesor"])) {

      echo json_encode(["mensaje" => "Todos los campos son obligatorios."]);
      exit;
    }
    
    // Mostramos los datos desde el controlador del profesor creado
    echo ControladorProfesores::ctrCrearProfesor();

  }

  /*=============================================
  EDITAR PROFESOR (POST)
  =============================================*/
  if($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST["editarIdProfesor"])) {

    // Se muestran los datos recibidos del controlador
    echo ControladorProfesores::ctrEditarProfesor();
  }

  /*=============================================
  VALIDAR NO REPETIR PROFESOR
  =============================================*/

  if ($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST["validarCIProfesor"])) {

    $item = "ci_code"; // Columna de la DB
    $valor = $_POST['validarCIProfesor']; // ci a validar

    // Enviar los datos al controlador para obtener el profesor
    $respuesta = ControladorProfesores::ctrMostrarProfesores($item, $valor);

    // Enviamos la respuesta al cliente si ya existe esa ci
    if ($respuesta) {
      
      echo json_encode([
        "status" => 200,
        "success" => true,
        "aviso" => "Esta CI ya se encuentra registrada"
      ]);
    }

  }

  /*=============================================
  ELMINAR PROFESOR
  =============================================*/
  if ($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST["EliminarIdProfesor"])) {

    echo ControladorProfesores::ctrEliminarProfesor();
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