<?php

require_once __DIR__ . '/../vendor/autoload.php'; // Asegúrate de que el autoload de Composer esté configurado correctamente
require_once __DIR__ . '/env.php'; // Cargar las variables de entorno

// Autoload de Composer para cargar las dependencias
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class Autenticacion {
    private static $secret_key; // Clave secreta para firmar el token
    private static $algoritmo; // Algoritmo de firma

    public static function init() {
        self::$secret_key = $GLOBALS['env']['SECRET_KEY']; // Clave secreta del JWT
        self::$algoritmo = $GLOBALS['env']['JWT_ALGORITHM']; // Algoritmo de firma del JWT
    }

    public static function generarToken($userId) {
        self::init();
        $payload = [
            "iat" => time(), // Tiempo de emisión
            "exp" => time() + 18000, // Expira en 5 hora
            "user_id" => $userId // ID del usuario
        ];
        return JWT::encode($payload, self::$secret_key, self::$algoritmo);
    }

    public static function validarToken($token) {
        self::init();
        try {
            // Decodificar el token
            $decoded = JWT::decode($token, new Key(self::$secret_key, self::$algoritmo));
            return (array) $decoded;
        } catch (Exception $e) {
            // Si hay un error al decodificar el token, significa que no es válido
            return false;
        }
    }
}