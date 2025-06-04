<?php
/*=============================================
INICIAR SESION
=============================================*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST["username"]) && isset($_POST["password"])) {

    echo ControladorUsuarios::ctrIniciarSesion();
} else {

    // Si no se han enviado los parámetros necesarios o son incorrectos
    http_response_code(500);
    echo json_encode(["Error" => "Parametros o datos Incorrectos."]);
}

?>