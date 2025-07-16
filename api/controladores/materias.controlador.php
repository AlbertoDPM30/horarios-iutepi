<?php

class ControladorMaterias
{

	/*=============================================
	MOSTRAR MATERIAS
	=============================================*/

	static public function ctrMostrarMaterias($item, $valor)
	{

		$tabla = "subjects"; // Definimos la tabla de la DB

		$respuesta = ModeloMaterias::MdlMostrarMaterias($tabla, $item, $valor);

		return $respuesta;
	}

	/*=============================================
	REGISTRAR MATERIA
	=============================================*/

	static public function ctrCrearMateria()
	{

		if (isset($_POST["nuevaMateria"])) {

			$tabla = "subjects"; // Tabla de materias en la base de datos

			header('Content-Type: application/json; charset=utf-8'); //  Establecer cabeceras para JSON + UTF-8

			// Crear un array con los datos del nueva materia
			$datos = array(
				"name" => trim($_POST["nuevaMateria"]),
				"duration_hours" => trim($_POST["nuevaHorasDuracion"]),
				"semester" => trim($_POST["nuevoSemestre"]),
				"is_assigned" => trim($_POST["nuevoAsignado"])
			);

			$respuesta = ModeloMaterias::mdlCrearMateria($tabla, $datos);

			// Verificar la respuesta del controlador
			if ($respuesta == "ok") {

				// Si es correcta mostrará los datos recién registrados
				http_response_code(201);
				return json_encode([
					"status" => 201,
					"success" => true,
					"data" => [
						"materia" => $datos["name"],
						"horas_duracion" => $datos["duration_hours"],
						"semestre" => $datos["semester"],
						"asignado" => $datos["is_assigned"],
					],
					"mensaje" => "Materia creada correctamente"
				], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			} else {

				// Si algo falla retornará un status 500
				http_response_code(500);
				return json_encode([
					"status" => 500,
					"success" => false,
					"data" => null,
					"mensaje" => "error al crear la nueva materia",
				], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
			}


		}
	}

	/*=============================================
	EDITAR MATERIA
	=============================================*/

	static public function ctrEditarMateria()
	{

		$tabla = "subjects";

		header('Content-Type: application/json; charset=utf-8'); //  Establecer cabeceras para JSON + UTF-8

		// Crear un array con los datos de la materia a editar
		$datos = array(
			"subject_id" => $_POST["editarIdMateria"],
			"name" => trim($_POST["editarMateria"]),
			"duration_hours" => trim($_POST["editarHorasDuracion"]),
			"semester" => trim($_POST["editarSemestre"]),
			"is_assigned" => trim($_POST["editarAsignado"])
		);

		$respuesta = ModeloMaterias::mdlEditarMateria($tabla, $datos);

		//Recibimos la respuesta
		if ($respuesta == "ok") {

			// Retornamos la respuesta con los datos actualizados
			http_response_code(201);
			return json_encode([
				"status" => 201,
				"success" => true,
				"data" => [
					"id" => $datos["subject_id"],
					"materia" => $datos["name"],
					"horas_duracion" => $datos["duration_hours"],
					"semestre" => $datos["semester"],
					"asignado" => $datos["is_assigned"]
				],
				"mensaje" => "Materia actualizada correctamente"
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

		} else {
		
			// Si algo falla retornará un status 500 y un mensaje de error
			http_response_code(500);
			return json_encode([
				"status" => 500,
				"success" => false,
				"Error" => "No se pudo actualizar la Materia",
				"mensaje" => "Ha ocurrido un problema al intentar actualizar esta materia, Contacte con un Administrador"
			], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		}


	}

	/*=============================================
	ELIMINAR MATERIA
	=============================================*/

	static public function ctrEliminarMateria(){

		$tabla ="subjects";

		$datos = $_POST["EliminarIdMateria"]; // Recibir el id a eliminar

		$respuesta = ModeloMaterias::mdlEliminarMateria($tabla, $datos);

		// Verificamos la respuesta del modelo
		if ($respuesta == "ok") {

			// Si la respuesta es correcta, retornamos un status 200 y un mensaje de éxito
			http_response_code(200);
			return json_encode([
				"status" => 200,
				"success" => true,
				"mensaje" => "Materia eliminada con exito"
			]);
		} else {

			// Si la respuesta es incorrecta, retornamos un status 500 y un mensaje de error
			http_response_code(500);
			return json_encode([
				"status" => 500,
				"success" => false,
				"error" => "Materia NO eliminada",
				"mensaje" => "Ha ocurrido un problema al intentar eliminar esta Materia, Contacte con un Administrador"
			]);
		}

	}

}
