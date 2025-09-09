<?php
// Configuración de cookies de sesión para CORS
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false, // true en producción con HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);

// Iniciar sesión antes de cualquier output
session_start();

// Configurar zona horaria
date_default_timezone_set('Europe/Madrid');

// Mostrar errores (solo en desarrollo)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Headers CORS
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = ['http://localhost:5173', 'http://localhost:3000'];

if (in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
} else if ($origin) {
    // Si hay un origin pero no está permitido, no enviar header
    header("Access-Control-Allow-Origin: http://localhost:5173");
} else {
    // Si no hay origin (peticiones directas), usar el primero permitido
    header("Access-Control-Allow-Origin: http://localhost:5173");
}

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Autoload
require_once __DIR__ . '/vendor/autoload.php';

// Cargar variables de entorno
use App\config\Environment;
Environment::load();

// Los archivos ahora se cargan automáticamente con el autoload

// Inicializar base de datos
use App\config\Database;
$database = new Database();
$db = $database->getConnection();

// Router
$request_uri = $_SERVER['REQUEST_URI'];
$request_method = $_SERVER['REQUEST_METHOD'];

// Remover el path base si existe
$base_path = '/reservas-taxiclass/backend';
$request_uri = str_replace($base_path, '', $request_uri);

// Separar la ruta de los parámetros de query
$uri_parts_with_query = explode('?', $request_uri);
$uri_path = $uri_parts_with_query[0];

// Parsear la URI (sin query params)
$uri_parts = explode('/', trim($uri_path, '/'));
$endpoint = $uri_parts[0] ?? '';
$action = $uri_parts[1] ?? '';

// Router mejorado
if ($endpoint === 'auth') {
    $authController = new \App\controllers\AuthController($db);
    
    switch ($action) {
        case 'login':
            if ($request_method === 'POST') {
                $authController->login($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'verify-2fa':
            if ($request_method === 'POST') {
                $authController->verifyTwoFactor($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'logout':
            if ($request_method === 'POST') {
                $authController->logout($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'me':
            if ($request_method === 'GET') {
                $authController->me($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'verify':
            if ($request_method === 'GET') {
                $authController->verify($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'refresh':
            if ($request_method === 'POST') {
                $authController->refresh($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'enable-2fa':
            if ($request_method === 'POST') {
                $authController->enableTwoFactor($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'confirm-enable-2fa':
            if ($request_method === 'POST') {
                $authController->confirmEnableTwoFactor($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'disable-2fa':
            if ($request_method === 'POST') {
                $authController->disableTwoFactor($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'update-profile':
            if ($request_method === 'PUT') {
                $authController->updateProfile($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'change-password':
            if ($request_method === 'POST') {
                $authController->changePassword($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Acción no encontrada']);
    }
    exit();
}

// Router para actividades
if ($endpoint === 'activities') {
    $activityController = new \App\controllers\ActivityController($db);
    
    switch ($action) {
        case '':
        case 'list':
            if ($request_method === 'GET') {
                $activityController->getUserActivities($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Acción no encontrada']);
    }
    exit();
}

// Router para localizaciones
if ($endpoint === 'locations') {
    $locationController = new \App\controllers\LocationController($db);
    
    switch ($action) {
        case '':
        case 'list':
            if ($request_method === 'GET') {
                $locationController->getLocations($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'search':
            if ($request_method === 'GET') {
                $locationController->searchLocations($_REQUEST);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Acción no encontrada']);
    }
    exit();
}

// Router para reservas
if ($endpoint === 'reservations') {
    $reservationController = new \App\controllers\ReservationController();
    
    switch ($action) {
        case 'create':
            if ($request_method === 'POST') {
                $reservationController->create();
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'list':
            if ($request_method === 'GET') {
                $reservationController->getUserReservations();
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
            }
            break;
            
        case 'detail':
            if ($request_method === 'GET' && isset($uri_parts[2])) {
                $reservationController->getReservationDetail($uri_parts[2]);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido o ID faltante']);
            }
            break;
            
        case 'cancel':
            if ($request_method === 'POST' && isset($uri_parts[2])) {
                $reservationController->cancelReservation($uri_parts[2]);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido o ID faltante']);
            }
            break;
            
        case 'receipt':
            if ($request_method === 'GET' && isset($uri_parts[2])) {
                $reservationController->downloadReceipt($uri_parts[2]);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Método no permitido o ID faltante']);
            }
            break;
            
        default:
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Acción no encontrada']);
    }
    exit();
}

// Respuesta por defecto para otros endpoints
$response = [
    'status' => 'success',
    'message' => 'API de Reservas TaxiClass funcionando',
    'version' => '1.0.0',
    'timestamp' => date('Y-m-d H:i:s'),
    'endpoints' => [
        'POST /auth/login' => 'Iniciar sesión',
        'POST /auth/verify-2fa' => 'Verificar código 2FA',
        'POST /auth/logout' => 'Cerrar sesión',
        'GET /auth/me' => 'Obtener usuario actual',
        'GET /auth/verify' => 'Verificar token',
        'POST /auth/refresh' => 'Refrescar token',
        'POST /auth/enable-2fa' => 'Activar verificación de dos pasos',
        'POST /auth/confirm-enable-2fa' => 'Confirmar activación de 2FA',
        'POST /auth/disable-2fa' => 'Desactivar verificación de dos pasos',
        'PUT /auth/update-profile' => 'Actualizar perfil de usuario',
        'POST /auth/change-password' => 'Cambiar contraseña',
        'GET /activities' => 'Obtener actividades recientes del usuario',
        'POST /reservations/create' => 'Crear nueva reserva',
        'GET /reservations/list' => 'Listar reservas del usuario',
        'GET /reservations/detail/{id}' => 'Obtener detalle de una reserva'
    ]
];

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
