<?php

require_once "conexion.php";

class ModeloProfesores{

	/*=============================================
	MOSTRAR PROFESORES (GET)
	=============================================*/

	static public function mdlMostrarProfesores($tabla, $item, $valor){

		// GET un solo profesor

		if($item != null){

			// Preparar la consulta SQL para obtener un profesor en específico

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");

			$stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt -> execute();

			return $stmt -> fetch(); // Retorna un solo profesor si se encuentra

		}else{

			// GET todos los profesores
			// Preparar la consulta SQL para obtener todos los profesores

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla");

			$stmt -> execute();

			return $stmt -> fetchAll(); // Retorna todos los profesores si no se especifica un item

		}

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	REGISTRO DE PROFESOR (POST)
	=============================================*/

	static public function mdlCrearProfesor($tabla, $datos){

		// Preparar la consulta SQL para insertar un nuevo profesor

		$stmt = Conexion::conectar()->prepare("INSERT INTO 		$tabla	(name,
																		ci_code) 
																VALUES 	(:name,
																		:ci_code)");

		$stmt->bindParam(":name", $datos["name"], PDO::PARAM_STR); // Vincular el parámetro name
		$stmt->bindParam(":ci_code", $datos["ci_code"], PDO::PARAM_STR); // Vincular el parámetro ci_code

		// Ejecutar la consulta SQL
		if($stmt->execute()){

			return "ok"; // Retornar 'ok' si la inserción fue exitosa

		}else{

			return "error"; // Retornar 'error' si hubo un problema al insertar el profesor
		
		}

		// Cerrar la conexión y liberar recursos
		$stmt->close();
		
		$stmt = null;

	}

	/*=============================================
	EDITAR PROFESOR (PUT)
	=============================================*/

	static public function mdlEditarProfesor($tabla, $datos){
	
		$stmt = Conexion::conectar()->prepare("UPDATE $tabla 	SET 	name 		= :name,
																		ci_code		= :ci_code,
																		updated_at	= :updated_at
																WHERE 	teacher_id 	= :teacher_id");

		$stmt->bindParam(":teacher_id", $datos["teacher_id"], PDO::PARAM_INT);
		$stmt->bindParam(":name", $datos["name"], PDO::PARAM_STR);
		$stmt->bindParam(":ci_code", $datos["ci_code"], PDO::PARAM_STR);
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
	BORRAR PROFESOR (DELETE)
	=============================================*/

	static public function mdlEliminarProfesor($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE teacher_id = :teacher_id");

		$stmt -> bindParam(":teacher_id", $datos, PDO::PARAM_INT);

		if($stmt -> execute()){

			return "ok";
		
		}else{

			return "error";	

		}

		$stmt -> close();

		$stmt = null;


	}

}