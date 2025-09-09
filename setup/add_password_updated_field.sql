-- Agregar campo para registrar última actualización de contraseña
ALTER TABLE users ADD COLUMN password_updated_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at;

-- Actualizar registros existentes con la fecha de creación como valor inicial
UPDATE users SET password_updated_at = created_at WHERE password_updated_at IS NULL;
