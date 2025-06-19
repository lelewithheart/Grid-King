<?php
/**
 * Database Configuration for Racing League Management System
 * Docker deployment with environment variable support
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn = null;
    
    public function __construct() {
        // Use environment variables for Docker, fallback to defaults for local development
        $this->host = getenv('DB_HOST') ?: 'db';
        $this->db_name = getenv('DB_NAME') ?: 'racing_league';
        $this->username = getenv('DB_USER') ?: 'racing_user';
        $this->password = getenv('DB_PASS') ?: 'racing_pass123';
    }
    
    public function getConnection() {
        if ($this->conn === null) {
            try {
                $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4";
                $this->conn = new PDO($dsn, $this->username, $this->password);
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            } catch(PDOException $e) {
                die("Connection failed: " . $e->getMessage());
            }
        }
        return $this->conn;
    }
    
    public function closeConnection() {
        $this->conn = null;
    }
}