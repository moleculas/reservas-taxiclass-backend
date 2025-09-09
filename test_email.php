<?php
// Script de prueba para verificar el envío de emails
require_once __DIR__ . '/vendor/autoload.php';

// Cargar variables de entorno
use App\config\Environment;
Environment::load();

echo "=== TEST DE ENVÍO DE EMAIL ===\n\n";

// Mostrar configuración (sin contraseña)
echo "Configuración actual:\n";
echo "MAIL_HOST: " . $_ENV['MAIL_HOST'] . "\n";
echo "MAIL_PORT: " . $_ENV['MAIL_PORT'] . "\n";
echo "MAIL_USERNAME: " . $_ENV['MAIL_USERNAME'] . "\n";
echo "MAIL_FROM_ADDRESS: " . $_ENV['MAIL_FROM_ADDRESS'] . "\n";
echo "MAIL_FROM_NAME: " . $_ENV['MAIL_FROM_NAME'] . "\n\n";

// Intentar enviar un email de prueba
try {
    $emailService = new \App\Services\EmailService();
    
    // IMPORTANTE: Cambia este email por uno real donde puedas recibir
    $testEmail = 'isaiasherreroflorensa@gmail.com'; // <-- CAMBIAR POR TU EMAIL
    $testName = 'Usuario Test';
    $testCode = '123456';
    
    echo "Intentando enviar email a: $testEmail\n";
    echo "Código de prueba: $testCode\n\n";
    
    $result = $emailService->sendTwoFactorCode($testEmail, $testName, $testCode);
    
    if ($result['success']) {
        echo "✅ EMAIL ENVIADO EXITOSAMENTE!\n";
        echo "Revisa la bandeja de entrada (y spam) de $testEmail\n";
    } else {
        echo "❌ ERROR AL ENVIAR EMAIL\n";
        echo "Error: " . ($result['error'] ?? 'Error desconocido') . "\n";
        echo "Mensaje: " . ($result['message'] ?? '') . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ EXCEPCIÓN CAPTURADA:\n";
    echo $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}

echo "\n\n=== INFORMACIÓN ADICIONAL ===\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "OpenSSL: " . (extension_loaded('openssl') ? 'Habilitado' : 'NO HABILITADO') . "\n";
echo "Sockets: " . (extension_loaded('sockets') ? 'Habilitado' : 'NO HABILITADO') . "\n";

// Verificar conectividad con Gmail
echo "\n=== TEST DE CONECTIVIDAD ===\n";
$connection = @fsockopen('smtp.gmail.com', 587, $errno, $errstr, 5);
if ($connection) {
    echo "✅ Conexión a smtp.gmail.com:587 exitosa\n";
    fclose($connection);
} else {
    echo "❌ No se puede conectar a smtp.gmail.com:587\n";
    echo "Error: $errstr ($errno)\n";
}

// Verificar si hay restricciones de firewall
echo "\n=== POSIBLES PROBLEMAS COMUNES ===\n";
echo "1. Firewall/Antivirus bloqueando puerto 587\n";
echo "2. Necesitas habilitar 'Aplicaciones menos seguras' en Gmail (deprecated)\n";
echo "3. Usar App Password en lugar de contraseña normal de Gmail\n";
echo "4. Verificación en dos pasos debe estar activada en Gmail\n";
echo "5. php.ini debe tener extension=openssl habilitada\n";
