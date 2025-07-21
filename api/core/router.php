<?php

session_start();

  /*=============================================
  RUTAS
  =============================================*/

  if (isset($_GET["ruta"])) {

    if (

      // PROFESORES
      $_GET["ruta"] == "profesores"   ||
      
      // HABILIDADES
      $_GET["ruta"] == "habilidades"  ||
      
      // MATERIAS
      $_GET["ruta"] == "materias"     ||
      
      // USUARIOS               
      $_GET["ruta"] == "login"        ||
      $_GET["ruta"] == "logout"       ||
      $_GET["ruta"] == "usuarios"
    ) {

      include "rutas/" . $_GET["ruta"] . ".php";
    } else {

      echo json_encode([
        "status" => "404",
        "mensaje" => "Ruta no encontrada.",
        "descripcion" => "La ruta solicitada no existe o no está implementada."
      ]);
    }
  } else {

    echo json_encode([
      "status" => "404",
      "mensaje" => "Ruta no especificada.",
      "descripcion" => "Por favor, especifique una ruta válida."
    ]);
  }

?>
