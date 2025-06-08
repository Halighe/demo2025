<?php
require_once 'config/db.php';

// сюда положить ссылку на новости
define('NEWS_URL', 'https://example.com/news');

class NewsParser {
    private $db;
    
    public function __construct() {
        $this->connectDB();
        // один раз выполнить этот метод для создания таблицы новостей в бд
        // $this->createNewsTable();
    }
    
    // подключение к бд используя PDO
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
    
    private function createNewsTable() {
        $query = "CREATE TABLE IF NOT EXISTS news (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            url VARCHAR(255) NOT NULL,
            summary TEXT,
            publish_date DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_url (url)
        )";
        
        if (!$this->db->query($query)) {
            die("Error creating table: " . $this->db->error);
        }
    }
    // Подключение к HTML-странице через cURL. Можно сделать через file_get_contents()
    public function fetchHtml($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
        
        $html = curl_exec($ch);
        
        if (curl_errno($ch)) {
            die("Ошибка при получении URL: " . curl_error($ch));
        }
        
        curl_close($ch);
        return $html;
    }
    
    public function parseNews($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // Этот XPath необходимо скорректировать на основе фактической HTML-структуры новостного сайта
        $newsNodes = $xpath->query("//div[contains(@class, 'news-item')]");
        
        $newsItems = [];
        
        foreach ($newsNodes as $node) {
            $titleNode = $xpath->query(".//h2/a", $node)->item(0);
            $summaryNode = $xpath->query(".//p[contains(@class, 'summary')]", $node)->item(0);
            $dateNode = $xpath->query(".//time", $node)->item(0);
            
            if ($titleNode) {
                $newsItem = [
                    'title' => trim($titleNode->nodeValue),
                    'url' => $titleNode->getAttribute('href'),
                    'summary' => $summaryNode ? trim($summaryNode->nodeValue) : '',
                    'publish_date' => $dateNode ? $dateNode->getAttribute('datetime') : date('Y-m-d H:i:s')
                ];
                
                $newsItems[] = $newsItem;
            }
        }
        
        return $newsItems;
    }
    
    public function saveNews($newsItems) {
        $stmt = $this->db->prepare("INSERT INTO news (title, url, summary, publish_date) 
                                   VALUES (?, ?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE title=VALUES(title), summary=VALUES(summary), publish_date=VALUES(publish_date)");
        
        $count = 0;
        
        foreach ($newsItems as $item) {
            $stmt->bind_param("ssss", $item['title'], $item['url'], $item['summary'], $item['publish_date']);
            
            if ($stmt->execute()) {
                $count++;
            } else {
                error_log("Ошибка сохранения новости: " . $stmt->error);
            }
        }
        
        $stmt = null;
        return $count;
    }
    
    public function run() {
        $html = $this->fetchHtml(NEWS_URL);
        $newsItems = $this->parseNews($html);
        $savedCount = $this->saveNews($newsItems);
        
        echo "Процесс завершен. Сохранено/обновлено {$savedCount} новостей.";
    }
    
    public function __destruct() {
        if ($this->db) {
            $this->db = null;
        }
    }
}

// запускаем парсер
$parser = new NewsParser();
$parser->run();
?>