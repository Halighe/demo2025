<?php
// Include your NewsAPI class
require_once 'news_api.php';

class NewsAPITest {
    private $testDb;
    private $testTable = 'test_news';
    
    public function __construct() {
        // Set up test database
        $this->setUpTestDatabase();
    }
    
    private function setUpTestDatabase() {
        // Connect without selecting database first
        $this->testDb = new PDO(
            "mysql:host=" . DB_HOST, 
            DB_USER, 
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        // Create test database
        $this->testDb->exec("CREATE DATABASE IF NOT EXISTS test_news_db");
        $this->testDb->exec("USE test_news_db");
        
        // Create test table
        $this->testDb->exec("CREATE TABLE IF NOT EXISTS {$this->testTable} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            url VARCHAR(255) NOT NULL,
            summary TEXT,
            publish_date DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Insert test data
        $this->testDb->exec("INSERT INTO {$this->testTable} (title, url, summary, publish_date) VALUES
            ('Test News 1', 'https://example.com/1', 'Summary 1', '2023-01-01 10:00:00'),
            ('Test News 2', 'https://example.com/2', 'Summary 2', '2023-01-02 10:00:00')");
    }
    
    private function cleanUp() {
        $this->testDb->exec("DROP DATABASE IF EXISTS test_news_db");
        $this->testDb = null;
    }
    
    public function testGetAllNews() {
        // Create API instance with test DB
        $api = new NewsAPI($this->testDb);
        
        // Capture output
        ob_start();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $api->handleRequest();
        $output = ob_get_clean();
        
        // Decode JSON
        $result = json_decode($output, true);
        
        // Assertions
        if (!is_array($result)) {
            echo "FAIL: Expected array, got " . gettype($result) . "\n";
            return false;
        }
        
        if (count($result) !== 2) {
            echo "FAIL: Expected 2 news items, got " . count($result) . "\n";
            return false;
        }
        
        if ($result[0]['title'] !== 'Test News 1') {
            echo "FAIL: First item title mismatch\n";
            return false;
        }
        
        echo "PASS: testGetAllNews\n";
        return true;
    }
    
    public function testInvalidMethod() {
        $api = new NewsAPI($this->testDb);
        
        ob_start();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $api->handleRequest();
        $output = ob_get_clean();
        
        $result = json_decode($output, true);
        
        if (!isset($result['error'])) {
            echo "FAIL: Expected error message\n";
            return false;
        }
        
        if (http_response_code() !== 405) {
            echo "FAIL: Expected 405 status code\n";
            return false;
        }
        
        echo "PASS: testInvalidMethod\n";
        return true;
    }
    
    public function runAllTests() {
        $results = [
            $this->testGetAllNews(),
            $this->testInvalidMethod()
        ];
        
        $this->cleanUp();
        
        $passed = count(array_filter($results));
        $total = count($results);
        
        echo "\nResults: {$passed}/{$total} tests passed\n";
        return $passed === $total;
    }
}

// Run tests
$tester = new NewsAPITest();
$success = $tester->runAllTests();

exit($success ? 0 : 1);