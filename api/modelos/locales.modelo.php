<?php

require_once "conexion.php";

class ModeloLocales{

	/*=============================================
	MOSTRAR LOCALES
	=============================================*/

	static public function mdlMostrarLocales($tabla, $tabla2, $item, $valor){

		if($item != null){

			$stmt = Conexion::conectar()->prepare("SELECT 	l.id,
															l.numeracion,
															l.metros_cuadrados,
															l.id_nave,
															n.nave
															FROM $tabla as l
															LEFT JOIN $tabla2 as n
															ON l.id_nave = n.id
															WHERE l.$item = :$item");

			$stmt->bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt->execute();

			return $stmt->fetch();

		}else{

			$stmt = Conexion::conectar()->prepare("SELECT 	l.id,
															l.numeracion,
															l.metros_cuadrados,
															l.id_nave,
															n.nave
															FROM $tabla as l
															LEFT JOIN $tabla2 as n
															ON l.id_nave = n.id
															ORDER BY l.id ASC");

			$stmt -> execute();

			return $stmt -> fetchAll();

		}

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	MOSTRAR NAVES
	=============================================*/

	static public function mdlMostrarNaves($tabla, $item, $valor){

		if($item != null){

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");

			$stmt->bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt->execute();

			return $stmt->fetch();

		}else{

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla ORDER BY id ASC");

			$stmt -> execute();

			return $stmt -> fetchAll();

		}

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	MOSTRAR NAVES
	=============================================*/

	static public function mdlMostrarPlanes($tabla, $item, $valor){

		if($item != null){

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla WHERE $item = :$item");

			$stmt->bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt->execute();

			return $stmt->fetch();

		}else{

			$stmt = Conexion::conectar()->prepare("SELECT * FROM $tabla ORDER BY id ASC");

			$stmt -> execute();

			return $stmt -> fetchAll();

		}

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	MOSTRAR DESCRIPCION PLANES
	=============================================*/

	static public function mdlMostrarDescripcionPlanes($tabla, $item, $valor){

		if($item != null){

			$stmt = Conexion::conectar()->prepare("	SELECT 	dp.id,
															dp.id_plan,
															dp.id_local,
															dp.precio_venta,
															dp.reserva,
															dp.inicial,
															dp.cuotas_mensuales,
															dp.cuotas_especiales,
															p.id AS id_plan,
															p.plan
													FROM $tabla as dp
													LEFT JOIN planes as p
													ON dp.id_plan = p.id
													WHERE dp.$item = :$item");

			$stmt->bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt->execute();

			if ($item == "id_local") {
				return $stmt->fetchAll();
			} else {
				return $stmt->fetch();
			}

		}else{

			$stmt = Conexion::conectar()->prepare("SELECT 	dp.id,
															dp.id_plan,
															dp.id_local,
															dp.precio_venta,
															dp.reserva,
															dp.inicial,
															dp.cuotas_mensuales,
															dp.cuotas_especiales,
															p.id AS id_plan,
															p.plan
													FROM $tabla as dp
													LEFT JOIN planes as p
													ON dp.id_plan = p.id
													ORDER BY dp.id ASC");

			$stmt -> execute();

			return $stmt -> fetchAll();

		}

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	BUSCAR LOCALES
	=============================================*/

	static public function mdlBuscarLocales($tabla, $item, $valor){

		$stmt = Conexion::conectar()->prepare("SELECT l.*, n.nave FROM $tabla as l LEFT JOIN naves AS n ON l.id_nave = n.id WHERE l.$item BETWEEN :$item - 2 AND :$item + 2");
												
		$stmt->bindParam(":".$item, $valor, PDO::PARAM_STR);

		$stmt->execute();

		return $stmt->fetchAll();

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	EDITAR NAVE
	=============================================*/

	static public function mdlEditarNave($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("UPDATE $tabla SET nave = :nave WHERE id = :id");

		$stmt->bindParam(":id", $datos["id"], PDO::PARAM_INT);
		$stmt->bindParam(":nave", $datos["nave"], PDO::PARAM_STR);

		if($stmt->execute()){

			return "ok";

		}else{

			return "error";		

		}

		$stmt->close();
		$stmt = null;

	}

	/*=============================================
	EDITAR LOCAL
	=============================================*/

	static public function mdlEditarLocal($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("UPDATE $tabla 	SET 	numeracion 			= :numeracion,
																		metros_cuadrados 	= :metros_cuadrados,
																		id_nave 			= :id_nave
																WHERE 	id 					= :id");

		$stmt->bindParam(":id", $datos["id"], PDO::PARAM_INT);
		$stmt->bindParam(":numeracion", $datos["numeracion"], PDO::PARAM_STR);
		$stmt->bindParam(":metros_cuadrados", $datos["metros_cuadrados"], PDO::PARAM_STR);
		$stmt->bindParam(":id_nave", $datos["id_nave"], PDO::PARAM_INT);

		if($stmt->execute()){

			return "ok";

		}else{

			return "error";		

		}

		$stmt->close();
		$stmt = null;

	}

	/*=============================================
	ELIMINAR LOCAL
	=============================================*/

	static public function mdlEliminarLocal($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("DELETE FROM $tabla WHERE id = :id");

		$stmt -> bindParam(":id", $datos, PDO::PARAM_INT);

		if($stmt -> execute()){

			return true;
		
		}else{

			return false;	

		}

		$stmt -> close();

		$stmt = null;
	}

}	