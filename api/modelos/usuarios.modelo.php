<?php

require_once "conexion.php";

class ModeloUsuarios{

	/*=============================================
	MOSTRAR USUARIOS (GET)
	=============================================*/

	static public function mdlMostrarUsuarios($tabla, $item, $valor){

		// GET un solo usuario

		if($item != null){

			// Preparar la consulta SQL para obtener un usuario específico

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");

			$stmt -> bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt -> execute();

			return $stmt -> fetch(); // Retorna un solo usuario si se encuentra

		}else{

			// GET todos los usuarios
			// Preparar la consulta SQL para obtener todos los usuarios

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla");

			$stmt -> execute();

			return $stmt -> fetchAll(); // Retorna todos los usuarios si no se especifica un item

		}

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	REGISTRO DE USUARIO (POST)
	=============================================*/

	static public function mdlIngresarUsuario($tabla, $datos){

		// Preparar la consulta SQL para insertar un nuevo usuario

		$stmt = Conexion::conectar()->prepare("INSERT INTO 		$tabla	(first_name,
																		last_name,
																		ci,
																		username,
																		password) 
																VALUES 	(:first_name,
																		:last_name,
																		:ci,
																		:username,
																		:password)");

		$stmt->bindParam(":first_name", $datos["first_name"], PDO::PARAM_STR); // Vincular el parámetro first_name
		$stmt->bindParam(":last_name", $datos["last_name"], PDO::PARAM_STR); // Vincular el parámetro last_name
		$stmt->bindParam(":ci", $datos["ci"], PDO::PARAM_STR); // Vincular el parámetro ci
		$stmt->bindParam(":username", $datos["username"], PDO::PARAM_STR); // Vincular el parámetro username
		$stmt->bindParam(":password", $datos["password"], PDO::PARAM_STR); // Vincular el parámetro password

		// Ejecutar la consulta SQL
		if($stmt->execute()){

			return "ok"; // Retornar 'ok' si la inserción fue exitosa

		}else{

			return "error"; // Retornar 'error' si hubo un problema al insertar el usuario
		
		}

		// Cerrar la conexión y liberar recursos
		$stmt->close();
		
		$stmt = null;

	}

	/*=============================================
	EDITAR USUARIO (PUT)
	=============================================*/

	static public function mdlEditarUsuario($tabla, $datos){
	
		$stmt = Conexion::conectar()->prepare("UPDATE $tabla 	SET 	first_name 	= :first_name,
																		last_name 	= :last_name, 
																		ci 			= :ci,
																		updated_at	= :updated_at
																WHERE 	user_id 	= :user_id");

		$stmt->bindParam(":user_id", $datos["user_id"], PDO::PARAM_INT);
		$stmt->bindParam(":first_name", $datos["first_name"], PDO::PARAM_STR);
		$stmt->bindParam(":last_name", $datos["last_name"], PDO::PARAM_STR);
		$stmt->bindParam(":ci", $datos["ci"], PDO::PARAM_STR);
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
	BORRAR USUARIO (DELETE)
	=============================================*/

	static public function mdlEliminarUsuario($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE user_id = :user_id");

		$stmt -> bindParam(":user_id", $datos, PDO::PARAM_INT);

		if($stmt -> execute()){

			return "ok";
		
		}else{

			return "error";	

		}

		$stmt -> close();

		$stmt = null;


	}

	/*=============================================
	ACTUALIZAR USUARIO (PUT)
	=============================================*/

	static public function mdlActualizarUsuario($tabla, $item1, $valor1, $item2, $valor2){

		$stmt = Conexion::conectar()->prepare("UPDATE $tabla SET $item1 = :$item1 WHERE $item2 = :$item2");

		$stmt -> bindParam(":".$item1, $valor1, PDO::PARAM_STR);
		$stmt -> bindParam(":".$item2, $valor2, PDO::PARAM_STR);

		if($stmt -> execute()){

			return "ok";
		
		}else{

			return "error";	

		}

		$stmt -> close();

		$stmt = null;

	}

}