<?php
// Script para probar la actualización del campo account

// Cargar configuración
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/config/Database.php';
require_once __DIR__ . '/../api/models/User.php';

use App\config\Database;
use App\models\User;

try {
    // Conectar a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    // Crear instancia del modelo User
    $userModel = new User($db);
    
    echo "=== PRUEBA DE ACTUALIZACIÓN DE PERFIL ===\n\n";
    
    // 1. Obtener el ID del usuario de prueba
    $user = $userModel->findByEmail('test@taxiclass.com');
    if (!$user) {
        die("Usuario de prueba no encontrado\n");
    }
    
    echo "Usuario encontrado:\n";
    echo "- ID: " . $user['id'] . "\n";
    echo "- Email: " . $user['email'] . "\n";
    echo "- Name: " . $user['name'] . "\n";
    echo "- Phone: " . $user['phone'] . "\n";
    echo "- Account actual: " . ($user['account'] ?? 'NULL') . "\n\n";
    
    // 2. Preparar datos de actualización
    $updates = [
        'name' => $user['name'], // Sin cambio
        'email' => $user['email'], // Sin cambio
        'phone' => $user['phone'], // Sin cambio
        'account' => 'TEST-123'   // Nuevo valor
    ];
    
    echo "Datos a actualizar:\n";
    print_r($updates);
    echo "\n";
    
    // 3. Ejecutar actualización
    echo "Ejecutando actualización...\n";
    $result = $userModel->updateProfile($user['id'], $updates);
    
    if ($result) {
        echo "✓ Actualización exitosa\n\n";
        
        // 4. Verificar cambios
        $updatedUser = $userModel->findByEmail('test@taxiclass.com');
        echo "Usuario después de actualización:\n";
        echo "- Account: " . ($updatedUser['account'] ?? 'NULL') . "\n";
        
        if ($updatedUser['account'] === 'TEST-123') {
            echo "\n✓ ¡El campo account se actualizó correctamente!\n";
        } else {
            echo "\n✗ El campo account NO se actualizó\n";
        }
    } else {
        echo "✗ Error en la actualización\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
