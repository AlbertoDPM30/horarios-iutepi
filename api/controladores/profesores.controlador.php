<?php

require_once "config/autenticacion.php"; // Importar la clase de autenticación

class ControladorProfesores
{

	/*=============================================
	MOSTRAR PROFESORES
	=============================================*/

	static public function ctrMostrarProfesores($item, $valor)
	{

		$tabla = "teachers"; // Definimos la tabla de la DB

		$respuesta = ModeloProfesores::MdlMostrarProfesores($tabla, $item, $valor);

		return $respuesta;
	}

	/*=============================================
	REGISTRAR PROFESOR
	=============================================*/

	static public function ctrCrearProfesor()
	{

		if (isset($_POST["nuevoNombreProfesor"])) {

			$tabla = "teachers"; // Tabla de profesores en la base de datos

			// Crear un array con los datos del nuevo profesor
			$datos = array(
			"name" => trim($_POST["nuevoNombreProfesor"]),
			"ci_code" => trim($_POST["nuevoCIProfesor"])
			);

			$respuesta = ModeloProfesores::mdlCrearProfesor($tabla, $datos);

			// Verificar la respuesta del controlador
			if ($respuesta == "ok") {

				// Si es correcta mostrará los datos del usuario recien registrado
				http_response_code(201);
				return json_encode([
					"status" => 201,
					"success" => true,
					"data" => [
					"nombres" => $datos["name"],
					"cedula_codigo" => $datos["ci_code"]
					],
					"mensaje" => "profesor creado correctamente"
				]);
			} else {

				// Si algo falla retornará un status 500
				http_response_code(500);
				return json_encode([
					"status" => 500,
					"success" => false,
					"data" => null,
					"mensaje" => "error al crear el nuevo profesor"
				]);
			}

		}
	}

	/*=============================================
	EDITAR PROFESOR
	=============================================*/

	static public function ctrEditarProfesor()
	{

		$tabla = "teachers";

		date_default_timezone_set('America/Caracas');

		$fechaActualizacion = date('Y-m-d H:i:s');
		
		// Crear un array con los datos del Profesor a editar
		$datos = array(
		"teacher_id" => $_POST["editarIdProfesor"],
		"name" => trim($_POST["editarNombreProfesor"]),
		"ci_code" => trim($_POST["editarCIProfesor"]),
		"updated_at" => $fechaActualizacion
		);

		$respuesta = ModeloProfesores::mdlEditarProfesor($tabla, $datos);

		//Recibimos la respuesta
		if ($respuesta == "ok") {

			// Retornamos la respuesta con los datos del profesor actualizado
			http_response_code(201);
			return json_encode([
				"status" => 201,
				"success" => true,
				"data" => [
				"id" => $datos["teacher_id"],
				"nombres" => $datos["name"],
				"ci" => $datos["ci_code"],
				"fecha_actualizacion" => $datos["updated_at"]
				],
				"mensaje" => "Profesor actualizado correctamente"
			]);
		} else {
		
		http_response_code(500);
		return json_encode([
			"status" => 500,
			"success" => false,
			"Error" => "No se pudo actualizar el profesor",
			"mensaje" => "Ha ocurrido un problema al intentar actualizar este profesor, Contacte con un Administrador"
		]);
		}
	}

	/*=============================================
	ELIMINAR PROFESOR
	=============================================*/

	static public function ctrEliminarProfesor(){

		$tabla ="teachers";

		$datos = $_POST["EliminarIdProfesor"]; // Recibir el id del usuario a eliminar

		$respuesta = ModeloProfesores::mdlEliminarProfesor($tabla, $datos);

		// Verificamos la respuesta del modelo
		if ($respuesta == "ok") {

			// Si la respuesta es correcta, retornamos un status 200 y un mensaje de éxito
			http_response_code(200);
			return json_encode([
				"status" => 200,
				"success" => true,
				"mensaje" => "Profesor eliminado con exito"
			]);
		} else {

			// Si la respuesta es incorrecta, retornamos un status 500 y un mensaje de error
			http_response_code(500);
			return json_encode([
				"status" => 500,
				"success" => false,
				"error" => "Profesor NO eliminado",
				"mensaje" => "Ha ocurrido un problema al intentar eliminar este Profesor, Contacte con un Administrador"
			]);
		}

	}

}
