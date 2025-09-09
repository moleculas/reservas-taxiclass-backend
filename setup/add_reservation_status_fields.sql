-- Añadir campos para manejo de cancelaciones en reservation_logs
ALTER TABLE `reservation_logs` 
ADD COLUMN `status` ENUM('active', 'cancelled', 'completed') DEFAULT 'active' AFTER `service_id`,
ADD COLUMN `cancelled_at` DATETIME DEFAULT NULL AFTER `status`,
ADD COLUMN `cancellation_reason` VARCHAR(255) DEFAULT NULL AFTER `cancelled_at`,
ADD COLUMN `auriga_cancel_response` LONGTEXT DEFAULT NULL AFTER `cancellation_reason`;

-- Añadir índice para búsquedas por estado
ALTER TABLE `reservation_logs` ADD INDEX `idx_status` (`status`);

-- Actualizar reservas pasadas como completadas
UPDATE `reservation_logs` 
SET `status` = 'completed' 
WHERE `booking_date` < NOW() AND `status` = 'active';
