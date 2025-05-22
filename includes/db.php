<?php
// Plik: includes/db.php

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); 
define('DB_NAME', 'votum_nauczycieli_db'); 
define('DB_CHARSET', 'utf8mb4');

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, 
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       
    PDO::ATTR_EMULATE_PREPARES   => false,                  
];

$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    error_log("Błąd połączenia z bazą danych: " . $e->getMessage()); 
    if (php_sapi_name() !== 'cli' && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Błąd serwera: Nie można połączyć się z bazą danych.']);
        exit;
    } else {
        die("Nie można połączyć się z bazą danych. Skontaktuj się z administratorem. Szczegóły błędu zostały zapisane w logu serwera.");
    }
}
?>