-- Crear tabla reservation_logs para registro de reservas con API Auriga
-- Enfoque híbrido: almacenamos datos esenciales y dependemos de Auriga para el estado real

CREATE TABLE IF NOT EXISTS reservation_logs (
    -- Identificadores
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    booking_id_auriga INT NULL, -- ID devuelto por Auriga tras crear la reserva
    
    -- Datos temporales
    booking_date DATETIME NOT NULL, -- Fecha y hora de la reserva
    