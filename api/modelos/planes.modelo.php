<?php

require_once "conexion.php";

class ModeloPlanesFinanciamiento{

	/*=============================================
	MOSTRAR PLANES DE FINANCIAMIENTO
	=============================================*/

	static public function mdlMostrarPlanesFinanciamiento($tabla, $tabla2, $tabla3, $item, $valor){

		if($item != null){

			$stmt = Conexion::conectar()->prepare("SELECT 	pf.id,
															pf.id_nave,
															n.nave,
															pf.id_plan,
															p.plan,
															pf.precio_mts,
															pf.reserva_porcentaje,
															pf.inicial_porcentaje,
															pf.meses_cantidad,
															pf.meses_porcentaje,
															pf.especiales_cantidad,
															pf.especiales_porcentaje
															FROM $tabla as pf
															LEFT JOIN $tabla2 as n
															ON pf.id_nave = n.id
															LEFT JOIN $tabla3 as p
															ON pf.id_plan = p.id
															WHERE pf.$item = :$item");

			$stmt->bindParam(":".$item, $valor, PDO::PARAM_STR);

			$stmt->execute();

			if($item == "id_nave" || $item == "id_plan"){

				return $stmt->fetchAll();
			} else {
				
				return $stmt->fetch();
			}


		}else{

			$stmt = Conexion::conectar()->prepare("SELECT 	pf.id,
															pf.id_nave,
															n.nave,
															pf.id_plan,
															p.plan,
															pf.precio_mts,
															pf.reserva_porcentaje,
															pf.inicial_porcentaje,
															pf.meses_cantidad,
															pf.meses_porcentaje,
															pf.especiales_cantidad,
															pf.especiales_porcentaje
															FROM $tabla as pf
															LEFT JOIN $tabla2 as n
															ON pf.id_nave = n.id
															LEFT JOIN $tabla3 as p
															ON pf.id_plan = p.id
															ORDER BY pf.id ASC");

			$stmt -> execute();

			return $stmt -> fetchAll();

		}

		$stmt -> close();

		$stmt = null;

	}

	/*=============================================
	EDITAR PLAN DE FINANCIAMIENTO
	=============================================*/

	static public function mdlEditarPlanFinanciamiento($tabla, $datos){

		$stmt = Conexion::conectar()->prepare("	UPDATE $tabla
												SET precio_mts 				= :precio_mts,
													reserva_porcentaje 		= :reserva_porcentaje,
													inicial_porcentaje 		= :inicial_porcentaje,
													meses_cantidad 			= :meses_cantidad,
													meses_porcentaje 		= :meses_porcentaje,
													especiales_cantidad 	= :especiales_cantidad,
													especiales_porcentaje 	= :especiales_porcentaje
												WHERE id = :id");

		$stmt->bindParam(":id", $datos["id"], PDO::PARAM_INT);
		$stmt->bindParam(":precio_mts", $datos["precio_mts"], PDO::PARAM_STR);
		$stmt->bindParam(":reserva_porcentaje", $datos["reserva_porcentaje"], PDO::PARAM_STR);
		$stmt->bindParam(":inicial_porcentaje", $datos["inicial_porcentaje"], PDO::PARAM_STR);
		$stmt->bindParam(":meses_cantidad", $datos["meses_cantidad"], PDO::PARAM_INT);
		$stmt->bindParam(":meses_porcentaje", $datos["meses_porcentaje"], PDO::PARAM_STR);
		$stmt->bindParam(":especiales_cantidad", $datos["especiales_cantidad"], PDO::PARAM_INT);
		$stmt->bindParam(":especiales_porcentaje", $datos["especiales_porcentaje"], PDO::PARAM_STR);

		if($stmt->execute()){

			return "ok";

		}else{

			return "error";		

		}

		$stmt->close();
		$stmt = null;

	}

}	