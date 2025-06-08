<?php
header('Content-Type: application/json');
require_once 'config/db.php';

class NewsAPI {
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db ?: $this->connectDB();
    }
    
    private function connectDB() {
    try {
        $this->db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    
    public function getAllNews() {
        $query = "SELECT id, title, url, summary, publish_date, created_at FROM news ORDER BY publish_date DESC";
        $result = $this->db->query($query);
        
        if (!$result) {
            http_response_code(500);
            return ['error' => 'Database query failed'];
        }
        
        $news = [];
        while ($row = $result->fetch()) {
            $news[] = $row;
        }
        
        return $news;
    }
    
    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $news = $this->getAllNews();
            echo json_encode($news);
        } else {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
        }
    }
    public function getDb() {
        return $this->db;
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db = null;
        }
    }
}

// Handle the request
$api = new NewsAPI();
$api->handleRequest();
?>