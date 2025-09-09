<?php
namespace App\models;

use PDO;

class ReservationLog {
    private $conn;
    private $table_name = "reservation_logs";
    
    // Propiedades
    public $id;
    public $user_id;
    public $booking_id_auriga;
    public $booking_date;
    public $client_name;
    public $client_phone;
    public $pickup_address;
    public $destination_address;
    public $passengers_details;
    public $special_instructions;
    public $provider_name;
    public $service_id;
    public $auriga_request;
    public $auriga_response;
    public $created_at;
    
    // Constructor
    public function __construct($db) {
        $this->conn = $db;
    }
    
    /**
     * Obtener reservas por usuario
     */
    public function getByUserId($userId, $limit = 20, $offset = 0) {
        $query = "SELECT 
                    id,
                    booking_id_auriga,
                    booking_date,
                    pickup_address,
                    destination_address,
                    passengers_details,
                    special_instructions,
                    provider_name,
                    service_id,
                    created_at
                FROM " . $this->table_name . "
                WHERE user_id = :user_id
                ORDER BY booking_date DESC
                LIMIT :limit OFFSET :offset";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Obtener una reserva específica
     */
    public function getOne($bookingIdAuriga, $userId) {
        $query = "SELECT * FROM " . $this->table_name . "
                WHERE booking_id_auriga = :booking_id_auriga 
                AND user_id = :user_id
                LIMIT 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":booking_id_auriga", $bookingIdAuriga);
        $stmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt;
    }
    
    /**
     * Contar reservas por estado
     */
    public function getStatsByUserId($userId) {
        $query = "SELECT 
                    COUNT(*) as total,
                    COUNT(CASE WHEN booking_date > NOW() THEN 1 END) as upcoming,
                    COUNT(CASE WHEN DATE(booking_date) = CURDATE() THEN 1 END) as today,
                    COUNT(CASE WHEN booking_date < NOW() AND DATE(booking_date) != CURDATE() THEN 1 END) as completed
                FROM " . $this->table_name . "
                WHERE user_id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":user_id", $userId, PDO::PARAM_INT);
        
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Crear nueva reserva
     */
    public function create() {
        $query = "INSERT INTO " . $this->table_name . "
                SET
                    user_id = :user_id,
                    booking_id_auriga = :booking_id_auriga,
                    booking_date = :booking_date,
                    client_name = :client_name,
                    client_phone = :client_phone,
                    pickup_address = :pickup_address,
                    destination_address = :destination_address,
                    passengers_details = :passengers_details,
                    special_instructions = :special_instructions,
                    provider_name = :provider_name,
                    service_id = :service_id,
                    auriga_request = :auriga_request,
                    auriga_response = :auriga_response";
        
        $stmt = $this->conn->prepare($query);
        
        // Bind values
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":booking_id_auriga", $this->booking_id_auriga);
        $stmt->bindParam(":booking_date", $this->booking_date);
        $stmt->bindParam(":client_name", $this->client_name);
        $stmt->bindParam(":client_phone", $this->client_phone);
        $stmt->bindParam(":pickup_address", $this->pickup_address);
        $stmt->bindParam(":destination_address", $this->destination_address);
        $stmt->bindParam(":passengers_details", $this->passengers_details);
        $stmt->bindParam(":special_instructions", $this->special_instructions);
        $stmt->bindParam(":provider_name", $this->provider_name);
        $stmt->bindParam(":service_id", $this->service_id);
        $stmt->bindParam(":auriga_request", $this->auriga_request);
        $stmt->bindParam(":auriga_response", $this->auriga_response);
        
        if($stmt->execute()) {
            return true;
        }
        
        return false;
    }
}
