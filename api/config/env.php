<?php
function cargarEnv($path = __DIR__ . '/../.env') {
    if (!file_exists($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];

    foreach ($lines as $line) {
        $parts = explode('=', $line, 2);

        if (count($parts) < 2) {
            continue; 
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);

        $env[$key] = $value;
    }

    return $env;
}

// Cargar variables de entorno globalmente
$GLOBALS['env'] = cargarEnv();
