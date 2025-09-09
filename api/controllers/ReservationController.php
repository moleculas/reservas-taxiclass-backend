<?php
namespace App\controllers;

use App\config\Database;
use App\models\User;
use App\helpers\JWTHelper;
use App\Services\EmailService;
use PDO;
use Exception;
use DateTime;

class ReservationController {
    private $db;
    private $user;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
        $this->user = new User($this->db);
    }

    /**
     * Crear una nueva reserva
     * Recibe todos los datos ya procesados desde el frontend
     */
    public function create() {
        try {
            // Verificar autenticación
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                http_response_code(401);
                echo json_encode(['message' => 'Token no proporcionado']);
                return;
            }
            
            $decoded = JWTHelper::validateToken($token);
            $userId = $decoded['userId'];

            // Obtener datos del request
            $data = json_decode(file_get_contents("php://input"));
            
            // Log para debug - se enviará al frontend
            $debugLogs = [];
            $debugLogs[] = "Datos recibidos en create(): " . json_encode($data);

            // Validar datos requeridos básicos - CORRECCIÓN: No esperar 'passengers'
            if (empty($data->bookingDate) || empty($data->pickupAddress) || empty($data->numberOfPassengers)) {
                http_response_code(400);
                echo json_encode([
                    'message' => 'Datos incompletos',
                    'debug' => [
                        'received_data' => $data,
                        'has_bookingDate' => !empty($data->bookingDate),
                        'has_pickupAddress' => !empty($data->pickupAddress),
                        'has_numberOfPassengers' => !empty($data->numberOfPassengers)
                    ]
                ]);
                return;
            }

            // Obtener datos del usuario
            $stmt = $this->db->prepare("SELECT name, phone, account, email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            // Construir array de preferences basado en los requerimientos
            $preferences = [];
            
            // Verificar requerimientos del vehículo - CORRECCIÓN: datos vienen directamente, no en passengers
            if (!empty($data->childSeat) && $data->childSeat === true) {
                array_push($preferences, "1662");
            }
            if (!empty($data->vehicle56Seats) && $data->vehicle56Seats === true) {
                array_push($preferences, "1663");
            }
            if (!empty($data->vehicle7Seats) && $data->vehicle7Seats === true) {
                array_push($preferences, "1665");
            }
            
            // Verificar si la recogida es en aeropuerto
            if (!empty($data->pickupAddress->type) && $data->pickupAddress->type === 'airport') {
                array_push($preferences, "1666");
            }

            // Procesar direcciones desde Google Places
            $pickupComponents = $this->extractAddressComponents($data->pickupAddress);
            $debugLogs[] = "Pickup components: " . json_encode($pickupComponents);
            
            $destinationComponents = null;
            if (!empty($data->destinationAddress)) {
                $destinationComponents = $this->extractAddressComponents($data->destinationAddress);
                $debugLogs[] = "Destination components: " . json_encode($destinationComponents);
            }
            
            // Asegurar que la fecha está en el formato correcto
            $bookingDateOriginal = $data->bookingDate;
            $debugLogs[] = "Fecha original recibida: " . $bookingDateOriginal;
            
            // Crear un DateTime para verificar
            try {
                $dateTime = new DateTime($bookingDateOriginal);
                $debugLogs[] = "Fecha parseada: " . $dateTime->format('Y-m-d H:i:s P');
                $debugLogs[] = "Timestamp: " . $dateTime->getTimestamp();
                
                // Verificar si es futura
                $now = new DateTime();
                $debugLogs[] = "Fecha actual: " . $now->format('Y-m-d H:i:s P');
                
                if ($dateTime <= $now) {
                    $debugLogs[] = "ERROR: La fecha está en el pasado!";
                }
            } catch (Exception $e) {
                $debugLogs[] = "Error parseando fecha: " . $e->getMessage();
            }
            
            // Si la fecha viene sin timezone offset o con formato incorrecto, ajustar
            if (!preg_match('/[+-]\d{4}$/', $bookingDateOriginal)) {
                // Si tiene el formato con : en el timezone, quitarlo
                if (preg_match('/[+-]\d{2}:\d{2}$/', $bookingDateOriginal)) {
                    $bookingDateFormatted = str_replace(['+02:00', '-02:00'], ['+0200', '-0200'], $bookingDateOriginal);
                    $debugLogs[] = "Fecha ajustada (quitando : del timezone): " . $bookingDateFormatted;
                } else {
                    // Convertir a timestamp y luego a formato con timezone de Madrid
                    $timestamp = strtotime($bookingDateOriginal);
                    $bookingDateFormatted = date('Y-m-d\TH:i:s', $timestamp) . '+0200'; // Horario de verano
                    $debugLogs[] = "Fecha ajustada con timezone: " . $bookingDateFormatted;
                }
            } else {
                $bookingDateFormatted = $bookingDateOriginal;
            }

            // Preparar datos para Auriga
            $bookingData = [
                'phoneNumber' => $userData['phone'], // Mantener el teléfono con prefijo
                'clientName' => $this->limitarNombreA30Bytes($userData['name']),
                'pickupAddress' => [
                    'latitude' => $this->eliminarUltimoDigitoCero($data->pickupAddress->latitude),
                    'longitude' => $this->eliminarUltimoDigitoCero($data->pickupAddress->longitude),
                    'bldgNumber' => $pickupComponents['bldgNumber'],
                    'street' => $pickupComponents['street'],
                    'locality' => $pickupComponents['locality'],
                    'town' => $pickupComponents['town'],
                    'country' => $pickupComponents['country']
                ],
                'bookingDate' => $bookingDateFormatted, // Usar la fecha formateada con timezone
                'destinationAddress' => null,
                'special' => isset($data->specialInstructions) && !empty($data->specialInstructions) ? $data->specialInstructions : null,
                'preferences' => !empty($preferences) ? $preferences : null,
                'providerId' => null,
                'urlHook' => null,
                'flight' => null,
                'account' => $userData['account'],
                'accountPassword' => null,
                'accountReference' => null,
                'lockedPrice' => null,
                'customerEmail' => null,
                'customerPaymentMethodId' => null,
                'bookingId' => null,
                'providerName' => null,
                'providerTelephone' => null,
                'serviceId' => null
            ];

            // Procesar dirección destino si existe
            if (!empty($data->destinationAddress) && $destinationComponents) {
                $bookingData['destinationAddress'] = [
                    'latitude' => $this->eliminarUltimoDigitoCero($data->destinationAddress->latitude),
                    'longitude' => $this->eliminarUltimoDigitoCero($data->destinationAddress->longitude),
                    'bldgNumber' => $destinationComponents['bldgNumber'],
                    'street' => $destinationComponents['street'],
                    'locality' => $destinationComponents['locality'],
                    'town' => $destinationComponents['town'],
                    'country' => $destinationComponents['country']
                ];
            }

            // Si es aeropuerto y hay datos de vuelo
            if ($data->pickupAddress->type === 'airport' && 
                !empty($data->pickupAddress->flightNumber) && 
                !empty($data->pickupAddress->flightOrigin)) {
                
                $bookingData['flight'] = [
                    'flightNo' => substr($data->pickupAddress->flightNumber, 0, 10),
                    'arrivalTime' => date('Hi'), // Por ahora hora actual, ajustar según necesidad
                    'origin' => substr($data->pickupAddress->flightOrigin, 0, 30)
                ];
            }

            // Generar signature para Auriga
            $signature = $this->generarSignature($bookingData, $debugLogs);
            $header = $_ENV['CLIENT_ID_AURIGA'] . ':' . $signature;
            $debugLogs[] = "Authorization header: " . $header;
            
            // Debug info para devolver al frontend
            $debugInfo = [
                'logs' => $debugLogs,
                'booking_data' => $bookingData,
                'signature_generation' => [
                    'client_id' => $_ENV['CLIENT_ID_AURIGA'],
                    'generated_signature' => $signature,
                    'full_header' => $header,
                    'api_url' => $_ENV['API_URL_AURIGA']
                ]
            ];

            // Llamar a API Auriga
            $aurigaResponse = $this->enviarReservaAuriga($bookingData, $header);
            
            // Añadir info de debug a la respuesta
            $debugInfo = array_merge($debugInfo, $aurigaResponse['debug_info'] ?? []);

            if ($aurigaResponse['success']) {
                // Guardar en base de datos
                $reservationId = $this->guardarReservaEnBD($userId, $bookingData, $aurigaResponse['data'], $data);
                
                // Envío de emails de confirmación
                try {
                    $emailService = new EmailService();
                    
                    // Preparar datos para el email
                    $bookingDateTime = new DateTime($bookingData['bookingDate']);
                    $emailData = [
                        'bookingId' => $aurigaResponse['data']['bookingId'],
                        'serviceId' => $aurigaResponse['data']['serviceId'] ?? null,
                        'providerName' => $aurigaResponse['data']['providerName'] ?? null,
                        'date' => $bookingDateTime->format('d/m/Y'),
                        'time' => $bookingDateTime->format('H:i'),
                        'pickupAddress' => $this->formatAddressForEmail($data->pickupAddress),
                        'destinationAddress' => $this->formatAddressForEmail($data->destinationAddress ?? null),
                        'passengers' => $data->numberOfPassengers,
                        'vehicleType' => $this->determineVehicleType($data),
                        'extras' => $this->determineExtras($data),
                        'specialInstructions' => $data->specialInstructions ?? null
                    ];
                    
                    // Enviar email al usuario
                    $emailService->sendReservationConfirmation($userData['email'], $userData['name'], $emailData);
                    
                    // Preparar datos adicionales para el admin
                    $adminEmailData = array_merge($emailData, [
                        'userName' => $userData['name'],
                        'userEmail' => $userData['email'],
                        'userPhone' => $userData['phone'],
                        'account' => $userData['account']
                    ]);
                    
                    // Enviar email a administración
                    $adminEmail = $_ENV['ADMIN_RESERVATION_EMAIL'];
                    $emailService->sendReservationNotificationToAdmin($adminEmail, $adminEmailData);
                    
                } catch (Exception $emailException) {
                    // Si falla el envío de email, loguear pero no interrumpir el proceso
                    error_log("Error enviando emails de confirmación: " . $emailException->getMessage());
                }
                
                // Registrar actividad
                $this->logActivity($userId, 'reservation_created', [
                    'booking_id' => $aurigaResponse['data']['bookingId'],
                    'service_id' => $aurigaResponse['data']['serviceId'] ?? null,
                    'pickup' => $data->pickupAddress->address ?? '',
                    'destination' => $data->destinationAddress->address ?? 'No especificado'
                ]);
                
                http_response_code(201);
                echo json_encode([
                    'success' => true,
                    'message' => 'Reserva creada exitosamente',
                    'reservationId' => $reservationId,
                    'bookingIdAuriga' => $aurigaResponse['data']['bookingId'],
                    'serviceId' => $aurigaResponse['data']['serviceId'],
                    'providerName' => $aurigaResponse['data']['providerName'] ?? null,
                    'debug' => $debugInfo
                ]);
            } else {
                // Añadir información del error a los logs de debug
                $debugInfo['error_details'] = [
                    'http_code' => $aurigaResponse['http_code'] ?? 'N/A',
                    'error_message' => $aurigaResponse['error'] ?? 'N/A',
                    'raw_response' => $aurigaResponse['raw_response'] ?? 'N/A',
                    'response_data' => $aurigaResponse['response'] ?? []
                ];
                
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al crear reserva en Auriga',
                    'error' => $aurigaResponse['error'],
                    'auriga_response' => $aurigaResponse['response'] ?? null,
                    'raw_response' => $aurigaResponse['raw_response'] ?? null,
                    'http_code' => $aurigaResponse['http_code'] ?? null,
                    'debug' => $debugInfo
                ]);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error del servidor',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generar signature para Auriga
     * IMPORTANTE: El orden de concatenación debe ser EXACTO según documentación
     */
    private function generarSignature($bookingData, &$debugLogs = []) {
        $clientKey = $_ENV['CLIENT_KEY_AURIGA'];
        $clientId = $_ENV['CLIENT_ID_AURIGA'];
        
        // Construir string para firma - ORDEN EXACTO
        $signatureString = $clientKey . $clientId;
        
        // 1. bookingDate
        $signatureString .= $bookingData['bookingDate'];
        
        // 2. clientName
        $signatureString .= $bookingData['clientName'];
        
        // 3. phoneNumber
        $signatureString .= $bookingData['phoneNumber'];
        
        // 4. pickupAddress (orden: lat, lng, bldgNumber, street, locality, town, country)
        $signatureString .= $bookingData['pickupAddress']['latitude'];
        $signatureString .= $bookingData['pickupAddress']['longitude'];
        $signatureString .= $bookingData['pickupAddress']['bldgNumber'];
        $signatureString .= $bookingData['pickupAddress']['street'];
        $signatureString .= $bookingData['pickupAddress']['locality'];
        $signatureString .= $bookingData['pickupAddress']['town'];
        $signatureString .= $bookingData['pickupAddress']['country'];
        
        // 5. destinationAddress (si existe)
        if (isset($bookingData['destinationAddress']) && $bookingData['destinationAddress'] !== null) {
            $signatureString .= $bookingData['destinationAddress']['latitude'];
            $signatureString .= $bookingData['destinationAddress']['longitude'];
            $signatureString .= $bookingData['destinationAddress']['bldgNumber'];
            $signatureString .= $bookingData['destinationAddress']['street'];
            $signatureString .= $bookingData['destinationAddress']['locality'];
            $signatureString .= $bookingData['destinationAddress']['town'];
            $signatureString .= $bookingData['destinationAddress']['country'];
        }
        
        // 6. special (si existe)
        if ($bookingData['special'] !== null) {
            $signatureString .= $bookingData['special'];
        }
        
        // 7. preferences (si existe y es array)
        if ($bookingData['preferences'] !== null && is_array($bookingData['preferences'])) {
            foreach ($bookingData['preferences'] as $pref) {
                $signatureString .= $pref;
            }
        }
        
        // 8. providerId (si existe y no es null)
        if (isset($bookingData['providerId']) && $bookingData['providerId'] !== null) {
            $signatureString .= $bookingData['providerId'];
        }
        
        // 9. No incluir bookingId en la firma para crear
        
        // 10. urlHook (si existe)
        if (isset($bookingData['urlHook']) && $bookingData['urlHook'] !== null) {
            $signatureString .= $bookingData['urlHook'];
        }
        
        // 11. flight (si existe)
        if ($bookingData['flight'] !== null) {
            $signatureString .= $bookingData['flight']['flightNo'];
            $signatureString .= $bookingData['flight']['arrivalTime'];
            $signatureString .= $bookingData['flight']['origin'];
        }
        
        // 12. account (si existe)
        if ($bookingData['account'] !== null) {
            $signatureString .= $bookingData['account'];
        }
        
        // 13. accountPassword (si existe)
        if (isset($bookingData['accountPassword']) && $bookingData['accountPassword'] !== null) {
            $signatureString .= $bookingData['accountPassword'];
        }
        
        // 14. accountReference (si existe)
        if ($bookingData['accountReference'] !== null) {
            $signatureString .= $bookingData['accountReference'];
        }
        
        // 15. lockedPrice (si existe)
        if (isset($bookingData['lockedPrice']) && $bookingData['lockedPrice'] !== null) {
            $signatureString .= $bookingData['lockedPrice'];
        }
        
        // DEBUG: Log el string completo para verificar
        $debugLogs[] = "=== SIGNATURE STRING DEBUG ===";
        $debugLogs[] = "String length: " . strlen($signatureString);
        $debugLogs[] = "String to sign (raw): " . $signatureString;
        $debugLogs[] = "String to sign (json): " . json_encode($signatureString);
        $debugLogs[] = "SHA1 result: " . sha1($signatureString);
        
        // Log específico de campos problemáticos
        $debugLogs[] = "ClientName bytes: " . strlen($bookingData['clientName']) . " - Value: " . $bookingData['clientName'];
        $debugLogs[] = "Town bytes: " . strlen($bookingData['pickupAddress']['town']) . " - Value: " . $bookingData['pickupAddress']['town'];
        $debugLogs[] = "Country bytes: " . strlen($bookingData['pickupAddress']['country']) . " - Value: " . $bookingData['pickupAddress']['country'];
        
        return sha1($signatureString);
    }

    /**
     * Enviar reserva a API Auriga
     */
    private function enviarReservaAuriga($bookingData, $header) {
        $url = $_ENV['API_URL_AURIGA'] . 'bookings';
        
        // NO limpiar campos null - Auriga necesita TODOS los campos
        // Reorganizar el array en el orden exacto que espera Auriga
        $bookingDataClean = [
            'phoneNumber' => $bookingData['phoneNumber'],
            'clientName' => $bookingData['clientName'],
            'bookingDate' => $bookingData['bookingDate'],
            'special' => $bookingData['special'],
            'preferences' => $bookingData['preferences'],
            'providerId' => $bookingData['providerId'],
            'urlHook' => $bookingData['urlHook'],
            'flight' => $bookingData['flight'],
            'account' => $bookingData['account'],
            'accountPassword' => $bookingData['accountPassword'],
            'accountReference' => $bookingData['accountReference'],
            'lockedPrice' => $bookingData['lockedPrice'],
            'customerEmail' => $bookingData['customerEmail'],
            'customerPaymentMethodId' => $bookingData['customerPaymentMethodId'],
            'bookingId' => $bookingData['bookingId'],
            'providerName' => $bookingData['providerName'],
            'providerTelephone' => $bookingData['providerTelephone'],
            'serviceId' => $bookingData['serviceId'],
            'pickupAddress' => $bookingData['pickupAddress'],
            'destinationAddress' => $bookingData['destinationAddress']
        ];
        
        // Preparar el contenido JSON exactamente igual
        $jsonContent = json_encode($bookingDataClean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        // Headers exactos
        $headers = [
            "Content-Type: application/json",
            "X-Authorization: " . $header,
            "Accept: */*",
            "Content-Length: " . strlen($jsonContent)
        ];
        
        // Preparar info de debug con más detalle
        $debugInfo = [
            'request' => [
                'url' => $url,
                'header' => $header,
                'headers_array' => $headers,
                'headers_string' => implode("\r\n", $headers),
                'body' => $bookingDataClean,
                'body_json' => $jsonContent,
                'body_length' => strlen($jsonContent),
                'body_md5' => md5($jsonContent),
                'encoding' => mb_detect_encoding($jsonContent),
                'php_version' => PHP_VERSION
            ]
        ];
        
        $options = [
            'http' => [
                'header' => implode("\r\n", $headers) . "\r\n",
                'method' => 'POST',
                'content' => $jsonContent,
                'ignore_errors' => true, // Importante: para capturar respuestas con códigos de error
                'protocol_version' => '1.1'
            ]
        ];
        
        $context = stream_context_create($options);
        
        // Añadir información del contexto al debug
        $debugInfo['request']['stream_context'] = stream_context_get_options($context);
        $debugInfo['request']['http_options'] = $options['http'];
        
        $response = @file_get_contents($url, false, $context);
        
        // Obtener headers de respuesta
        $responseHeaders = $http_response_header ?? [];
        $httpCode = 0;
        if (!empty($responseHeaders)) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $responseHeaders[0], $matches);
            $httpCode = intval($matches[1] ?? 0);
        }
        
        // Añadir respuesta al debug
        $debugInfo['response'] = [
            'http_code' => $httpCode,
            'headers' => $responseHeaders,
            'body' => $response ?: 'EMPTY RESPONSE'
        ];
        
        if ($response === false) {
            $error = error_get_last();
            return [
                'success' => false,
                'error' => 'Error al conectar con Auriga: ' . ($error['message'] ?? 'Unknown error'),
                'http_code' => $httpCode,
                'debug_info' => $debugInfo
            ];
        }
        
        $responseData = json_decode($response, true);
        $debugInfo['response']['decoded'] = $responseData;
        
        // Verificar si la respuesta tiene bookingId
        if ($httpCode === 201 && !empty($responseData['bookingId'])) {
            return [
                'success' => true,
                'data' => $responseData,
                'http_code' => $httpCode,
                'debug_info' => $debugInfo
            ];
        }
        
        // Si no es exitoso, devolver toda la información
        return [
            'success' => false,
            'error' => 'Error en la respuesta de Auriga',
            'http_code' => $httpCode,
            'response' => $responseData,
            'raw_response' => $response,
            'debug_info' => $debugInfo
        ];
    }

    /**
     * Guardar reserva en base de datos
     */
    private function guardarReservaEnBD($userId, $bookingData, $aurigaResponse, $originalData) {
        $sql = "INSERT INTO reservation_logs (
                    user_id, 
                    booking_id_auriga, 
                    booking_date,
                    client_name,
                    client_phone,
                    pickup_address,
                    destination_address,
                    passengers_details,
                    special_instructions,
                    provider_name,
                    service_id,
                    auriga_request,
                    auriga_response
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->db->prepare($sql);
        
        // Preparar datos de dirección para JSON
        $pickupAddressJson = [
            'type' => $originalData->pickupAddress->type ?? 'address',
            'latitude' => $bookingData['pickupAddress']['latitude'],
            'longitude' => $bookingData['pickupAddress']['longitude'],
            'bldgNumber' => $bookingData['pickupAddress']['bldgNumber'],
            'street' => $bookingData['pickupAddress']['street'],
            'locality' => $bookingData['pickupAddress']['locality'],
            'town' => $bookingData['pickupAddress']['town'],
            'country' => $bookingData['pickupAddress']['country'],
            'address' => $originalData->pickupAddress->address ?? null
        ];
        
        // Si es aeropuerto, añadir campos específicos
        if ($originalData->pickupAddress->type === 'airport') {
            $pickupAddressJson['terminal'] = $originalData->pickupAddress->terminal ?? null;
            $pickupAddressJson['flight_number'] = $originalData->pickupAddress->flightNumber ?? null;
            $pickupAddressJson['flight_origin'] = $originalData->pickupAddress->flightOrigin ?? null;
        }
        
        // Dirección destino
        $destinationAddressJson = null;
        if ($bookingData['destinationAddress']) {
            $destinationAddressJson = [
                'type' => $originalData->destinationAddress->type ?? 'address',
                'latitude' => $bookingData['destinationAddress']['latitude'],
                'longitude' => $bookingData['destinationAddress']['longitude'],
                'bldgNumber' => $bookingData['destinationAddress']['bldgNumber'],
                'street' => $bookingData['destinationAddress']['street'],
                'locality' => $bookingData['destinationAddress']['locality'],
                'town' => $bookingData['destinationAddress']['town'],
                'country' => $bookingData['destinationAddress']['country'],
                'address' => $originalData->destinationAddress->address ?? null
            ];
            
            if ($originalData->destinationAddress->type === 'airport') {
                $destinationAddressJson['terminal'] = $originalData->destinationAddress->terminal ?? null;
            }
        }
        
        // Detalles de pasajeros - CORRECCIÓN: datos vienen directamente
        $passengersDetails = [
            'number_of_passengers' => $originalData->numberOfPassengers ?? 1,
            'child_seat' => $originalData->childSeat ?? false,
            'vehicle_5_6_seats' => $originalData->vehicle56Seats ?? false,
            'vehicle_7_seats' => $originalData->vehicle7Seats ?? false
        ];
        
        $stmt->execute([
            $userId,
            $aurigaResponse['bookingId'],
            $bookingData['bookingDate'],
            $bookingData['clientName'],
            $bookingData['phoneNumber'],
            json_encode($pickupAddressJson, JSON_UNESCAPED_UNICODE),
            $destinationAddressJson ? json_encode($destinationAddressJson, JSON_UNESCAPED_UNICODE) : null,
            json_encode($passengersDetails, JSON_UNESCAPED_UNICODE),
            $bookingData['special'],
            $aurigaResponse['providerName'] ?? null,
            $aurigaResponse['serviceId'] ?? null,
            json_encode($bookingData, JSON_UNESCAPED_UNICODE),
            json_encode($aurigaResponse, JSON_UNESCAPED_UNICODE)
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Eliminar ceros finales de coordenadas según documentación Auriga
     * Ej: 41.3801872805610 -> 41.380187280561
     * PERO: 0.0 debe quedar como 0.0
     */
    private function eliminarUltimoDigitoCero($numero) {
        // Si es exactamente 0.0, devolver sin cambios
        if ($numero == 0.0) {
            return '0.0';
        }
        
        // Convertir a string para manipular
        $str = (string)$numero;
        
        // Si tiene decimales, eliminar ceros finales
        if (strpos($str, '.') !== false) {
            $str = rtrim($str, '0');
            // Si terminó en punto, mantener al menos un cero
            if (substr($str, -1) === '.') {
                $str .= '0';
            }
        }
        
        return $str;
    }

    private function limitarNombreA30Bytes($cadena) {
        if (strlen($cadena) < 30) {
            return $cadena;
        } else {
            return substr($cadena, 0, 29);
        }
    }

    /**
     * Obtener detalle de una reserva (consultando API Auriga)
     */
    public function getReservationDetail($bookingIdAuriga) {
        try {
            // Verificar autenticación
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                http_response_code(401);
                echo json_encode(['message' => 'Token no proporcionado']);
                return;
            }
            
            $decoded = JWTHelper::validateToken($token);
            $userId = $decoded['userId'];

            // Verificar que la reserva pertenece al usuario
            $stmt = $this->db->prepare("SELECT * FROM reservation_logs WHERE booking_id_auriga = ? AND user_id = ?");
            $stmt->execute([$bookingIdAuriga, $userId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                http_response_code(404);
                echo json_encode(['message' => 'Reserva no encontrada']);
                return;
            }

            // TODO: Aquí llamarías a la API de Auriga para obtener el estado actual
            // Por ahora devolvemos los datos locales

            echo json_encode([
                'success' => true,
                'data' => [
                    'id' => $reservation['id'],
                    'bookingIdAuriga' => $reservation['booking_id_auriga'],
                    'bookingDate' => $reservation['booking_date'],
                    'pickupAddress' => json_decode($reservation['pickup_address']),
                    'destinationAddress' => json_decode($reservation['destination_address']),
                    'passengersDetails' => json_decode($reservation['passengers_details']),
                    'specialInstructions' => $reservation['special_instructions'],
                    'providerName' => $reservation['provider_name'],
                    'serviceId' => $reservation['service_id'],
                    'createdAt' => $reservation['created_at']
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error del servidor',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Listar reservas del usuario con paginación
     */
    public function getUserReservations() {
        try {
            // Verificar autenticación
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                http_response_code(401);
                echo json_encode(['message' => 'Token no proporcionado']);
                return;
            }
            
            $decoded = JWTHelper::validateToken($token);
            $userId = $decoded['userId'];

            // Obtener parámetros de paginación y filtro
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $limit = isset($_GET['limit']) ? min(50, max(1, intval($_GET['limit']))) : 10;
            $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all'; // all, upcoming, past
            $offset = ($page - 1) * $limit;

            // Construir condición WHERE según el filtro
            $whereCondition = "WHERE user_id = ?";
            $params = [$userId];
            
            switch ($filter) {
                case 'upcoming':
                    $whereCondition .= " AND booking_date >= CURDATE() AND status != 'cancelled'";
                    break;
                case 'past':
                    $whereCondition .= " AND (booking_date < CURDATE() OR status = 'cancelled')";
                    break;
                case 'all':
                default:
                    // No filtro adicional
                    break;
            }

            // Primero, obtener el total de registros
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as total
                FROM reservation_logs 
                $whereCondition
            ");
            $countStmt->execute($params);
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Calcular total de páginas
            $totalPages = ceil($totalCount / $limit);

            // Obtener las reservas con paginación
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    booking_id_auriga,
                    booking_date,
                    pickup_address,
                    destination_address,
                    passengers_details,
                    special_instructions,
                    status,
                    created_at
                FROM reservation_logs 
                $whereCondition
                ORDER BY booking_date DESC 
                LIMIT ? OFFSET ?
            ");
            
            // Añadir limit y offset a los parámetros
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt->execute($params);
            $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Procesar los JSON fields
            foreach ($reservations as &$reservation) {
                $reservation['pickup_address'] = json_decode($reservation['pickup_address']);
                $reservation['destination_address'] = json_decode($reservation['destination_address']);
                $reservation['passengers_details'] = json_decode($reservation['passengers_details']);
            }

            // Devolver respuesta con metadata de paginación
            echo json_encode([
                'success' => true,
                'data' => $reservations,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_items' => $totalCount,
                    'items_per_page' => $limit,
                    'has_next' => $page < $totalPages,
                    'has_previous' => $page > 1
                ]
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error del servidor',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Cancelar una reserva
     */
    public function cancelReservation($bookingIdAuriga) {
        try {
            // Verificar autenticación
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                http_response_code(401);
                echo json_encode(['message' => 'Token no proporcionado']);
                return;
            }
            
            $decoded = JWTHelper::validateToken($token);
            $userId = $decoded['userId'];

            // Verificar que la reserva pertenece al usuario y obtener detalles
            $stmt = $this->db->prepare("
                SELECT id, booking_date, status 
                FROM reservation_logs 
                WHERE booking_id_auriga = ? AND user_id = ?
            ");
            $stmt->execute([$bookingIdAuriga, $userId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                http_response_code(404);
                echo json_encode(['message' => 'Reserva no encontrada']);
                return;
            }

            // Verificar que la reserva no esté ya cancelada
            if (isset($reservation['status']) && $reservation['status'] === 'cancelled') {
                http_response_code(400);
                echo json_encode(['message' => 'La reserva ya está cancelada']);
                return;
            }

            // Verificar que la fecha de la reserva sea futura
            $bookingDate = new DateTime($reservation['booking_date']);
            $now = new DateTime();
            
            if ($bookingDate <= $now) {
                http_response_code(400);
                echo json_encode(['message' => 'No se pueden cancelar reservas pasadas']);
                return;
            }

            // Llamar a API Auriga para cancelar la reserva
            $cancelResult = $this->cancelarEnAuriga($bookingIdAuriga);
            
            if ($cancelResult['success']) {
                // Actualizar estado en base de datos solo si Auriga confirmó la cancelación
                $updateStmt = $this->db->prepare("
                    UPDATE reservation_logs 
                    SET status = 'cancelled', 
                        cancelled_at = NOW(),
                        cancellation_reason = 'Cancelado por el usuario',
                        auriga_cancel_response = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    json_encode($cancelResult['debug_info']),
                    $reservation['id']
                ]);

                // Registrar actividad
                $this->logActivity($userId, 'reservation_cancelled', [
                    'booking_id' => $bookingIdAuriga,
                    'auriga_response' => $cancelResult['http_code']
                ]);

                echo json_encode([
                    'success' => true,
                    'message' => 'Reserva cancelada exitosamente'
                ]);
            } else {
                // Si Auriga falla, devolver el error
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al cancelar la reserva en el sistema',
                    'error' => $cancelResult['error'],
                    'debug' => $cancelResult['debug_info']
                ]);
            }

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error del servidor',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generar comprobante PDF de una reserva
     */
    public function downloadReceipt($bookingIdAuriga) {
        try {
            // Verificar autenticación
            $token = JWTHelper::getBearerToken();
            
            if (!$token) {
                http_response_code(401);
                echo json_encode(['message' => 'Token no proporcionado']);
                return;
            }
            
            $decoded = JWTHelper::validateToken($token);
            $userId = $decoded['userId'];

            // Obtener datos completos de la reserva
            $stmt = $this->db->prepare("
                SELECT r.*, u.name as user_name, u.email, u.phone as user_phone
                FROM reservation_logs r
                JOIN users u ON r.user_id = u.id
                WHERE r.booking_id_auriga = ? AND r.user_id = ?
            ");
            $stmt->execute([$bookingIdAuriga, $userId]);
            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reservation) {
                http_response_code(404);
                echo json_encode(['message' => 'Reserva no encontrada']);
                return;
            }

            // Decodificar campos JSON
            $pickupAddress = json_decode($reservation['pickup_address'], true);
            $destinationAddress = json_decode($reservation['destination_address'], true);
            $passengersDetails = json_decode($reservation['passengers_details'], true);

            // Generar HTML del comprobante
            $html = $this->generateReceiptHTML($reservation, $pickupAddress, $destinationAddress, $passengersDetails);

            // Por ahora devolvemos el HTML para que el frontend lo maneje
            // En producción, podrías usar una librería PHP para generar PDF
            echo json_encode([
                'success' => true,
                'html' => $html,
                'filename' => 'reserva_' . $bookingIdAuriga . '.html'
            ]);

        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Error del servidor',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generar HTML del comprobante
     */
    private function generateReceiptHTML($reservation, $pickupAddress, $destinationAddress, $passengersDetails) {
        $bookingDate = date('d/m/Y H:i', strtotime($reservation['booking_date']));
        $createdDate = date('d/m/Y H:i', strtotime($reservation['created_at']));
        
        $pickupFormatted = $this->formatAddressForReceipt($pickupAddress);
        $destinationFormatted = $destinationAddress ? $this->formatAddressForReceipt($destinationAddress) : 'Sin destino especificado';
        
        $vehicleType = 'Estándar';
        if ($passengersDetails['vehicle_7_seats']) {
            $vehicleType = 'Vehículo 7 plazas';
        } elseif ($passengersDetails['vehicle_5_6_seats']) {
            $vehicleType = 'Vehículo 5-6 plazas';
        }

        $extras = [];
        if ($passengersDetails['child_seat']) {
            $extras[] = 'Alzador infantil';
        }
        $extrasText = !empty($extras) ? implode(', ', $extras) : 'Ninguno';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Reserva - TaxiClass</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #011850;
            color: white;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
        }
        .booking-ref {
            background-color: #f0f0f0;
            padding: 15px;
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        .section {
            margin-bottom: 25px;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
        .section h2 {
            color: #011850;
            margin-top: 0;
            font-size: 20px;
            border-bottom: 2px solid #05D9D9;
            padding-bottom: 10px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            color: #666;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            color: #666;
            font-size: 14px;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: bold;
        }
        .status.confirmed {
            background-color: #4CAF50;
            color: white;
        }
        .status.cancelled {
            background-color: #f44336;
            color: white;
        }
        @media print {
            body {
                padding: 0;
            }
            .header {
                margin-bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>TaxiClass - Comprobante de Reserva</h1>
    </div>
    
    <div class="booking-ref">
        Referencia de Reserva: #{$reservation['booking_id_auriga']}
    </div>
    
    <div class="section">
        <h2>Información del Cliente</h2>
        <div class="info-row">
            <span class="info-label">Nombre:</span>
            <span>{$reservation['user_name']}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Email:</span>
            <span>{$reservation['email']}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Teléfono:</span>
            <span>{$reservation['user_phone']}</span>
        </div>
    </div>
    
    <div class="section">
        <h2>Detalles del Servicio</h2>
        <div class="info-row">
            <span class="info-label">Fecha y hora:</span>
            <span>{$bookingDate}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Estado:</span>
            <span class="status confirmed">Confirmada</span>
        </div>
        <div class="info-row">
            <span class="info-label">Pasajeros:</span>
            <span>{$passengersDetails['number_of_passengers']}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Tipo de vehículo:</span>
            <span>{$vehicleType}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Extras:</span>
            <span>{$extrasText}</span>
        </div>
    </div>
    
    <div class="section">
        <h2>Trayecto</h2>
        <div class="info-row">
            <span class="info-label">Recogida:</span>
            <span>{$pickupFormatted}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Destino:</span>
            <span>{$destinationFormatted}</span>
        </div>
    </div>
HTML;

        if (!empty($reservation['special_instructions'])) {
            $html .= <<<HTML
    
    <div class="section">
        <h2>Observaciones</h2>
        <p>{$reservation['special_instructions']}</p>
    </div>
HTML;
        }

        $html .= <<<HTML
    
    <div class="footer">
        <p>Documento generado el {$createdDate}</p>
        <p>TaxiClass - Servicio de transporte premium</p>
        <p>Para modificaciones o cancelaciones, acceda a su cuenta en nuestra plataforma</p>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Formatear dirección para el comprobante
     */
    private function formatAddressForReceipt($address) {
        if ($address['type'] !== 'address' && !empty($address['address'])) {
            $formatted = $address['address'];
            if ($address['type'] === 'airport' && !empty($address['terminal'])) {
                $formatted .= " - Terminal " . $address['terminal'];
                if (!empty($address['flight_number'])) {
                    $formatted .= " (Vuelo " . $address['flight_number'] . ")";
                }
            }
            return $formatted;
        }
        
        $parts = [];
        if (!empty($address['street'])) $parts[] = $address['street'];
        if (!empty($address['bldgNumber'])) $parts[] = $address['bldgNumber'];
        if (!empty($address['locality']) && $address['locality'] !== $address['town']) {
            $parts[] = $address['locality'];
        }
        if (!empty($address['town'])) $parts[] = $address['town'];
        
        return implode(', ', $parts);
    }

    /**
     * Cancelar reserva en API Auriga
     */
    private function cancelarEnAuriga($bookingIdAuriga) {
        try {
            // Construir URL para DELETE
            $url = $_ENV['API_URL_AURIGA'] . 'bookings/' . $bookingIdAuriga;
            
            // Generar signature para cancelación
            $clientKey = $_ENV['CLIENT_KEY_AURIGA'];
            $clientId = $_ENV['CLIENT_ID_AURIGA'];
            
            // Para cancelación: CLIENT_KEY + CLIENT_ID + bookingId
            $signatureString = $clientKey . $clientId . $bookingIdAuriga;
            $signature = sha1($signatureString);
            
            // Header de autorización
            $authHeader = $clientId . ':' . $signature;
            
            // Body con solo el bookingId
            $body = json_encode(['bookingId' => $bookingIdAuriga]);
            
            // Headers para la petición DELETE
            $headers = [
                "Content-Type: application/json",
                "x-authorization: " . $authHeader,  // Nota: minúsculas según documentación
                "Accept: application/json",
                "Content-Length: " . strlen($body)
            ];
            
            // Debug info
            $debugInfo = [
                'request' => [
                    'url' => $url,
                    'method' => 'DELETE',
                    'signature_string' => $signatureString,
                    'signature' => $signature,
                    'auth_header' => $authHeader,
                    'headers' => $headers,
                    'body' => $body
                ]
            ];
            
            // Configurar contexto para DELETE
            $options = [
                'http' => [
                    'header' => implode("\r\n", $headers) . "\r\n",
                    'method' => 'DELETE',
                    'content' => $body,
                    'ignore_errors' => true,
                    'protocol_version' => '1.1'
                ]
            ];
            
            $context = stream_context_create($options);
            
            // Ejecutar petición
            $response = @file_get_contents($url, false, $context);
            
            // Obtener código HTTP de respuesta
            $responseHeaders = $http_response_header ?? [];
            $httpCode = 0;
            if (!empty($responseHeaders)) {
                preg_match('/HTTP\/\d\.\d\s+(\d+)/', $responseHeaders[0], $matches);
                $httpCode = intval($matches[1] ?? 0);
            }
            
            // Añadir respuesta al debug
            $debugInfo['response'] = [
                'http_code' => $httpCode,
                'headers' => $responseHeaders,
                'body' => $response ?: 'EMPTY RESPONSE'
            ];
            
            // Log para debugging
            error_log("\n=== AURIGA CANCEL REQUEST ===");
            error_log("URL: " . $url);
            error_log("Method: DELETE");
            error_log("Signature String: " . $signatureString);
            error_log("Auth Header: " . $authHeader);
            error_log("HTTP Code: " . $httpCode);
            error_log("Response: " . ($response ?: 'EMPTY'));
            
            // Evaluar resultado
            if ($httpCode === 200) {
                return [
                    'success' => true,
                    'http_code' => $httpCode,
                    'debug_info' => $debugInfo
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Error al cancelar en Auriga. HTTP Code: ' . $httpCode,
                    'http_code' => $httpCode,
                    'response' => $response,
                    'debug_info' => $debugInfo
                ];
            }
            
        } catch (Exception $e) {
            error_log("Error cancelando en Auriga: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Excepción al cancelar: ' . $e->getMessage(),
                'debug_info' => ['exception' => $e->getMessage()]
            ];
        }
    }

    /**
     * Registrar actividad en el log
     */
    private function logActivity($userId, $type, $details = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO activity_logs (user_id, activity_type, details, ip_address, user_agent)
                VALUES (?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $type,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            // Log silencioso, no interrumpir el flujo principal
            error_log("Error logging activity: " . $e->getMessage());
        }
    }

    /**
     * Extraer componentes de dirección del objeto Google Places
     */
    private function extractAddressComponents($addressData) {
        $components = [
            'bldgNumber' => '',
            'street' => '',
            'locality' => '',
            'town' => '',
            'country' => ''
        ];

        // Si es una dirección predefinida (como aeropuerto)
        if ($addressData->type === 'predefined') {
            // Para lugares predefinidos, parseamos la dirección completa
            $address = $addressData->address ?? '';
            
            // Intentar extraer la ciudad del aeropuerto
            if (stripos($address, 'Barcelona') !== false) {
                $components['town'] = 'Barcelona';
                $components['country'] = 'Spain';
            }
            
            // Para aeropuerto, usar la dirección como street
            $components['street'] = $address;
            
            return $components;
        }

        // Si tiene googlePlace con address_components
        if (isset($addressData->googlePlace) && isset($addressData->googlePlace->address_components)) {
            foreach ($addressData->googlePlace->address_components as $component) {
                $types = $component->types;
                
                if (in_array('street_number', $types)) {
                    $components['bldgNumber'] = $component->long_name;
                } elseif (in_array('route', $types)) {
                    $components['street'] = $component->long_name;
                } elseif (in_array('sublocality', $types) || in_array('sublocality_level_1', $types)) {
                    $components['locality'] = $component->long_name;
                } elseif (in_array('locality', $types)) {
                    $components['town'] = $component->long_name;
                } elseif (in_array('administrative_area_level_2', $types) && empty($components['town'])) {
                    $components['town'] = $component->long_name;
                } elseif (in_array('country', $types)) {
                    $components['country'] = $component->long_name;
                }
            }

            // Si no hay street pero sí name, usar name
            if (empty($components['street']) && isset($addressData->googlePlace->name)) {
                $components['street'] = $addressData->googlePlace->name;
            }
        }

        // Fallback: si no hay datos de Google Places, intentar usar campos directos
        if (empty($components['street']) && isset($addressData->address)) {
            // Intentar parsear la dirección completa
            $parts = explode(',', $addressData->address);
            if (count($parts) > 0) {
                $components['street'] = trim($parts[0]);
            }
            if (count($parts) > 1) {
                $components['town'] = trim($parts[count($parts) - 2] ?? '');
                $components['country'] = trim($parts[count($parts) - 1] ?? '');
            }
        }

        // Asegurar que no haya valores null
        foreach ($components as $key => $value) {
            if ($value === null || $value === false) {
                $components[$key] = '';
            }
        }

        // Los logs ahora se envían al frontend

        return $components;
    }
    
    /**
     * Formatear dirección para email de manera legible
     */
    private function formatAddressForEmail($addressData) {
        if (!$addressData) {
            return 'No especificado';
        }
        
        // Si es predefinida (aeropuerto, estación, etc.)
        if ($addressData->type === 'predefined' || $addressData->type === 'airport') {
            $formatted = $addressData->address ?? '';
            
            // Si es aeropuerto y tiene datos adicionales
            if ($addressData->type === 'airport') {
                if (!empty($addressData->flightNumber)) {
                    $formatted .= "\nVuelo: " . $addressData->flightNumber;
                }
                if (!empty($addressData->flightOrigin)) {
                    $formatted .= " desde " . $addressData->flightOrigin;
                }
                if (!empty($addressData->terminal)) {
                    $formatted .= "\nTerminal: " . $addressData->terminal;
                }
            }
            
            return $formatted;
        }
        
        // Si es dirección normal
        return $addressData->address ?? 'Dirección no especificada';
    }
    
    /**
     * Determinar tipo de vehículo según selecciones
     */
    private function determineVehicleType($data) {
        if (!empty($data->vehicle7Seats) && $data->vehicle7Seats === true) {
            return 'Vehículo 7 plazas (Mercedes Clase V)';
        }
        if (!empty($data->vehicle56Seats) && $data->vehicle56Seats === true) {
            return 'Vehículo 5-6 plazas (Mercedes Clase V)';
        }
        return 'Vehículo estándar';
    }
    
    /**
     * Determinar extras seleccionados
     */
    private function determineExtras($data) {
        $extras = [];
        
        if (!empty($data->childSeat) && $data->childSeat === true) {
            $extras[] = 'Alzador infantil';
        }
        
        return !empty($extras) ? implode(', ', $extras) : 'Ninguno';
    }
}
