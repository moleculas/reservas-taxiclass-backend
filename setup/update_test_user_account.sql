-- Script para actualizar el usuario de prueba con número de abonado

UPDATE users 
SET account = 'C-308'
WHERE email = 'test@taxiclass.com';

-- Verificar la actualización
SELECT id, email, name, phone, account, two_factor_enabled 
FROM users 
WHERE email = 'test@taxiclass.com';
