<?php

class ControladorUsuarios
{

	/*=============================================
	INGRESO DE USUARIO
	=============================================*/

	static public function ctrIniciarSesion($datos)
	{

		if (isset($datos["username"]) && isset($datos["password"])) {

			// Validar que el campo de usuario no contenga caracteres especiales
			if (preg_match('/^[a-zA-Z0-9]+$/', $datos["username"])) {

				$tabla = "users"; //tabla de usuarios en DB
				
				$item = "username"; //columna de la tabla
				$valor = $datos["username"]; //valor del usuario a buscar
				
				$encriptar = crypt($datos["password"], '$2a$07$asxx54ahjppf45sd87a5a4dDDGsystemdev$'); //encriptar la contraseña

				$respuesta = ModeloUsuarios::MdlMostrarUsuarios($tabla, $item, $valor); // llamar al modelo para obtener el usuario
				
				// Verificar si la respuesta es un array y si el usuario y contraseña coinciden
				if (is_array($respuesta) && $respuesta["username"] == $datos["username"] && $respuesta["password"] == $encriptar) {

					// session_start();

					$_SESSION["logged"] = "ok"; // Variable de sesión para indicar que el usuario ha iniciado sesión
					$_SESSION["user_id"] = $respuesta["user_id"]; // ID del usuario
					$_SESSION["nombres"] = $respuesta["first_name"]; // Nombre del usuario
					$_SESSION["apellidos"] = $respuesta["last_name"]; // Apellido del usuario
					$_SESSION["username"] = $respuesta["username"]; // Nombre de usuario
					$_SESSION["ci"] = $respuesta["ci"]; // Cédula de identidad del usuario
					
					/*=============================================
					REGISTRAR FECHA PARA SABER EL ÚLTIMO LOGIN
					=============================================*/

					date_default_timezone_set('America/Caracas');

					$fecha = date('Y-m-d');
					$hora = date('H:i:s');

					$fechaActual = $fecha . ' ' . $hora;

					$item1 = "last_login";
					$valor1 = $fechaActual;

					$item2 = "user_id";
					$valor2 = $respuesta["user_id"];

					$ultimoLogin = ModeloUsuarios::mdlActualizarUsuario($tabla, $item1, $valor1, $item2, $valor2);

					if ($ultimoLogin == "ok") { 
						
						return 'ok'; // Retornar 'ok' si el último login se actualizó correctamente
					} else {
						return 'error'; // Retornar 'error' si no se pudo actualizar el último login
					}
					
				} else {

					$_SESSION["logged"] = null;
					$_SESSION["user_id"] = null;
					$_SESSION["nombres"] = null;
					$_SESSION["apellidos"] = null;
					$_SESSION["username"] = null;
					$_SESSION["ci"] = null;
					
					return 'error'; // Retornar 'error' si las credenciales son incorrectas
					
					session_abort();
				}
			}
		}
	}

	/*=============================================
	REGISTRO DE USUARIO
	=============================================*/

	static public function ctrCrearUsuario($datos)
	{

		if (isset($datos)) {

			$tabla = "users"; // Tabla de usuarios en la base de datos
			
			$respuesta = ModeloUsuarios::mdlIngresarUsuario($tabla, $datos);

			if ($respuesta == "ok") {
				return "ok"; // Retornar 'ok' si el usuario fue creado exitosamente

			} else {
				return "error";	// Si hubo un error al crear el usuario, mostrar un mensaje de error

			}
		}
	}

	/*=============================================
	MOSTRAR USUARIO
	=============================================*/

	static public function ctrMostrarUsuarios($item, $valor)
	{

		$tabla = "usuarios";

		$respuesta = ModeloUsuarios::MdlMostrarUsuarios($tabla, $item, $valor);

		return $respuesta;
	}

	/*=============================================
	EDITAR USUARIO
	=============================================*/

	static public function ctrEditarUsuario()
	{

		if (isset($_POST["editarUsuario"])) {

			$tabla = "usuarios";

			if ($_POST["editarPassword"] != "") {

				$encriptar = crypt($_POST["editarPassword"], '$2a$07$asxx54ahjppf45sd87a5a4dDDGsystemdev$');
			} else {

				
				$encriptar = $_POST["passwordActual"];
			}

			$datos = array(
				"id" => $_POST["idUsuario"],
				"nombres" => $_POST["editarNombres"],
				"usuario" => strtolower($_POST["editarUsuario"]),
				"email" => strtolower($_POST["editarEmail"]),
				"password" => $encriptar
			);

			$respuesta = ModeloUsuarios::mdlEditarUsuario($tabla, $datos);

			if ($respuesta == "ok") {

				echo '<script>

					Swal.fire({
						icon: "success",
						title: "usuario editado con éxito!",
						timer: 1500,
						timerProgressBar: true,
						didOpen: () => {
							Swal.showLoading();
						}
					}).then((result) => {
						if (result.dismiss === Swal.DismissReason.timer) {
							window.location = "usuarios";
						}
					});

					</script>';
			} else {

				echo '<script>

					Swal.fire({
						icon: "error",
						title: "¡Error al Editar el usuario!",
						timer: 2000,
						timerProgressBar: true,
						didOpen: () => {
							Swal.showLoading();
						}
					}).then((result) => {
						if (result.dismiss === Swal.DismissReason.timer) {
							window.location = "usuarios";
						}
					});

				</script>';
			}
		}
	}

	/*=============================================
	ELIMINAR USUARIO
	=============================================*/

	static public function ctrEliminarUsuario(){

		if(isset($_POST["idEliminarUsuario"])){

			$tabla ="usuarios";
			$datos = $_POST["idEliminarUsuario"];

			$respuesta = ModeloUsuarios::mdlEliminarUsuario($tabla, $datos);

			return $respuesta;
		}

	}

}
