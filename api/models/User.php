<?php
namespace App\models;

use PDO;
use Exception;

class User {
    private $conn;
    private $table = 'users';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Buscar usuario por email
     */
    public function findByEmail($email) {
        $query = "SELECT id, email, password, name, phone, account, two_factor_enabled, two_factor_email, 
                  two_factor_verified_at, created_at, updated_at, password_updated_at 
                  FROM " . $this->table . " WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Buscar usuario por ID
     */
    public function findById($id) {
        $query = "SELECT id, email, password, name, phone, account, two_factor_enabled, two_factor_email, 
                  two_factor_verified_at, created_at, updated_at, password_updated_at 
                  FROM " . $this->table . " WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear nuevo usuario
     */
    public function create($data) {
        $query = "INSERT INTO " . $this->table . " 
                  (email, password, name, phone) 
                  VALUES (:email, :password, :name, :phone)";
        
        $stmt = $this->conn->prepare($query);
        
        // Hash de la contraseña
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':phone', $data['phone']);
        
        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Verificar contraseña
     */
    public function verifyPassword($password, $hashedPassword) {
        return password_verify($password, $hashedPassword);
    }
    
    /**
     * Generar código 2FA
     */
    public function generateTwoFactorCode($userId) {
        // Generar código de 6 dígitos
        $code = sprintf('%06d', mt_rand(0, 999999));
        
        // Guardar en la base de datos con expiración
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        
        $query = "INSERT INTO two_factor_attempts (user_id, code, expires_at) 
                  VALUES (:user_id, :code, :expires_at)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':code', $code);
        $stmt->bindParam(':expires_at', $expiresAt);
        
        if ($stmt->execute()) {
            return $code;
        }
        
        return false;
    }
    
    /**
     * Verificar código 2FA
     */
    public function verifyTwoFactorCode($userId, $code) {
        $query = "SELECT * FROM two_factor_attempts 
                  WHERE user_id = :user_id 
                  AND code = :code 
                  AND used = 0 
                  AND expires_at > NOW() 
                  ORDER BY created_at DESC 
                  LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':code', $code);
        $stmt->execute();
        
        $attempt = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($attempt) {
            // Marcar el código como usado
            $updateQuery = "UPDATE two_factor_attempts SET used = 1 WHERE id = :id";
            $updateStmt = $this->conn->prepare($updateQuery);
            $updateStmt->bindParam(':id', $attempt['id']);
            $updateStmt->execute();
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Registrar sesión de usuario
     */
    public function createSession($userId, $token, $ipAddress = null, $userAgent = null) {
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $query = "INSERT INTO user_sessions (user_id, token, ip_address, user_agent, expires_at) 
                  VALUES (:user_id, :token, :ip_address, :user_agent, :expires_at)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':ip_address', $ipAddress);
        $stmt->bindParam(':user_agent', $userAgent);
        $stmt->bindParam(':expires_at', $expiresAt);
        
        return $stmt->execute();
    }
    
    /**
     * Invalidar sesiones de usuario
     */
    public function invalidateSessions($userId) {
        $query = "DELETE FROM user_sessions WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        
        return $stmt->execute();
    }
    
    /**
     * Verificar si el usuario tiene 2FA habilitado
     */
    public function hasTwoFactorEnabled($userId) {
        $user = $this->findById($userId);
        return $user && $user['two_factor_enabled'] == 1;
    }
    
    /**
     * Activar 2FA para un usuario
     */
    public function enableTwoFactor($userId, $twoFactorEmail) {
        $query = "UPDATE users SET 
                  two_factor_enabled = 1, 
                  two_factor_email = :two_factor_email,
                  two_factor_verified_at = NOW()
                  WHERE id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':two_factor_email', $twoFactorEmail);
        
        return $stmt->execute();
    }
    
    /**
     * Desactivar 2FA para un usuario
     */
    public function disableTwoFactor($userId) {
        $query = "UPDATE users SET 
                  two_factor_enabled = 0,
                  two_factor_email = NULL,
                  two_factor_verified_at = NULL
                  WHERE id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        
        return $stmt->execute();
    }
    
    /**
     * Actualizar perfil del usuario
     */
    public function updateProfile($userId, $updates) {
        $fields = [];
        $params = [':user_id' => $userId];
        
        // Debug: mostrar qué actualizaciones llegan
        error_log("DEBUG User::updateProfile - userId: $userId");
        error_log("DEBUG User::updateProfile - updates: " . json_encode($updates));
        
        foreach ($updates as $field => $value) {
            $fields[] = "$field = :$field";
            $params[":$field"] = $value;
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $query = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = :user_id";
        
        // Debug: mostrar query y parámetros
        error_log("DEBUG User::updateProfile - query: $query");
        error_log("DEBUG User::updateProfile - params: " . json_encode($params));
        
        $stmt = $this->conn->prepare($query);
        
        $result = $stmt->execute($params);
        
        // Debug: mostrar resultado
        error_log("DEBUG User::updateProfile - execute result: " . ($result ? 'true' : 'false'));
        
        if (!$result) {
            error_log("DEBUG User::updateProfile - Error: " . json_encode($stmt->errorInfo()));
        }
        
        return $result;
    }
    
    /**
     * Cambiar contraseña del usuario
     */
    public function changePassword($userId, $newPassword) {
        // Hash de la nueva contraseña
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        $query = "UPDATE users SET 
                  password = :password,
                  password_updated_at = NOW(),
                  updated_at = NOW()
                  WHERE id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':password', $hashedPassword);
        $stmt->bindParam(':user_id', $userId);
        
        return $stmt->execute();
    }
}
