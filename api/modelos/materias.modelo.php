<?php

require_once "conexion.php";

class ModeloMaterias{

	/*=============================================
	MOSTRAR MATERIA (GET)
	=============================================*/

	static public function mdlMostrarMateria($tabla, $item, $valor){

		// GET una sola materia

		if($item != null){

			// Preparar la consulta SQL para obtener una materia en específico

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");

			$stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt -> execute();

			return $stmt -> fetch(); // Retorna un solo materia si se encuentra

		}else{

			// GET todas las materias
			// Preparar la consulta SQL para obtener todas las materias

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla");

			$stmt -> execute();

			return $stmt -> fetchAll(); // Retorna todas las materia si no se especifica un item

		}

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	REGISTRO DE MATERIA (POST)
	=============================================*/

	static public function mdlCrearMateria($tabla, $datos){

		// Preparar la consulta SQL para insertar una nueva materia

		$stmt = Conexion::conectar()->prepare("INSERT INTO 	$tabla
															(name,
															duration_hours,
															semester,
															is_assigned)
													VALUES 	(:name,
															:duration_hours,
															:semester,
															:is_assigned)");

		$stmt->bindParam(":name", $datos["name"], PDO::PARAM_STR); // Vincular el parámetro name
		$stmt->bindParam(":duration_hours", $datos["duration_hours"], PDO::PARAM_STR); // Vincular el parámetro duration_hours
		$stmt->bindParam(":semester", $datos["semester"], PDO::PARAM_STR); // Vincular el parámetro semester
		$stmt->bindParam(":is_assigned", $datos["is_assigned"], PDO::PARAM_INT); // Vincular el parámetro is_assigned

		// Ejecutar la consulta SQL
		if($stmt->execute()){

			return "ok"; // Retornar 'ok' si la inserción fue exitosa

		}else{

			return "error"; // Retornar 'error' si hubo un problema al insertar la materia
		
		}

		// Cerrar la conexión y liberar recursos
		$stmt->close();
		
		$stmt = null;

	}

	/*=============================================
	EDITAR MATERIA (PUT)
	=============================================*/

	static public function mdlEditarMateria($tabla, $datos){
	
		$stmt = Conexion::conectar()->prepare("UPDATE $tabla 	SET 	name 			= :name,
																		duration_hours 	= :duration_hours,
																		semester 		= :semester,
																		is_assigned 	= :is_assigned,
																		updated_at		= :updated_at
																WHERE 	subject_id 		= :subject_id");

		$stmt->bindParam(":subject_id", $datos["subject_id"], PDO::PARAM_INT);
		$stmt->bindParam(":name", $datos["name"], PDO::PARAM_STR);
		$stmt->bindParam(":duration_hours", $datos["duration_hours"], PDO::PARAM_STR);
		$stmt->bindParam(":semester", $datos["semester"], PDO::PARAM_STR);
		$stmt->bindParam(":is_assigned", $datos["is_assigned"], PDO::PARAM_INT);
		$stmt->bindParam(":updated_at", $datos["updated_at"], PDO::PARAM_STR);

		if($stmt -> execute()){

			return "ok";
		
		}else{

			return "error";	

		}

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	BORRAR MATERIA (DELETE)
	=============================================*/

	static public function mdlEliminarMateria($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE subject_id = :subject_id");

		$stmt -> bindParam(":subject_id", $datos, PDO::PARAM_INT);

		if($stmt -> execute()){

			return "ok";
		
		}else{

			return "error";	

		}

		$stmt -> close();

		$stmt = null;


	}

}