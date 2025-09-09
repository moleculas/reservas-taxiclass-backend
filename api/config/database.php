<?php
namespace App\config;

use PDO;
use PDOException;

class Database {
    private $host = "localhost";
    private $database = "taxiclass_db";
    private $username = "root";
    private $password = "";
    private $charset = "utf8mb4";
    private $pdo;
    
    public function getConnection() {
        $this->pdo = null;
        
        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->database . ";charset=" . $this->charset;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            echo json_encode([
                "status" => "error",
                "message" => "Error de conexión: " . $exception->getMessage()
            ]);
            exit();
        }
        
        return $this->pdo;
    }
}
