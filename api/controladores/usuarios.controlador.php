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

					if($respuesta["status"] !== 1) {
						http_response_code(401);
						echo json_encode([
							"status" => 401,
							"success" => false,
							"mensaje" => "Este usuario no se encuentra activo. Comunicarse con un administrador"
						]);
						exit;
					}

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

					$fechaActual = date('Y-m-d H:i:s');

					$item1 = "last_login";
					$valor1 = $fechaActual;

					$item2 = "user_id";
					$valor2 = $respuesta["user_id"];

					// Actualizar el último login del usuario en la base de datos
					$ultimoLogin = ModeloUsuarios::mdlActualizarUsuario($tabla, $item1, $valor1, $item2, $valor2);

					if ($ultimoLogin == "ok") { 
						
						return 'ok'; // Retornar 'ok' si el último login se actualizó correctamente
					} else {
						return 'error'; // Retornar 'error' si no se pudo actualizar el último login
					}
					
				} else {

					// Si las credenciales son incorrectas, limpiar las variables de sesión
					$_SESSION["logged"] = null;
					$_SESSION["user_id"] = null;
					$_SESSION["nombres"] = null;
					$_SESSION["apellidos"] = null;
					$_SESSION["username"] = null;
					$_SESSION["ci"] = null;
					
					return 'error'; // Retornar 'error' si las credenciales son incorrectas
					
					session_abort(); // Terminar la sesión actual
				}
			}
		}
	}

	/*=============================================
	MOSTRAR USUARIO
	=============================================*/

	static public function ctrMostrarUsuarios($item, $valor)
	{

		$tabla = "users"; // Definimos la tabla de la DB

		$respuesta = ModeloUsuarios::MdlMostrarUsuarios($tabla, $item, $valor);

		return $respuesta;
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
	EDITAR USUARIO
	=============================================*/

	static public function ctrEditarUsuario($datos)
	{

		$tabla = "users";

		$respuesta = ModeloUsuarios::mdlEditarUsuario($tabla, $datos);

		if ($respuesta == "ok") {

			return 'ok';
			} else {

			return 'error';
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
