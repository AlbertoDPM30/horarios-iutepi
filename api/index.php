<?php

/**
 * =====================================================================
 *  API · Sistema de Horarios Academicos IUTEPI
 * ---------------------------------------------------------------------
 *  Punto de entrada unico. Todo el trafico entra por aqui gracias al
 *  rewrite del .htaccess; no hay ningun otro archivo PHP publico.
 *
 *  Requisitos: PHP 8.0+ con pdo_mysql, mbstring, json y curl.
 *  Sin composer: la API se puede subir por FTP tal cual.
 * =====================================================================
 */

require_once __DIR__ . '/app/Core/App.php';

App\Core\App::iniciar();
