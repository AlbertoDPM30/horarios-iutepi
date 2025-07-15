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
				]);
			} else {

				// Si algo falla retornará un status 500
				http_response_code(500);
				return json_encode([
					"status" => 500,
					"success" => false,
					"data" => null,
					"mensaje" => "error al crear la nueva materia",
				]);
			}

		}
	}

	/*=============================================
	EDITAR MATERIA
	=============================================*/

	static public function ctrEditarMateria()
	{

		$tabla = "subjects";

		date_default_timezone_set('America/Caracas');

		$fechaActualizacion = date('Y-m-d H:i:s');
		
		// Crear un array con los datos de la materia a editar
		$datos = array(
		"subject_id" => $_POST["editarIdMateria"],
		"name" => trim($_POST["editarMateria"]),
		"duration_hours" => trim($_POST["editarHorasDuracion"]),
		"semester" => trim($_POST["editarSemestre"]),
		"is_assigned" => trim($_POST["editarAsignado"]),
		"updated_at" => $fechaActualizacion
		);

		$respuesta = ModeloMaterias::mdlEditarMateroa($tabla, $datos);

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
					"asignado" => $datos["is_assigned"],
					"fecha_actualizacion" => $datos["updated_at"]
				],
				"mensaje" => "Materia actualizada correctamente"
			]);
		} else {
		
		// Si algo falla retornará un status 500 y un mensaje de error
		http_response_code(500);
		return json_encode([
			"status" => 500,
			"success" => false,
			"Error" => "No se pudo actualizar la Materia",
			"mensaje" => "Ha ocurrido un problema al intentar actualizar esta materia, Contacte con un Administrador"
		]);
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
