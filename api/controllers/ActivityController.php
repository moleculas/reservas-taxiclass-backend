<?php
namespace App\controllers;

use App\models\ActivityLog;
use App\helpers\JWTHelper;
use Exception;

class ActivityController {
    private $db;
    private $activityLog;
    
    public function __construct($db) {
        $this->db = $db;
        $this->activityLog = new ActivityLog($db);
    }
    
    /**
     * Obtener actividades recientes del usuario
     */
    public function getUserActivities($request) {
        try {
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                return $this->response(401, 'Token no proporcionado');
            }
            
            try {
                $decoded = JWTHelper::validateToken($token);
                $userId = $decoded['userId'];
                
                // Obtener límite y offset de los parámetros
                $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 15;
                $offset = isset($_GET['offset']) ? max((int)$_GET['offset'], 0) : 0;
                
                // Obtener actividades con paginación
                $activities = $this->activityLog->getUserActivities($userId, $limit, $offset);
                
                // Formatear las actividades para mejor presentación
                $formattedActivities = array_map(function($activity) {
                    return [
                        'id' => $activity['id'],
                        'type' => $activity['activity_type'],
                        'description' => $activity['activity_description'],
                        'ipAddress' => $activity['ip_address'],
                        'userAgent' => $this->parseUserAgent($activity['user_agent']),
                        'metadata' => $activity['metadata'],
                        'createdAt' => $activity['created_at'],
                        'icon' => $this->getActivityIcon($activity['activity_type']),
                        'color' => $this->getActivityColor($activity['activity_type'])
                    ];
                }, $activities);
                
                return $this->response(200, 'Actividades obtenidas', [
                    'activities' => $formattedActivities
                ]);
                
            } catch (Exception $e) {
                return $this->response(401, 'Token inválido');
            }
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en el servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Parsear user agent para mostrar información legible
     */
    private function parseUserAgent($userAgent) {
        if (!$userAgent) return 'Desconocido';
        
        // Detectar navegador
        $browser = 'Desconocido';
        if (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Chrome') !== false && strpos($userAgent, 'Edg') === false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Safari') !== false && strpos($userAgent, 'Chrome') === false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'Edg') !== false) {
            $browser = 'Edge';
        }
        
        // Detectar SO
        $os = 'Desconocido';
        if (strpos($userAgent, 'Windows') !== false) {
            $os = 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $os = 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $os = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $os = 'Android';
        } elseif (strpos($userAgent, 'iPhone') !== false || strpos($userAgent, 'iPad') !== false) {
            $os = 'iOS';
        }
        
        return $browser . ' en ' . $os;
    }
    
    /**
     * Obtener icono para el tipo de actividad
     */
    private function getActivityIcon($activityType) {
        $icons = [
            ActivityLog::TYPE_LOGIN_SUCCESS => 'Login',
            ActivityLog::TYPE_LOGIN_FAILED => 'LoginError',
            ActivityLog::TYPE_LOGOUT => 'Logout',
            ActivityLog::TYPE_PASSWORD_CHANGED => 'Lock',
            ActivityLog::TYPE_PROFILE_UPDATED => 'Person',
            ActivityLog::TYPE_2FA_ENABLED => 'Security',
            ActivityLog::TYPE_2FA_DISABLED => 'SecurityOff',
            ActivityLog::TYPE_2FA_FAILED => 'SecurityError'
        ];
        
        return $icons[$activityType] ?? 'Info';
    }
    
    /**
     * Obtener color para el tipo de actividad
     */
    private function getActivityColor($activityType) {
        $colors = [
            ActivityLog::TYPE_LOGIN_SUCCESS => 'success',
            ActivityLog::TYPE_LOGIN_FAILED => 'error',
            ActivityLog::TYPE_LOGOUT => 'info',
            ActivityLog::TYPE_PASSWORD_CHANGED => 'warning',
            ActivityLog::TYPE_PROFILE_UPDATED => 'info',
            ActivityLog::TYPE_2FA_ENABLED => 'success',
            ActivityLog::TYPE_2FA_DISABLED => 'warning',
            ActivityLog::TYPE_2FA_FAILED => 'error'
        ];
        
        return $colors[$activityType] ?? 'default';
    }
    
    /**
     * Helper para respuestas JSON
     */
    private function response($code, $message, $data = null) {
        http_response_code($code);
        
        $response = [
            'status' => $code < 400 ? 'success' : 'error',
            'message' => $message
        ];
        
        if ($data !== null) {
            $response = array_merge($response, $data);
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
