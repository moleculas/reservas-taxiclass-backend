<?php
namespace App\controllers;

use App\models\User;
use App\helpers\JWTHelper;
use App\services\EmailService;
use App\models\ActivityLog;
use Exception;
use PDO;

class AuthController {
    private $db;
    private $userModel;
    private $emailService;
    private $activityLog;
    
    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($db);
        $this->emailService = new EmailService();
        $this->activityLog = new ActivityLog($db);
    }
    
    /**
     * Login endpoint
     */
    public function login($request) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            
            // Validar datos requeridos
            if (!isset($data['email']) || !isset($data['password'])) {
                return $this->response(400, 'Email y contraseña son requeridos');
            }
            
            $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
            $password = $data['password'];
            
            // Buscar usuario
            $user = $this->userModel->findByEmail($email);
            
            if (!$user) {
                return $this->response(401, 'Credenciales incorrectas');
            }
            
            // Verificar contraseña
            if (!$this->userModel->verifyPassword($password, $user['password'])) {
                // Registrar intento fallido
                $this->activityLog->log(
                    $user['id'],
                    ActivityLog::TYPE_LOGIN_FAILED,
                    'Intento de inicio de sesión fallido - contraseña incorrecta'
                );
                return $this->response(401, 'Credenciales incorrectas');
            }
            
            // Verificar si tiene 2FA habilitado
            error_log("DEBUG: Usuario encontrado - " . json_encode($user));
            error_log("DEBUG: 2FA habilitado? " . $user['two_factor_enabled']);
            
            if ($user['two_factor_enabled']) {
                // Generar código 2FA
                $code = $this->userModel->generateTwoFactorCode($user['id']);
                
                // Enviar código por email
                $emailDestino = $user['two_factor_email'] ?? $user['email'];
                $emailResult = $this->emailService->sendTwoFactorCode($emailDestino, $user['name'], $code);
                
                if (!$emailResult['success']) {
                    error_log("Error al enviar email 2FA: " . ($emailResult['error'] ?? 'Error desconocido'));
                    return $this->response(500, 'Error al enviar el código de verificación. Por favor, intenta nuevamente.');
                }
                
                // Generar token temporal
                $tempToken = JWTHelper::generateToken($user['id'], $user['email'], true);
                
                $responseData = [
                    'requiresTwoFactor' => true,
                    'tempToken' => $tempToken,
                    'emailSent' => $emailResult['success'],
                    'email' => substr($emailDestino, 0, 3) . '****' . substr($emailDestino, strpos($emailDestino, '@'))
                ];
                
                return $this->response(200, 'Código de verificación enviado a tu email', $responseData);
            }
            
            // Login exitoso sin 2FA
            $token = JWTHelper::generateToken($user['id'], $user['email']);
            $refreshToken = JWTHelper::generateRefreshToken($user['id']);
            
            // Registrar sesión
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $this->userModel->createSession($user['id'], $token, $ipAddress, $userAgent);
            
            // Registrar actividad de login exitoso sin 2FA
            $this->activityLog->log(
                $user['id'],
                ActivityLog::TYPE_LOGIN_SUCCESS,
                'Inicio de sesión exitoso'
            );
            
            // Preparar datos del usuario (sin información sensible)
            $userData = [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'phone' => $user['phone'],
                'account' => $user['account'],
                'twoFactorEnabled' => (bool)$user['two_factor_enabled'],
                'createdAt' => $user['created_at'],
                'passwordUpdatedAt' => $user['password_updated_at']
            ];
            
            return $this->response(200, 'Login exitoso', [
                'token' => $token,
                'refreshToken' => $refreshToken,
                'user' => $userData
            ]);
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en el servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Verificar código 2FA
     */
    public function verifyTwoFactor($request) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!isset($data['tempToken']) || !isset($data['code'])) {
                return $this->response(400, 'Token temporal y código son requeridos');
            }
            
            // Validar token temporal
            try {
                $decoded = JWTHelper::validateToken($data['tempToken']);
                
                if (!JWTHelper::isTempToken($decoded)) {
                    return $this->response(401, 'Token inválido');
                }
                
                $userId = $decoded['userId'];
                
            } catch (Exception $e) {
                return $this->response(401, 'Token temporal inválido o expirado');
            }
            
            // Verificar código 2FA
            if (!$this->userModel->verifyTwoFactorCode($userId, $data['code'])) {
                // Registrar intento fallido de 2FA
                $this->activityLog->log(
                    $userId,
                    ActivityLog::TYPE_2FA_FAILED,
                    'Código de verificación 2FA incorrecto'
                );
                return $this->response(401, 'Código de verificación incorrecto');
            }
            
            // Obtener usuario
            $user = $this->userModel->findById($userId);
            
            if (!$user) {
                return $this->response(404, 'Usuario no encontrado');
            }
            
            // Generar tokens definitivos
            $token = JWTHelper::generateToken($user['id'], $user['email']);
            $refreshToken = JWTHelper::generateRefreshToken($user['id']);
            
            // Registrar sesión
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $this->userModel->createSession($user['id'], $token, $ipAddress, $userAgent);
            
            // Preparar datos del usuario
            $userData = [
                'id' => $user['id'],
                'email' => $user['email'],
                'name' => $user['name'],
                'phone' => $user['phone'],
                'account' => $user['account'],
                'twoFactorEnabled' => true,
                'createdAt' => $user['created_at'],
                'passwordUpdatedAt' => $user['password_updated_at']
            ];
            
            return $this->response(200, 'Verificación exitosa', [
                'token' => $token,
                'refreshToken' => $refreshToken,
                'user' => $userData
            ]);
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en el servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Logout
     */
    public function logout($request) {
        try {
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                return $this->response(401, 'Token no proporcionado');
            }
            
            try {
                $decoded = JWTHelper::validateToken($token);
                $userId = $decoded['userId'];
                
                // Invalidar todas las sesiones del usuario
                $this->userModel->invalidateSessions($userId);
                
                // Registrar actividad de logout
                $this->activityLog->log(
                    $userId,
                    ActivityLog::TYPE_LOGOUT,
                    'Cierre de sesión'
                );
                
                return $this->response(200, 'Sesión cerrada exitosamente');
                
            } catch (Exception $e) {
                return $this->response(401, 'Token inválido');
            }
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en el servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Obtener información del usuario actual
     */
    public function me($request) {
        try {
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                return $this->response(401, 'Token no proporcionado');
            }
            
            try {
                $decoded = JWTHelper::validateToken($token);
                $userId = $decoded['userId'];
                
                $user = $this->userModel->findById($userId);
                
                if (!$user) {
                    return $this->response(404, 'Usuario no encontrado');
                }
                
                // Eliminar información sensible
                unset($user['password']);
                unset($user['two_factor_secret']);
                
                // Preparar datos del usuario
                $userData = [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'phone' => $user['phone'],
                    'account' => $user['account'],
                    'twoFactorEnabled' => (bool)$user['two_factor_enabled'],
                    'createdAt' => $user['created_at'],
                    'passwordUpdatedAt' => $user['password_updated_at']
                ];
                
                return $this->response(200, 'Usuario encontrado', [
                    'user' => $userData
                ]);
                
            } catch (Exception $e) {
                return $this->response(401, 'Token inválido');
            }
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en el servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Verificar si el token es válido
     */
    public function verify($request) {
        try {
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                return $this->response(401, 'Token no proporcionado');
            }
            
            try {
                $decoded = JWTHelper::validateToken($token);
                $userId = $decoded['userId'];
                
                $user = $this->userModel->findById($userId);
                
                if (!$user) {
                    return $this->response(401, 'Token inválido');
                }
                
                // Eliminar información sensible
                unset($user['password']);
                unset($user['two_factor_secret']);
                
                return $this->response(200, 'Token válido', [
                    'valid' => true,
                    'user' => $user
                ]);
                
            } catch (Exception $e) {
                return $this->response(401, 'Token inválido', [
                    'valid' => false
                ]);
            }
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en el servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Refrescar token
     */
    public function refresh($request) {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            
            if (!isset($data['refreshToken'])) {
                return $this->response(400, 'Refresh token es requerido');
            }
            
            try {
                $decoded = JWTHelper::validateToken($data['refreshToken']);
                
                if (!isset($decoded['type']) || $decoded['type'] !== 'refresh') {
                    return $this->response(401, 'Token inválido');
                }
                
                $userId = $decoded['userId'];
                $user = $this->userModel->findById($userId);
                
                if (!$user) {
                    return $this->response(404, 'Usuario no encontrado');
                }
                
                // Generar nuevo token
                $newToken = JWTHelper::generateToken($user['id'], $user['email']);
                $newRefreshToken = JWTHelper::generateRefreshToken($user['id']);
                
                return $this->response(200, 'Token actualizado', [
                    'token' => $newToken,
                    'refreshToken' => $newRefreshToken
                ]);
                
            } catch (Exception $e) {
                return $this->response(401, 'Refresh token inválido o expirado');
            }
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en el servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Activar 2FA para el usuario actual
     */
    public function enableTwoFactor($request) {
        try {
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                return $this->response(401, 'Token no proporcionado');
            }
            
            try {
                $decoded = JWTHelper::validateToken($token);
                $userId = $decoded['userId'];
                
                // Obtener datos del body
                $data = json_decode(file_get_contents("php://input"), true);
                
                // Validar email si se proporciona uno diferente
                $twoFactorEmail = null;
                if (isset($data['two_factor_email'])) {
                    $twoFactorEmail = filter_var($data['two_factor_email'], FILTER_VALIDATE_EMAIL);
                    if (!$twoFactorEmail) {
                        return $this->response(400, 'Email inválido');
                    }
                }
                
                // Obtener usuario
                $user = $this->userModel->findById($userId);
                if (!$user) {
                    return $this->response(404, 'Usuario no encontrado');
                }
                
                // Si ya tiene 2FA activado
                if ($user['two_factor_enabled']) {
                    return $this->response(400, 'La verificación de dos pasos ya está activada');
                }
                
                // Generar código de verificación
                $verificationCode = sprintf('%06d', mt_rand(0, 999999));
                
                // Limpiar códigos anteriores del usuario
                $stmt = $this->db->prepare("DELETE FROM two_factor_setup WHERE user_id = ?");
                $stmt->execute([$userId]);
                
                // Guardar código en base de datos
                $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutos
                $stmt = $this->db->prepare("
                    INSERT INTO two_factor_setup (user_id, code, email, expires_at) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $userId,
                    $verificationCode,
                    $twoFactorEmail ?? $user['email'],
                    $expiresAt
                ]);
                
                // Enviar código por email
                $emailDestino = $twoFactorEmail ?? $user['email'];
                $emailResult = $this->emailService->sendTwoFactorCode(
                    $emailDestino, 
                    $user['name'], 
                    $verificationCode
                );
                
                if (!$emailResult['success']) {
                    return $this->response(500, 'Error al enviar el código de verificación');
                }
                
                return $this->response(200, 'Código de verificación enviado', [
                    'email' => substr($emailDestino, 0, 3) . '****' . substr($emailDestino, strpos($emailDestino, '@')),
                    'message' => 'Por favor, ingresa el código enviado a tu email para activar la verificación de dos pasos'
                ]);
                
            } catch (Exception $e) {
                return $this->response(401, 'Token inválido');
            }
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en el servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Confirmar activación de 2FA
     */
    public function confirmEnableTwoFactor($request) {
        try {
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                return $this->response(401, 'Token no proporcionado');
            }
            
            try {
                $decoded = JWTHelper::validateToken($token);
                $userId = $decoded['userId'];
                
                // Obtener código del body
                $data = json_decode(file_get_contents("php://input"), true);
                
                if (!isset($data['code'])) {
                    return $this->response(400, 'Código de verificación requerido');
                }
                
                // Buscar el código en la base de datos
                $stmt = $this->db->prepare("
                    SELECT * FROM two_factor_setup 
                    WHERE user_id = ? 
                    AND expires_at > NOW() 
                    AND used = FALSE
                    ORDER BY created_at DESC
                    LIMIT 1
                ");
                $stmt->execute([$userId]);
                $setupData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Debug info
                $debugInfo = [
                    'user_id' => $userId,
                    'setup_found' => !empty($setupData),
                    'code_provided' => $data['code']
                ];
                
                // Verificar si existe un proceso de activación
                if (!$setupData) {
                    return $this->response(400, 'No hay proceso de activación en curso o el código ha expirado', [
                        'debug' => $debugInfo
                    ]);
                }
                
                // Verificar código
                if ($data['code'] !== $setupData['code']) {
                    return $this->response(401, 'Código incorrecto');
                }
                
                // Activar 2FA en la base de datos
                $activated = $this->userModel->enableTwoFactor($userId, $setupData['email']);
                
                if (!$activated) {
                    return $this->response(500, 'Error al activar la verificación de dos pasos');
                }
                
                // Marcar el código como usado
                $stmt = $this->db->prepare("UPDATE two_factor_setup SET used = TRUE WHERE id = ?");
                $stmt->execute([$setupData['id']]);
                
                // Registrar actividad
                $this->activityLog->log(
                    $userId,
                    ActivityLog::TYPE_2FA_ENABLED,
                    'Verificación de dos pasos activada'
                );
                
                // Enviar email de confirmación
                $user = $this->userModel->findById($userId);
                $this->emailService->sendTwoFactorActivationEmail(
                    $setupData['email'],
                    $user['name']
                );
                
                return $this->response(200, 'Verificación de dos pasos activada exitosamente', [
                    'twoFactorEnabled' => true,
                    'twoFactorEmail' => $setupData['email']
                ]);
                
            } catch (Exception $e) {
                return $this->response(401, 'Token inválido');
            }
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en el servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Desactivar 2FA
     */
    public function disableTwoFactor($request) {
        try {
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                return $this->response(401, 'Token no proporcionado');
            }
            
            try {
                $decoded = JWTHelper::validateToken($token);
                $userId = $decoded['userId'];
                
                // Obtener contraseña del body para confirmar
                $data = json_decode(file_get_contents("php://input"), true);
                
                if (!isset($data['password'])) {
                    return $this->response(400, 'Contraseña requerida para desactivar 2FA');
                }
                
                // Verificar usuario y contraseña
                $user = $this->userModel->findById($userId);
                
                if (!$user) {
                    return $this->response(404, 'Usuario no encontrado');
                }
                
                if (!$this->userModel->verifyPassword($data['password'], $user['password'])) {
                    return $this->response(401, 'Contraseña incorrecta');
                }
                
                if (!$user['two_factor_enabled']) {
                    return $this->response(400, 'La verificación de dos pasos no está activada');
                }
                
                // Desactivar 2FA
                $disabled = $this->userModel->disableTwoFactor($userId);
                
                if (!$disabled) {
                    return $this->response(500, 'Error al desactivar la verificación de dos pasos');
                }
                
                // Registrar actividad
                $this->activityLog->log(
                    $userId,
                    ActivityLog::TYPE_2FA_DISABLED,
                    'Verificación de dos pasos desactivada'
                );
                
                return $this->response(200, 'Verificación de dos pasos desactivada exitosamente', [
                    'twoFactorEnabled' => false
                ]);
                
            } catch (Exception $e) {
                return $this->response(401, 'Token inválido');
            }
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en el servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Actualizar perfil del usuario
     */
    public function updateProfile($request) {
        try {
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                return $this->response(401, 'Token no proporcionado');
            }
            
            try {
                $decoded = JWTHelper::validateToken($token);
                $userId = $decoded['userId'];
                
                // Obtener datos del body
                $data = json_decode(file_get_contents("php://input"), true);
                
                // Debug: Ver qué datos llegan
                error_log("DEBUG updateProfile - Datos recibidos: " . json_encode($data));
                
                // Validar datos
                $updates = [];
                $errors = [];
                
                // Validar nombre
                if (isset($data['name'])) {
                    $name = trim($data['name']);
                    if (empty($name)) {
                        $errors[] = 'El nombre no puede estar vacío';
                    } elseif (strlen($name) < 3) {
                        $errors[] = 'El nombre debe tener al menos 3 caracteres';
                    } else {
                        $updates['name'] = $name;
                    }
                }
                
                // Validar email
                if (isset($data['email'])) {
                    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);
                    if (!$email) {
                        $errors[] = 'Email inválido';
                    } else {
                        // Verificar si el email ya existe en otro usuario
                        $existingUser = $this->userModel->findByEmail($email);
                        if ($existingUser && $existingUser['id'] != $userId) {
                            $errors[] = 'El email ya está en uso';
                        } else {
                            $updates['email'] = $email;
                        }
                    }
                }
                
                // Validar teléfono
                if (isset($data['phone'])) {
                    $phone = trim($data['phone']);
                    if (!empty($phone)) {
                        // Validar formato de teléfono (ajustar según país)
                        $phone = preg_replace('/[^0-9+]/', '', $phone);
                        if (strlen($phone) < 9) {
                            $errors[] = 'Teléfono inválido';
                        } else {
                            $updates['phone'] = $phone;
                        }
                    } else {
                        $updates['phone'] = null; // Permitir borrar el teléfono
                    }
                }
                
                // Validar número de abonado
                if (isset($data['account'])) {
                    $account = trim($data['account']);
                    if (!empty($account)) {
                        $updates['account'] = $account;
                    } else {
                        $updates['account'] = null; // Permitir borrar el número de abonado
                    }
                }
                
                // Si hay errores, devolverlos
                if (!empty($errors)) {
                    return $this->response(400, 'Errores de validación', [
                        'errors' => $errors
                    ]);
                }
                
                // Si no hay nada que actualizar
                if (empty($updates)) {
                    return $this->response(400, 'No hay datos para actualizar');
                }
                
                // Debug: Ver qué se va a actualizar
                error_log("DEBUG updateProfile - Campos a actualizar: " . json_encode($updates));
                
                // Actualizar usuario
                $updated = $this->userModel->updateProfile($userId, $updates);
                
                // Debug: Ver resultado de la actualización
                error_log("DEBUG updateProfile - Resultado actualización: " . ($updated ? 'true' : 'false'));
                
                if (!$updated) {
                    return $this->response(500, 'Error al actualizar el perfil');
                }
                
                // Registrar actividad
                $changedFields = [];
                if (isset($updates['name'])) $changedFields[] = 'nombre';
                if (isset($updates['email'])) $changedFields[] = 'email';
                if (isset($updates['phone'])) $changedFields[] = 'teléfono';
                if (isset($updates['account'])) $changedFields[] = 'número de abonado';
                
                $this->activityLog->log(
                    $userId,
                    ActivityLog::TYPE_PROFILE_UPDATED,
                    'Perfil actualizado: ' . implode(', ', $changedFields),
                    ['fields' => array_keys($updates)]
                );
                
                // Obtener usuario actualizado
                $user = $this->userModel->findById($userId);
                
                // Preparar datos del usuario
                $userData = [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'name' => $user['name'],
                    'phone' => $user['phone'],
                    'account' => $user['account'],
                    'twoFactorEnabled' => (bool)$user['two_factor_enabled'],
                    'createdAt' => $user['created_at'],
                    'passwordUpdatedAt' => $user['password_updated_at']
                ];
                
                return $this->response(200, 'Perfil actualizado correctamente', [
                    'user' => $userData
                ]);
                
            } catch (Exception $e) {
                return $this->response(401, 'Token inválido');
            }
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en el servidor: ' . $e->getMessage());
        }
    }
    
    /**
     * Cambiar contraseña del usuario
     */
    public function changePassword($request) {
        try {
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                return $this->response(401, 'Token no proporcionado');
            }
            
            try {
                $decoded = JWTHelper::validateToken($token);
                $userId = $decoded['userId'];
                
                // Obtener datos del body
                $data = json_decode(file_get_contents("php://input"), true);
                
                // Validar datos requeridos
                if (!isset($data['current_password']) || !isset($data['new_password'])) {
                    return $this->response(400, 'La contraseña actual y la nueva son requeridas');
                }
                
                $currentPassword = $data['current_password'];
                $newPassword = $data['new_password'];
                
                // Validar contraseña nueva
                if (strlen($newPassword) < 8) {
                    return $this->response(400, 'La nueva contraseña debe tener al menos 8 caracteres');
                }
                
                // Validar que contenga al menos una mayúscula, una minúscula y un número
                if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $newPassword)) {
                    return $this->response(400, 'La contraseña debe contener al menos una mayúscula, una minúscula y un número');
                }
                
                // Obtener usuario actual
                $user = $this->userModel->findById($userId);
                
                if (!$user) {
                    return $this->response(404, 'Usuario no encontrado');
                }
                
                // Verificar contraseña actual
                if (!$this->userModel->verifyPassword($currentPassword, $user['password'])) {
                    return $this->response(401, 'La contraseña actual es incorrecta');
                }
                
                // Verificar que la nueva contraseña sea diferente
                if ($this->userModel->verifyPassword($newPassword, $user['password'])) {
                    return $this->response(400, 'La nueva contraseña debe ser diferente a la actual');
                }
                
                // Cambiar la contraseña
                $changed = $this->userModel->changePassword($userId, $newPassword);
                
                if (!$changed) {
                    return $this->response(500, 'Error al cambiar la contraseña');
                }
                
                // Registrar actividad
                $this->activityLog->log(
                    $userId,
                    ActivityLog::TYPE_PASSWORD_CHANGED,
                    'Contraseña cambiada exitosamente'
                );
                
                return $this->response(200, 'Contraseña cambiada exitosamente');
                
            } catch (Exception $e) {
                return $this->response(401, 'Token inválido');
            }
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en el servidor: ' . $e->getMessage());
        }
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