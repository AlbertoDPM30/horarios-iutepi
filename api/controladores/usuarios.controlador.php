<?php

class ControladorUsuarios
{

	/*=============================================
	INGRESO DE USUARIO
	=============================================*/

	static public function ctrIniciarSesion()
	{

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
					
						// Iniciar sesión y guardar los datos del usuario en la sesión
						http_response_code(201);
						return json_encode([
							"logged:" => $_SESSION["logged"],
							"id:" => $_SESSION["user_id"],
							"nombres:" => $_SESSION["nombres"],
							"apellidos:" => $_SESSION["apellidos"],
							"usuario:" => $_SESSION["username"],
							"cedula" => $_SESSION["ci"]
						]); 
					} else {

						// Si la respuesta no es "ok", significa que hubo un error al iniciar sesión
						http_response_code(500);
						return json_encode(["Error" => "Usuario o contraseña incorrectos."]);
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
	REGISTRAR USUARIO
	=============================================*/

	static public function ctrCrearUsuario()
	{

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
				]);
			} else {

				// Si algo falla retornará un status 500
				http_response_code(500);
				return json_encode([
					"status" => 500,
					"success" => false,
					"data" => null,
					"mensaje" => "error al crear el usuario"
				]);
			}

		}
	}

	/*=============================================
	EDITAR USUARIO
	=============================================*/

	static public function ctrEditarUsuario()
	{

		$tabla = "users";

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
		]);
		} else {
		
		http_response_code(500);
		return json_encode([
			"status" => 500,
			"success" => false,
			"Error" => "No se pudo actualizar el usuario"
		]);
		}
	}

	/*=============================================
	ACTUALIZAR USUARIO
	=============================================*/

	static public function ctrActualizarStatusUsuario(){

		if (!isset($_POST["actualizarStatus"]) || ($_POST["actualizarStatus"] != "1" && $_POST["actualizarStatus"] != "0")) {
			echo json_encode([
				"error" => "El valor enviado debe ser 1 o 0"
			]);
			exit;
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
			]); 
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

		if(isset($_POST["idEliminarUsuario"])){

			$tabla ="usuarios";
			$datos = $_POST["idEliminarUsuario"];

			$respuesta = ModeloUsuarios::mdlEliminarUsuario($tabla, $datos);

			return $respuesta;
		}

	}

}
