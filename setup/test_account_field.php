<?php
// Script para verificar el campo account en la base de datos

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../api/config/Database.php';

use App\config\Database;

try {
    // Conectar a la base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    echo "=== VERIFICACIÓN CAMPO ACCOUNT ===\n\n";
    
    // 1. Verificar estructura de la tabla
    echo "1. Estructura de la tabla users:\n";
    $query = "DESCRIBE users";
    $stmt = $db->prepare($query);
    $stmt->execute();
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['Field'] == 'account') {
            echo "   Campo 'account' encontrado:\n";
            echo "   - Type: " . $row['Type'] . "\n";
            echo "   - Null: " . $row['Null'] . "\n";
            echo "   - Default: " . $row['Default'] . "\n\n";
        }
    }
    
    // 2. Verificar usuario de prueba
    echo "2. Usuario de prueba:\n";
    $query = "SELECT id, email, name, phone, account FROM users WHERE email = 'test@taxiclass.com'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "   Usuario encontrado:\n";
        echo "   - ID: " . $user['id'] . "\n";
        echo "   - Email: " . $user['email'] . "\n";
        echo "   - Name: " . $user['name'] . "\n";
        echo "   - Phone: " . $user['phone'] . "\n";
        echo "   - Account: " . ($user['account'] ?? 'NULL') . "\n\n";
    }
    
    // 3. Probar actualización directa
    echo "3. Prueba de actualización directa:\n";
    $testAccount = 'TEST-' . time();
    $query = "UPDATE users SET account = :account WHERE email = 'test@taxiclass.com'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':account', $testAccount);
    
    if ($stmt->execute()) {
        echo "   ✓ Actualización exitosa. Nuevo valor: $testAccount\n";
        
        // Verificar
        $query = "SELECT account FROM users WHERE email = 'test@taxiclass.com'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "   ✓ Verificación: account = " . $result['account'] . "\n";
    } else {
        echo "   ✗ Error en la actualización: " . json_encode($stmt->errorInfo()) . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
