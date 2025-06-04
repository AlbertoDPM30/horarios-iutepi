<?php

class Conexion
{

	static public function conectar()
	{

		$link = new PDO(
			"mysql:host=localhost;dbname=horarios",
			"root",
			""
		);
		
		// $link = new PDO(
		// 	"mysql:host=localhost;dbname=prueba_horarios",
		// 	"root",
		// 	""
		// );

		$link->exec("set names utf8");

		return $link;
	}
}

?>