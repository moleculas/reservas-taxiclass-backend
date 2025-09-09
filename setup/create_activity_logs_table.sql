-- Crear o actualizar tabla activity_logs para registro de actividades
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type VARCHAR(50) NOT NULL,
    activity_description VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_activity (user_id, created_at),
    INDEX idx_activity_type (activity_type)
);

-- Tipos de actividad sugeridos:
-- 'login_success' - Inicio de sesión exitoso
-- 'login_failed' - Intento de inicio de sesión fallido
-- 'logout' - Cierre de sesión
-- 'password_changed' - Cambio de contraseña
-- 'profile_updated' - Actualización de perfil
-- '2fa_enabled' - Activación de 2FA
-- '2fa_disabled' - Desactivación de 2FA
-- '2fa_failed' - Intento fallido de 2FA
