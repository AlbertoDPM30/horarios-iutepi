<?php

class ControladorHabilidades
{

	/*=============================================
	MOSTRAR HABILIDADES
	=============================================*/

	static public function ctrMostrarHabilidades($item, $valor)
	{

		$tabla = "skills"; // Definimos la tabla de la DB

		$respuesta = ModeloHabilidades::MdlMostrarHabilidades($tabla, $item, $valor);

		return $respuesta;
	}

	/*=============================================
	REGISTRAR HABILIDAD
	=============================================*/

	static public function ctrCrearHabilidad()
	{

		if (isset($_POST["nuevoNombreHabilidad"])) {

			header('Content-Type: application/json; charset=utf-8'); //  Establecer cabeceras para JSON + UTF-8

			$tabla = "skills"; // Tabla de habilidades en la base de datos

			// Crear un array con los datos del nueva habilidad
			$datos = array("skill_name" => trim($_POST["nuevoNombreHabilidad"]));

			$respuesta = ModeloHabilidades::mdlCrearHabilidad($tabla, $datos);

			// Verificar la respuesta del controlador
			if ($respuesta == "ok") {

				// Si es correcta mostrará los datos recién registrados
				http_response_code(201);
				$dataRespuesta = json_encode([
					"status" => 201,
					"success" => true,
					"data" => [
						"habilidad" => $datos["skill_name"]
					],
					"mensaje" => "habilidad creada correctamente"
				]);
			} else {

				// Si algo falla retornará un status 500
				http_response_code(500);
				$dataRespuesta = json_encode([
					"status" => 500,
					"success" => false,
					"data" => null,
					"mensaje" => "error al crear la nueva habilidad",
				]);
			}

			$json = json_encode($dataRespuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

			if ($json === false) {
				$jsonError = json_last_error_msg();
				http_response_code(500); // Error interno del servidor
				$json = json_encode(['error' => "Error generando JSON: $jsonError"]);
			}

			return $json; // Retornar el JSON generado

		}
	}

	/*=============================================
	EDITAR HABILIDAD
	=============================================*/

	static public function ctrEditarHabilidad()
	{

		$tabla = "skills";

		date_default_timezone_set('America/Caracas');

		$fechaActualizacion = date('Y-m-d H:i:s');
		
		// Crear un array con los datos de la habilidad a editar
		$datos = array(
		"skill_id" => $_POST["editarIdHabilidad"],
		"skill_name" => trim($_POST["editarNombreHabilidad"]),
		"updated_at" => $fechaActualizacion
		);

		$respuesta = ModeloHabilidades::mdlEditarHabilidad($tabla, $datos);

		//Recibimos la respuesta
		if ($respuesta == "ok") {

			// Retornamos la respuesta con los datos actualizado
			http_response_code(201);
			$dataRespuesta = json_encode([
				"status" => 201,
				"success" => true,
				"data" => [
					"id" => $datos["skill_id"],
					"habilidad" => $datos["skill_name"],
					"fecha_actualizacion" => $datos["updated_at"]
				],
				"mensaje" => "Habilidad actualizada correctamente"
			]);
		} else {
		
			// Si algo falla retornará un status 500 y un mensaje de error
			http_response_code(500);
			$dataRespuesta = json_encode([
				"status" => 500,
				"success" => false,
				"Error" => "No se pudo actualizar la habilidad",
				"mensaje" => "Ha ocurrido un problema al intentar actualizar esta habilidad, Contacte con un Administrador"
			]);
		}

		$json = json_encode($dataRespuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

		if ($json === false) {
			$jsonError = json_last_error_msg();
			http_response_code(500); // Error interno del servidor
			$json = json_encode(['error' => "Error generando JSON: $jsonError"]);
		}

		return $json; // Retornar el JSON generado
	}

	/*=============================================
	ELIMINAR HABILIDAD
	=============================================*/

	static public function ctrEliminarHabilidad(){

		$tabla ="skills";

		$datos = $_POST["EliminarIdHabilidad"]; // Recibir el id a eliminar

		$respuesta = ModeloHabilidades::mdlEliminarHabilidad($tabla, $datos);

		// Verificamos la respuesta del modelo
		if ($respuesta == "ok") {

			// Si la respuesta es correcta, retornamos un status 200 y un mensaje de éxito
			http_response_code(200);
			$dataRespuesta = json_encode([
				"status" => 200,
				"success" => true,
				"mensaje" => "Habilidad eliminada con exito"
			]);
		} else {

			// Si la respuesta es incorrecta, retornamos un status 500 y un mensaje de error
			http_response_code(500);
			$dataRespuesta = json_encode([
				"status" => 500,
				"success" => false,
				"error" => "Habilidad NO eliminada",
				"mensaje" => "Ha ocurrido un problema al intentar eliminar esta Habilidad, Contacte con un Administrador"
			]);
		}

		$json = json_encode($dataRespuesta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

		if ($json === false) {
			$jsonError = json_last_error_msg();
			http_response_code(500); // Error interno del servidor
			$json = json_encode(['error' => "Error generando JSON: $jsonError"]);
		}

		return $json; // Retornar el JSON generado

	}

}
