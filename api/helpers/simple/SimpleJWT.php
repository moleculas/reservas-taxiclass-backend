<?php
namespace App\helpers\simple;

/**
 * JWT simple sin dependencias externas
 * SOLO PARA DESARROLLO - NO USAR EN PRODUCCIÓN
 */
class SimpleJWT {
    
    public static function encode($payload, $key) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);
        
        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        
        $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, $key, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    public static function decode($token, $key) {
        $parts = explode('.', $token);
        
        if (count($parts) != 3) {
            throw new \Exception('Token inválido');
        }
        
        list($header, $payload, $signature) = $parts;
        
        $validSignature = hash_hmac('sha256', $header . "." . $payload, $key, true);
        $validBase64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));
        
        if ($validBase64Signature !== $signature) {
            throw new \Exception('Firma inválida');
        }
        
        $payload = json_decode(base64_decode($payload), true);
        
        // Verificar expiración
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \Exception('Token expirado');
        }
        
        return $payload;
    }
}
