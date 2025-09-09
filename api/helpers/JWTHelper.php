<?php
namespace App\helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class JWTHelper {
    private static $secretKey;
    private static $algorithm = 'HS256';
    private static $tokenExpiration = 3600; // 1 hora
    private static $refreshTokenExpiration = 604800; // 7 días
    
    public static function init() {
        self::$secretKey = $_ENV['JWT_SECRET'] ?? 'default_secret_key_change_in_production';
    }
    
    /**
     * Generar token JWT
     */
    public static function generateToken($userId, $email, $isTempToken = false) {
        self::init();
        
        $issuedAt = time();
        $expiration = $issuedAt + ($isTempToken ? 300 : self::$tokenExpiration); // 5 min para temp tokens
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expiration,
            'userId' => $userId,
            'email' => $email,
            'isTemp' => $isTempToken
        ];
        
        return JWT::encode($payload, self::$secretKey, self::$algorithm);
    }
    
    /**
     * Generar refresh token
     */
    public static function generateRefreshToken($userId) {
        self::init();
        
        $issuedAt = time();
        $expiration = $issuedAt + self::$refreshTokenExpiration;
        
        $payload = [
            'iat' => $issuedAt,
            'exp' => $expiration,
            'userId' => $userId,
            'type' => 'refresh'
        ];
        
        return JWT::encode($payload, self::$secretKey, self::$algorithm);
    }
    
    /**
     * Validar y decodificar token
     */
    public static function validateToken($token) {
        self::init();
        
        try {
            $decoded = JWT::decode($token, new Key(self::$secretKey, self::$algorithm));
            return (array) $decoded;
        } catch (Exception $e) {
            throw new Exception('Token inválido: ' . $e->getMessage());
        }
    }
    
    /**
     * Extraer token del header Authorization
     */
    public static function getBearerToken() {
        $headers = getallheaders();
        
        if (!isset($headers['Authorization'])) {
            return null;
        }
        
        $authHeader = $headers['Authorization'];
        
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Verificar si el token es temporal
     */
    public static function isTempToken($decodedToken) {
        return isset($decodedToken['isTemp']) && $decodedToken['isTemp'] === true;
    }
}
