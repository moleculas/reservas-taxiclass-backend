<?php
// Script para generar hash de contraseña y SQL para usuario de prueba

$password = 'password123';
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

echo "=== HASH DE CONTRASEÑA GENERADO ===\n\n";
echo "Contraseña original: " . $password . "\n";
echo "Hash generado: " . $hashedPassword . "\n\n";

echo "=== SQL PARA INSERTAR USUARIO DE PRUEBA ===\n\n";

$sql = "INSERT INTO users (email, password, name, phone, two_factor_enabled, email_verified_at) 
VALUES (
    'test@taxiclass.com',
    '" . $hashedPassword . "',
    'Usuario Test',
    '+34 600 000 000',
    1,
    NOW()
);";

echo $sql . "\n\n";

echo "=== INSTRUCCIONES ===\n\n";
echo "1. Copia el SQL de arriba\n";
echo "2. Abre phpMyAdmin\n";
echo "3. Selecciona la base de datos 'taxiclass_db'\n";
echo "4. Ve a la pestaña SQL\n";
echo "5. Pega y ejecuta el SQL\n\n";

echo "=== CREDENCIALES DE PRUEBA ===\n\n";
echo "Email: test@taxiclass.com\n";
echo "Contraseña: password123\n";
echo "2FA habilitado: Sí\n";
echo "Código 2FA de prueba: Se mostrará en la respuesta del login (solo para desarrollo)\n";
