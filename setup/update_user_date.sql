-- Actualizar el usuario de prueba con una fecha de creación
-- Si queremos que muestre "Miembro desde enero 2025" (o el mes actual)
UPDATE users 
SET created_at = '2025-01-15 10:00:00' 
WHERE email = 'test@taxiclass.com';

-- Si prefieres que sea de un año anterior para probar el otro formato:
-- UPDATE users 
-- SET created_at = '2024-06-15 10:00:00' 
-- WHERE email = 'test@taxiclass.com';
