<?php
// Incluir autoload
require_once __DIR__ . '/vendor/autoload.php';
?>
<!DOCTYPE html>
<html>
<head>
    <title>Debug Login API</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; margin: 0 auto; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Debug Login - TaxiClass</h1>
        
        <div class="section">
            <h2>1. Verificar Usuario en BD</h2>
            <?php
            require_once __DIR__ . '/api/config/database.php';
            require_once __DIR__ . '/api/models/User.php';
            
            try {
                $database = new Database();
                $db = $database->getConnection();
                $userModel = new \App\models\User($db);
                $user = $userModel->findByEmail('test@taxiclass.com');
                
                if ($user) {
                    echo "<p class='success'>✓ Usuario encontrado</p>";
                    echo "<pre>";
                    echo "ID: " . $user['id'] . "\n";
                    echo "Email: " . $user['email'] . "\n";
                    echo "2FA Habilitado: " . $user['two_factor_enabled'] . " (tipo: " . gettype($user['two_factor_enabled']) . ")\n";
                    echo "Valor exacto: '" . var_export($user['two_factor_enabled'], true) . "'\n";
                    echo "</pre>";
                }
            } catch (Exception $e) {
                echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
            }
            ?>
        </div>
        
        <div class="section">
            <h2>2. Probar Login Directo (POST)</h2>
            <button onclick="testLoginDirect()">Probar Login</button>
            <div id="loginResult"></div>
        </div>
        
        <div class="section">
            <h2>3. Probar con CURL desde PHP</h2>
            <?php
            if (isset($_GET['test_curl'])) {
                $url = 'http://localhost/reservas-taxiclass/backend/auth/login';
                $data = json_encode([
                    'email' => 'test@taxiclass.com',
                    'password' => 'password123'
                ]);
                
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($data)
                ]);
                
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                echo "<p class='info'>HTTP Code: " . $httpCode . "</p>";
                echo "<p>Respuesta:</p>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
                
                $decoded = json_decode($response, true);
                if ($decoded && isset($decoded['requiresTwoFactor'])) {
                    echo "<p class='success'>✓ 2FA está funcionando!</p>";
                } else {
                    echo "<p class='error'>✗ 2FA no está funcionando</p>";
                }
            } else {
                echo '<a href="?test_curl=1" class="button">Ejecutar prueba CURL</a>';
            }
            ?>
        </div>
    </div>
    
    <script>
        async function testLoginDirect() {
            const resultDiv = document.getElementById('loginResult');
            resultDiv.innerHTML = '<p>Enviando...</p>';
            
            try {
                const response = await fetch('http://localhost/reservas-taxiclass/backend/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: 'test@taxiclass.com',
                        password: 'password123'
                    })
                });
                
                const text = await response.text();
                console.log('Respuesta cruda:', text);
                
                try {
                    const data = JSON.parse(text);
                    resultDiv.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
                    
                    if (data.requiresTwoFactor) {
                        resultDiv.innerHTML += '<p class="success">✓ 2FA detectado!</p>';
                    } else {
                        resultDiv.innerHTML += '<p class="error">✗ No se detectó 2FA</p>';
                    }
                } catch (e) {
                    resultDiv.innerHTML = '<p class="error">Error parseando JSON:</p><pre>' + text + '</pre>';
                }
            } catch (error) {
                resultDiv.innerHTML = '<p class="error">Error: ' + error.message + '</p>';
            }
        }
    </script>
</body>
</html>
