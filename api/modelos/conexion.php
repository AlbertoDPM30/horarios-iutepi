<?php

require_once __DIR__ . '/../config/env.php';

class Conexion
{

	static public function conectar()
	{

		$engine = $GLOBALS['env']['DB_ENGINE'];
		$host = $GLOBALS['env']['DB_HOST'];
		$user = $GLOBALS['env']['DB_USER'];
		$pass = $GLOBALS['env']['DB_PASS'];
		$dbname = $GLOBALS['env']['DB_NAME'];

		$link = new PDO(
			$engine.':host='.$host.';'.'dbname='.$dbname,
			$user,
			$pass
		);
		
		$link->exec("set names utf8");

		return $link;
	}
}

?>