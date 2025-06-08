<?php
require_once 'news_api.php';

class NewsAPITest {
    private $testDb;
    
    public function __construct() {
        $this->setUpTestDatabase();
    }
    
    private function setUpTestDatabase() {
        try {
            $this->testDb = new PDO(
                "mysql:host=localhost;dbname=test_news_db", 
                "root", 
                "",
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            
            // Create test data
            $this->testDb->exec("CREATE TABLE IF NOT EXISTS news (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                url VARCHAR(255) NOT NULL,
                summary TEXT,
                publish_date DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            $this->testDb->exec("TRUNCATE TABLE news");
            $this->testDb->exec("INSERT INTO news (title, url, summary, publish_date) VALUES
                ('Test News 1', 'https://example.com/1', 'Summary 1', '2023-01-01 10:00:00'),
                ('Test News 2', 'https://example.com/2', 'Summary 2', '2023-01-02 10:00:00')");
                
        } catch (PDOException $e) {
            die("Test setup failed: " . $e->getMessage());
        }
    }
    
    private function makeRequest($method = 'GET') {
        // Backup original SERVER vars
        $backup = $_SERVER;
        
        // Set test environment
        $_SERVER['REQUEST_METHOD'] = $method;
        
        // Start output buffering
        ob_start();
        
        // Create and run API
        $api = new NewsAPI($this->testDb);
        $api->handleRequest();
        
        // Get output
        $output = ob_get_clean();
        
        // Restore SERVER vars
        $_SERVER = $backup;
        
        return $output;
    }
    
    public function testGetAllNews() {
        $output = $this->makeRequest('GET');
        $result = json_decode($output, true);
        
        if (!is_array($result)) {
            echo "FAIL: Expected array, got " . gettype($result) . "\n";
            return false;
        }
        
        if (count($result) !== 2) {
            echo "FAIL: Expected 2 items, got " . count($result) . "\n";
            echo "Output: $output\n";
            return false;
        }
        
        if ($result[0]['title'] !== 'Test News 1') {
            echo "FAIL: Title mismatch. Got: '{$result[0]['title']}'\n";
            return false;
        }
        
        echo "PASS: testGetAllNews\n";
        return true;
    }
    
    public function testInvalidMethod() {
        $output = $this->makeRequest('POST');
        $result = json_decode($output, true);
        
        if (!isset($result['error'])) {
            echo "FAIL: Expected error message\n";
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
        
        $passed = count(array_filter($results));
        $total = count($results);
        
        echo "\nResults: $passed/$total tests passed\n";
        return $passed === $total;
    }
}

// Run tests
$tester = new NewsAPITest();
$success = $tester->runAllTests();

exit($success ? 0 : 1);