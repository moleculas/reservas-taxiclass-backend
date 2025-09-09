<?php
// Test directo del backend

// Configurar para mostrar errores
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Incluir autoload
require_once __DIR__ . '/vendor/autoload.php';

// Incluir archivos necesarios
require_once __DIR__ . '/api/config/database.php';
require_once __DIR__ . '/api/helpers/JWTHelper.php';
require_once __DIR__ . '/api/models/User.php';
require_once __DIR__ . '/api/controllers/AuthController.php';

// Probar conexión a BD
echo "<h2>1. Probando conexión a base de datos:</h2>";
try {
    $database = new Database();
    $db = $database->getConnection();
    echo "<p style='color:green'>✓ Conexión exitosa</p>";
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
    exit;
}

// Buscar usuario de prueba
echo "<h2>2. Buscando usuario test@taxiclass.com:</h2>";
try {
    $userModel = new \App\models\User($db);
    $user = $userModel->findByEmail('test@taxiclass.com');
    
    if ($user) {
        echo "<p style='color:green'>✓ Usuario encontrado</p>";
        echo "<pre>";
        echo "ID: " . $user['id'] . "\n";
        echo "Email: " . $user['email'] . "\n";
        echo "Nombre: " . $user['name'] . "\n";
        echo "2FA Habilitado: " . ($user['two_factor_enabled'] ? 'SÍ' : 'NO') . "\n";
        echo "</pre>";
    } else {
        echo "<p style='color:red'>✗ Usuario no encontrado</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}

// Probar generación de token
echo "<h2>3. Probando generación de JWT:</h2>";
try {
    if ($user) {
        $token = \App\helpers\JWTHelper::generateToken($user['id'], $user['email']);
        echo "<p style='color:green'>✓ Token generado correctamente</p>";
        echo "<p>Token (primeros 50 caracteres): " . substr($token, 0, 50) . "...</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}

// Verificar tabla two_factor_attempts
echo "<h2>4. Verificando tabla two_factor_attempts:</h2>";
try {
    $query = "SHOW TABLES LIKE 'two_factor_attempts'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $table = $stmt->fetch();
    
    if ($table) {
        echo "<p style='color:green'>✓ Tabla two_factor_attempts existe</p>";
    } else {
        echo "<p style='color:red'>✗ Tabla two_factor_attempts NO existe</p>";
    }
} catch (Exception $e) {
    echo "<p style='color:red'>✗ Error: " . $e->getMessage() . "</p>";
}
