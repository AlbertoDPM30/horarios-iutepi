<?php
/*=============================================
INICIAR SESION
=============================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["username"]) && isset($_POST["password"])) {

    // Obtenemos los datos del formulario y los enviamos al controlador
    $datos = array(
        "username" => $_POST["username"],
        "password" => $_POST["password"]
    );

    $respuesta = ControladorUsuarios::ctrIniciarSesion($datos);

    // Verificamos la respuesta del controlador
    if ($respuesta == "ok") {

        // Iniciar sesión y guardar los datos del usuario en la sesión
        echo json_encode([
            "Logged:" => $_SESSION["logged"],
            "ID:" => $_SESSION["user_id"],
            "Nombres:" => $_SESSION["nombres"],
            "Apellidos:" => $_SESSION["apellidos"],
            "Usuario:" => $_SESSION["username"],
            "Cedula" => $_SESSION["ci"]
        ]); 
    } else {

        // Si la respuesta no es "ok", significa que hubo un error al iniciar sesión
        echo json_encode(["Error" => "Usuario o contraseña incorrectos."]);
    }

	
} else {

    // Si no se han enviado los parámetros necesarios o son incorrectos
    echo json_encode(["Error" => "Parametros o datos Incorrectos."]);
}

?>