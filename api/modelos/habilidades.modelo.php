<?php

require_once "conexion.php";

class ModeloHabilidades{

	/*=============================================
	MOSTRAR HABILIDADES (GET)
	=============================================*/

	static public function mdlMostrarHabilidades($tabla, $item, $valor){

		// GET una sola habilidad

		if($item != null){

			// Preparar la consulta SQL para obtener una habilidad en específico

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");

			$stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt -> execute();

			return $stmt -> fetch(); // Retorna un solo habilidad si se encuentra

		}else{

			// GET todas las habilidades
			// Preparar la consulta SQL para obtener todas las habilidades

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla");

			$stmt -> execute();

			return $stmt -> fetchAll(); // Retorna todas las habilidad si no se especifica un item

		}

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	REGISTRO DE HABILIDAD (POST)
	=============================================*/

	static public function mdlCrearHabilidad($tabla, $datos){

		// Preparar la consulta SQL para insertar una nueva habilidad

		$stmt = Conexion::conectar()->prepare("INSERT INTO $tabla (skill_name) VALUES (:skill_name)");

		$stmt->bindParam(":skill_name", $datos["skill_name"], PDO::PARAM_STR); // Vincular el parámetro skill_name

		// Ejecutar la consulta SQL
		if($stmt->execute()){

			return "ok"; // Retornar 'ok' si la inserción fue exitosa

		}else{

			return "error"; // Retornar 'error' si hubo un problema al insertar la habilidad
		
		}

		// Cerrar la conexión y liberar recursos
		$stmt->close();
		
		$stmt = null;

	}

	/*=============================================
	EDITAR HABILIDAD (PUT)
	=============================================*/

	static public function mdlEditarHabilidad($tabla, $datos){
	
		$stmt = Conexion::conectar()->prepare("UPDATE $tabla 	SET 	skill_name 	= :skill_name,
																		updated_at	= :updated_at
																WHERE 	skill_id 	= :skill_id");

		$stmt->bindParam(":skill_id", $datos["skill_id"], PDO::PARAM_INT);
		$stmt->bindParam(":skill_name", $datos["skill_name"], PDO::PARAM_STR);
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
	BORRAR HABILIDAD (DELETE)
	=============================================*/

	static public function mdlEliminarHabilidad($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE skill_id = :skill_id");

		$stmt -> bindParam(":skill_id", $datos, PDO::PARAM_INT);

		if($stmt -> execute()){

			return "ok";
		
		}else{

			return "error";	

		}

		$stmt -> close();

		$stmt = null;


	}

}