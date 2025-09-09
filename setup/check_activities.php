<?php
require_once '../vendor/autoload.php';

use App\config\Database;
use App\config\Environment;

// Cargar variables de entorno
Environment::load();

// Conectar a la base de datos
$database = new Database();
$db = $database->getConnection();

// Verificar las últimas actividades
$query = "SELECT a.*, u.email, u.name 
          FROM activity_logs a 
          JOIN users u ON a.user_id = u.id 
          ORDER BY a.created_at DESC 
          LIMIT 10";

$stmt = $db->prepare($query);
$stmt->execute();

echo "Últimas 10 actividades en la base de datos:\n";
echo "==========================================\n\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo "ID: " . $row['id'] . "\n";
    echo "Usuario: " . $row['name'] . " (" . $row['email'] . ")\n";
    echo "Tipo: " . $row['activity_type'] . "\n";
    echo "Descripción: " . $row['activity_description'] . "\n";
    echo "Fecha: " . $row['created_at'] . "\n";
    echo "IP: " . $row['ip_address'] . "\n";
    echo "User Agent: " . substr($row['user_agent'], 0, 50) . "...\n";
    echo "-------------------------------------------\n\n";
}

// Verificar si hay actividades para el usuario de prueba
$testUserQuery = "SELECT COUNT(*) as total FROM activity_logs WHERE user_id = 1";
$testStmt = $db->prepare($testUserQuery);
$testStmt->execute();
$result = $testStmt->fetch(PDO::FETCH_ASSOC);

echo "\nTotal de actividades para usuario ID 1: " . $result['total'] . "\n";
