<?php
// Plik: includes/db.php

// Konfiguracja połączenia z bazą danych
// Zazwyczaj dla XAMPP domyślne wartości to:
// host: localhost
// user: root
// password: '' (puste hasło)
// dbname: nazwa_twojej_bazy (tutaj: votum_nauczycieli_db)

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // Domyślnie puste hasło w XAMPP
define('DB_NAME', 'votum_nauczycieli_db');
define('DB_CHARSET', 'utf8mb4');

// Opcje PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Rzucaj wyjątki w przypadku błędów
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Domyślny tryb pobierania danych jako tablica asocjacyjna
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Używaj natywnych prepared statements
];

// Data Source Name (DSN)
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

try {
    // Utworzenie instancji PDO (obiektu połączenia)
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Obsługa błędu połączenia
    // W środowisku produkcyjnym lepiej logować błąd niż go wyświetlać
    // Dla celów deweloperskich możemy wyświetlić komunikat
    error_log("Błąd połączenia z bazą danych: " . $e->getMessage()); // Logowanie błędu do logu serwera
    die("Nie można połączyć się z bazą danych. Skontaktuj się z administratorem. Szczegóły błędu zostały zapisane w logu serwera.");
    // Możesz też wyświetlić bardziej szczegółowy błąd podczas developmentu:
    // die("Błąd połączenia z bazą danych: " . $e->getMessage());
}

// Obiekt $pdo jest teraz dostępny do wykonywania zapytań w skryptach, które dołączą ten plik.
// np. require_once 'includes/db.php';
// global $pdo; // jeśli funkcje potrzebują dostępu
?>