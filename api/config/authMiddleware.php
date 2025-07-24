<?php

require_once __DIR__ . '/autenticacion.php';

class AuthMiddleware {
    public static function handle() {
        // Obtener el token del encabezado authorization
        $headers = apache_request_headers();
        $authHeader = $headers['Authorization'] ?? '';

        // Verificar si existe el encabezado
        if (empty($authHeader)) {
            http_response_code(401);
            echo json_encode(['error' => 'Token no proporcionado']);
            exit;
        }

        // Extraer el token del formato "Bearer <token>"
        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            http_response_code(401);
            echo json_encode(['error' => 'Formato de token inválido']);
            exit;
        }

        $token = $matches[1];

        // Validar el token
        $tokenData = Autenticacion::validarToken($token);
        
        if (!$tokenData) {
            http_response_code(401);
            echo json_encode(['error' => 'Token inválido o expirado']);
            exit;
        }

        // Guardar datos del token para uso posterior
        $GLOBALS['user_id'] = $tokenData['user_id'];
    }
}