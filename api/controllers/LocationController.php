<?php
namespace App\controllers;

use App\models\PredefinedLocation;
use Exception;

class LocationController {
    private $db;
    private $predefinedLocation;
    
    public function __construct($db) {
        $this->db = $db;
        $this->predefinedLocation = new PredefinedLocation($db);
    }
    
    /**
     * Obtener todas las localizaciones predefinidas activas
     */
    public function getLocations($request) {
        try {
            // Obtener localizaciones
            $locations = $this->predefinedLocation->getActiveLocations();
            
            // Agrupar por categoría para mejor organización
            $groupedLocations = [];
            foreach ($locations as $location) {
                $category = $location['category'] ?: 'otros';
                if (!isset($groupedLocations[$category])) {
                    $groupedLocations[$category] = [];
                }
                $groupedLocations[$category][] = [
                    'id' => (int)$location['id'],
                    'name' => $location['name'],
                    'address' => $location['address'],
                    'icon' => $location['icon'],
                    'coordinates' => [
                        'lat' => (float)$location['latitude'],
                        'lng' => (float)$location['longitude']
                    ]
                ];
            }
            
            return $this->response(200, 'Localizaciones obtenidas', [
                'locations' => $locations,
                'grouped' => $groupedLocations
            ]);
            
        } catch (Exception $e) {
            return $this->response(500, 'Error al obtener localizaciones: ' . $e->getMessage());
        }
    }
    
    /**
     * Buscar localizaciones por término
     */
    public function searchLocations($request) {
        try {
            $searchTerm = $_GET['q'] ?? '';
            
            if (strlen($searchTerm) < 2) {
                return $this->response(400, 'El término de búsqueda debe tener al menos 2 caracteres');
            }
            
            $locations = $this->predefinedLocation->searchLocations($searchTerm);
            
            return $this->response(200, 'Búsqueda completada', [
                'locations' => $locations
            ]);
            
        } catch (Exception $e) {
            return $this->response(500, 'Error en la búsqueda: ' . $e->getMessage());
        }
    }
    
    /**
     * Helper para respuestas JSON
     */
    private function response($code, $message, $data = null) {
        http_response_code($code);
        
        $response = [
            'status' => $code < 400 ? 'success' : 'error',
            'message' => $message
        ];
        
        if ($data !== null) {
            $response = array_merge($response, $data);
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
