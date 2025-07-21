<?php

require_once "config/autenticacion.php"; // Importar la clase de autenticación

class ControladorUsuarios
{

	/*=============================================
	INGRESO DE USUARIO
	=============================================*/

	static public function ctrIniciarSesion()
	{

		header('Content-Type: application/json; charset=utf-8'); //  Establecer cabeceras para JSON + UTF-8

		if (isset($_POST["username"]) && isset($_POST["password"])) {

			// Validar que el campo de usuario no contenga caracteres especiales
			if (preg_match('/^[a-zA-Z0-9]+$/', $_POST["username"])) {

				$tabla = "users"; //tabla de usuarios en DB
				
				$item = "username"; //columna de la tabla
				$valor = $_POST["username"]; //valor del usuario a buscar
				
				$encriptar = crypt($_POST["password"], '$2a$07$asxx54ahjppf45sd87a5a4dDDGsystemdev$'); //encriptar la contraseña

				$respuesta = ModeloUsuarios::MdlMostrarUsuarios($tabla, $item, $valor); // llamar al modelo para obtener el usuario
				
				// Verificar si la respuesta es un array y si el usuario y contraseña coinciden
				if (is_array($respuesta) && $respuesta["username"] == $_POST["username"] && $respuesta["password"] == $encriptar) {

					if($respuesta["status"] !== 1) {
						http_response_code(401);
						return json_encode([
							"status" => 401,
							"success" => false,
							"mensaje" => "Este usuario no se encuentra activo. Comunicarse con un administrador"
						], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
						exit;
					}

					$token = Autenticacion::generarToken($respuesta["user_id"]); // Generar un token de autenticación
					
					$_SESSION["logged"] = "ok"; // Variable de sesión para indicar que el usuario ha iniciado sesión
					$_SESSION["user_id"] = $respuesta["user_id"]; // ID del usuario
					$_SESSION["nombres"] = $respuesta["first_name"]; // Nombre del usuario
					$_SESSION["apellidos"] = $respuesta["last_name"]; // Apellido del usuario
					$_SESSION["username"] = $respuesta["username"]; // Nombre de usuario
					$_SESSION["ci"] = $respuesta["ci"]; // Cédula de identidad del usuario
					$_SESSION["token"] = $token; // Token de autenticación
					
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
					
						// Iniciar sesión y guardar los datos del usuario en la sesión
						http_response_code(201);
						return json_encode([
							"status" => 201,
							"success" => true,
							"data" => [
								"logged:" => $_SESSION["logged"],
								"id:" => $_SESSION["user_id"],
								"nombres:" => $_SESSION["nombres"],
								"apellidos:" => $_SESSION["apellidos"],
								"usuario:" => $_SESSION["username"],
								"cedula" => $_SESSION["ci"],
								"token" => $_SESSION["token"],
							],
							"mensaje" => "Inicio de sesion exitoso"
						], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); 
					} else {

						// Si la respuesta no es "ok", significa que hubo un error al iniciar sesión
						http_response_code(500);
						return json_encode([
							"status" => 500,
							"success" => false,
							"Error" => "Usuario o contraseña incorrectos.",
							"mensaje" => "Ha ocurrido un problema al iniciar sesion, Contacte con un Administrador"
						], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
					}

				} else {
					
					session_abort(); // Terminar la sesión actual

					// Si las credenciales son incorrectas, limpiar las variables de sesión
					$_SESSION["logged"] = null;
					$_SESSION["user_id"] = null;
					$_SESSION["nombres"] = null;
					$_SESSION["apellidos"] = null;
					$_SESSION["username"] = null;
					$_SESSION["ci"] = null;
					$_SESSION["token"] = null;
					
					return 'error'; // Retornar 'error' si las credenciales son incorrectas
				}
			}
		}
	}

	/*=============================================
	CERRAR SESIÓN
	=============================================*/

	static public function ctrCerrarSesion($user_id, $token)
	{

		header('Content-Type: application/json; charset=utf-8'); //  Establecer cabeceras para JSON + UTF-8

		if ($user_id === $_SESSION["user_id"] && $token === $_SESSION["token"]) {

			// Verificar si la sesión está iniciada
			if (!isset($_SESSION["logged"]) || $_SESSION["logged"] !== "ok") {
				http_response_code(401);
				return json_encode([
					"status" => 401,
					"success" => false,
					"mensaje" => "No se ha iniciado sesión."
				], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
				exit;
			}

			// Cerrar sesión y limpiar las variables de sesión
			$_SESSION["logged"] = null;
			$_SESSION["user_id"] = null;
			$_SESSION["nombres"] = null;
			$_SESSION["apellidos"] = null;
			$_SESSION["username"] = null;
			$_SESSION["ci"] = null;

			session_destroy();

			http_response_code(201);
			return json_encode([
				"status" => 201,
				"success" => true,
				"message" => "Sesion cerrada correctamente."
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

		}

		// No coinciden el user_id y el token, o la sesión no está iniciada
		header('Content-Type: application/json; charset=utf-8');
		http_response_code(401);
		return json_encode([
			"status" => 401,
			"success" => false,
			"mensaje" => "No coinciden el user_id o el token, o la sesión no está iniciada."
		], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		exit;
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
	REGISTRAR USUARIO
	=============================================*/

	static public function ctrCrearUsuario()
	{

		header('Content-Type: application/json; charset=utf-8'); //  Establecer cabeceras para JSON + UTF-8

		if (isset($_POST["nuevoUsername"])) {

			$tabla = "users"; // Tabla de usuarios en la base de datos
			
			$encriptar = crypt($_POST["nuevoPassword"], '$2a$07$asxx54ahjppf45sd87a5a4dDDGsystemdev$'); // Encriptar la contraseña

			// Crear un array con los datos del nuevo usuario
			$datos = array(
				"first_name" => trim($_POST["nuevoNombres"]),
				"last_name" => trim($_POST["nuevoApellidos"]),
				"ci" => trim($_POST["nuevoCI"]),
				"username" => strtolower(trim($_POST["nuevoUsername"])),
				"password" => $encriptar
			);

			$respuesta = ModeloUsuarios::mdlIngresarUsuario($tabla, $datos);

			// Verificar la respuesta del controlador
			if ($respuesta == "ok") {

				// Si es correcta mostrará los datos del usuario recien registrado
				http_response_code(201);
				return json_encode([
					"status" => 201,
					"success" => true,
					"data" => [
					"nombres" => $datos["first_name"],
					"apellidos" => $datos["last_name"],
					"cedula" => $datos["ci"],
					"usuario" => $datos["username"]
					],
					"mensaje" => "usuario creado correctamente"
				], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			} else {

				// Si algo falla retornará un status 500
				http_response_code(500);
				return json_encode([
					"status" => 500,
					"success" => false,
					"data" => null,
					"mensaje" => "error al crear el usuario"
				], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			}

		}

	}

	/*=============================================
	EDITAR USUARIO
	=============================================*/

	static public function ctrEditarUsuario()
	{

		header('Content-Type: application/json; charset=utf-8'); //  Establecer cabeceras para JSON + UTF-8

		$tabla = "users";

		date_default_timezone_set('America/Caracas');

		$fechaActualizacion = date('Y-m-d H:i:s');
		
		// Se evita que se edite el usuario con ID 1 (el master)
		if($_POST["editarIdUsuario"] == 1) {
			http_response_code(403);
			return json_encode([
				"status" => 403,
				"success" => false,
				"mensaje" => "No se puede editar el usuario con ID 1"
			]);
		}

		// Crear un array con los datos del usuario a editar
		$datos = array(
			"user_id" => $_POST["editarIdUsuario"],
			"first_name" => trim($_POST["editarNombres"]),
			"last_name" => trim($_POST["editarApellidos"]),
			"ci" => trim($_POST["editarCI"]),
			"updated_at" => $fechaActualizacion
		);

		$respuesta = ModeloUsuarios::mdlEditarUsuario($tabla, $datos);

		//Recibimos la respuesta
		if ($respuesta == "ok") {

			http_response_code(201);
			return json_encode([
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
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		} else {
		
			http_response_code(500);
			return json_encode([
				"status" => 500,
				"success" => false,
				"Error" => "No se pudo actualizar el usuario"
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		}

	}

	/*=============================================
	ACTUALIZAR USUARIO
	=============================================*/

	static public function ctrActualizarStatusUsuario(){

		// si el valor no está definido o no es 1 o 0, retornar un error
		if (!isset($_POST["actualizarStatus"]) || ($_POST["actualizarStatus"] != "1" && $_POST["actualizarStatus"] != "0")) {
			return json_encode([
				"error" => "El valor enviado debe ser 1 o 0"
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			exit;
		}
		
		// Se evita que se edite el usuario con ID 1 (el master)
		if($_POST["actualizarIdUsuario"] == 1) {
			http_response_code(403);
			return json_encode([
				"status" => 403,
				"success" => false,
				"mensaje" => "No se puede modificar el status del usuario con ID 1"
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		}

		$tabla = "users";

		// Recibir un valor binario (0 - 1)
		$item1 = "status";
		$valor1 = $_POST["actualizarStatus"];

		// Recibir el id del usuario a actualizar
		$item2 = "user_id";
		$valor2 = $_POST["actualizarIdUsuario"];

		// Actualizar el último login del usuario en la base de datos
		$respuesta = ModeloUsuarios::mdlActualizarUsuario($tabla, $item1, $valor1, $item2, $valor2);

		if ($respuesta == "ok") { 
		
			// Mensaje de exito si la respuesta es "ok"
			http_response_code(201);
			return json_encode([
				"status" => 201,
				"success" => true,
				"mensaje" => "Status actualizado correctamente"
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); 
		} else {

			// Si la respuesta no es "ok", significa que hubo un error al Actualizar el status
			http_response_code(500);
			return json_encode(["Error" => "Ha ocurrido un problema al actualizar el status"]);
		}

	}

	/*=============================================
	ELIMINAR USUARIO
	=============================================*/

	static public function ctrEliminarUsuario(){

		// Se evita que se elimine el usuario con ID 1 (el master)
		if($_POST["EliminarIdUsuario"] == 1) {
			http_response_code(403);
			return json_encode([
				"status" => 403,
				"success" => false,
				"mensaje" => "No se puede eliminar el usuario con ID 1"
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		}

		$tabla ="users";

		$datos = $_POST["EliminarIdUsuario"]; // Recibir el id del usuario a eliminar

		$respuesta = ModeloUsuarios::mdlEliminarUsuario($tabla, $datos);

		if ($respuesta == "ok") {

			http_response_code(200);
			return json_encode([
				"status" => 200,
				"success" => true,
				"mensaje" => "Usuario eliminado con exito"
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		} else {

			http_response_code(500);
			return json_encode([
				"status" => 500,
				"success" => false,
				"error" => "Usuario NO eliminado",
				"mensaje" => "Ha ocurrido un problema al intentar eliminar este usuario, Contacte con un Administrador"
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		}

	}

}
