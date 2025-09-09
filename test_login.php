<?php
// Página de prueba para verificar el login

// Headers para permitir CORS
header("Access-Control-Allow-Origin: *");
header("Content-Type: text/html; charset=UTF-8");

?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Login API</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 600px; margin: 0 auto; }
        input, button { margin: 5px 0; padding: 8px; width: 100%; }
        button { background: #011850; color: white; border: none; cursor: pointer; }
        button:hover { background: #334975; }
        #response { margin-top: 20px; padding: 10px; background: #f0f0f0; white-space: pre-wrap; }
        .error { color: red; }
        .success { color: green; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Test Login API - TaxiClass</h1>
        
        <div>
            <label>Email:</label>
            <input type="email" id="email" value="test@taxiclass.com">
        </div>
        
        <div>
            <label>Contraseña:</label>
            <input type="password" id="password" value="password123">
        </div>
        
        <button onclick="testLogin()">Probar Login</button>
        
        <div id="response"></div>
    </div>

    <script>
        async function testLogin() {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const responseDiv = document.getElementById('response');
            
            responseDiv.innerHTML = 'Enviando petición...';
            
            try {
                const response = await fetch('http://localhost/reservas-taxiclass/backend/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        email: email,
                        password: password
                    })
                });
                
                const data = await response.json();
                
                responseDiv.innerHTML = '<strong>Respuesta del servidor:</strong>\n' + 
                                      JSON.stringify(data, null, 2);
                
                if (data.requiresTwoFactor) {
                    responseDiv.innerHTML += '\n\n<span class="success">✓ 2FA está funcionando correctamente!</span>';
                    responseDiv.innerHTML += '\n\nCódigo 2FA de prueba: ' + data.debug_code;
                } else if (data.token) {
                    responseDiv.innerHTML += '\n\n<span class="error">✗ Login sin 2FA (no debería pasar)</span>';
                }
                
            } catch (error) {
                responseDiv.innerHTML = '<span class="error">Error: ' + error.message + '</span>';
            }
        }
    </script>
</body>
</html>
