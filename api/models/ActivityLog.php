<?php
namespace App\models;

use PDO;
use Exception;

class ActivityLog {
    private $conn;
    private $table = 'activity_logs';
    
    // Tipos de actividad
    const TYPE_LOGIN_SUCCESS = 'login_success';
    const TYPE_LOGIN_FAILED = 'login_failed';
    const TYPE_LOGOUT = 'logout';
    const TYPE_PASSWORD_CHANGED = 'password_changed';
    const TYPE_PROFILE_UPDATED = 'profile_updated';
    const TYPE_2FA_ENABLED = '2fa_enabled';
    const TYPE_2FA_DISABLED = '2fa_disabled';
    const TYPE_2FA_FAILED = '2fa_failed';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Registrar una actividad
     */
    public function log($userId, $activityType, $description, $metadata = null) {
        try {
            $query = "INSERT INTO " . $this->table . " 
                      (user_id, activity_type, activity_description, ip_address, user_agent, metadata) 
                      VALUES (:user_id, :activity_type, :activity_description, :ip_address, :user_agent, :metadata)";
            
            $stmt = $this->conn->prepare($query);
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $metadataJson = $metadata ? json_encode($metadata) : null;
            
            $stmt->bindParam(':user_id', $userId);
            $stmt->bindParam(':activity_type', $activityType);
            $stmt->bindParam(':activity_description', $description);
            $stmt->bindParam(':ip_address', $ipAddress);
            $stmt->bindParam(':user_agent', $userAgent);
            $stmt->bindParam(':metadata', $metadataJson);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error al registrar actividad: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtener actividades recientes de un usuario
     */
    public function getUserActivities($userId, $limit = 15, $offset = 0) {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                      WHERE user_id = :user_id 
                      ORDER BY created_at DESC 
                      LIMIT :limit OFFSET :offset";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $activities = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                // Decodificar metadata si existe
                if ($row['metadata']) {
                    $row['metadata'] = json_decode($row['metadata'], true);
                }
                $activities[] = $row;
            }
            
            return $activities;
        } catch (Exception $e) {
            error_log("Error al obtener actividades: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener actividades por tipo
     */
    public function getUserActivitiesByType($userId, $activityType, $limit = 10) {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                      WHERE user_id = :user_id 
                      AND activity_type = :activity_type 
                      ORDER BY created_at DESC 
                      LIMIT :limit";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':activity_type', $activityType);
            $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $activities = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row['metadata']) {
                    $row['metadata'] = json_decode($row['metadata'], true);
                }
                $activities[] = $row;
            }
            
            return $activities;
        } catch (Exception $e) {
            error_log("Error al obtener actividades por tipo: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener últimos intentos de login fallidos
     */
    public function getFailedLoginAttempts($userId, $hours = 24) {
        try {
            $query = "SELECT COUNT(*) as count FROM " . $this->table . " 
                      WHERE user_id = :user_id 
                      AND activity_type = :activity_type 
                      AND created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $activityType = self::TYPE_LOGIN_FAILED;
            $stmt->bindParam(':activity_type', $activityType);
            $stmt->bindParam(':hours', $hours, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (Exception $e) {
            error_log("Error al obtener intentos fallidos: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Limpiar logs antiguos (opcional, para mantenimiento)
     */
    public function cleanOldLogs($days = 90) {
        try {
            $query = "DELETE FROM " . $this->table . " 
                      WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':days', $days, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Error al limpiar logs antiguos: " . $e->getMessage());
            return false;
        }
    }
}
