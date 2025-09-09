<?php
namespace App\models;

use PDO;
use Exception;

class PredefinedLocation {
    private $conn;
    private $table = 'predefined_locations';
    
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener todas las localizaciones activas
     */
    public function getActiveLocations() {
        try {
            $query = "SELECT id, name, address, latitude, longitude, category, icon 
                      FROM " . $this->table . " 
                      WHERE is_active = 1 
                      ORDER BY category, name";
            
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            
            $locations = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $locations[] = $row;
            }
            
            return $locations;
        } catch (Exception $e) {
            error_log("Error al obtener localizaciones: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Obtener una localización por ID
     */
    public function getLocationById($id) {
        try {
            $query = "SELECT * FROM " . $this->table . " WHERE id = :id AND is_active = 1";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("Error al obtener localización: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Buscar localizaciones por nombre
     */
    public function searchLocations($searchTerm) {
        try {
            $query = "SELECT id, name, address, latitude, longitude, category, icon 
                      FROM " . $this->table . " 
                      WHERE is_active = 1 
                      AND (name LIKE :search OR address LIKE :search)
                      ORDER BY name";
            
            $searchPattern = '%' . $searchTerm . '%';
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':search', $searchPattern);
            $stmt->execute();
            
            $locations = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $locations[] = $row;
            }
            
            return $locations;
        } catch (Exception $e) {
            error_log("Error al buscar localizaciones: " . $e->getMessage());
            return [];
        }
    }
}
