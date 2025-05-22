<?php
// Plik: temporary_create_admin.php
// UWAGA: TEN SKRYPT NALEŻY USUNĄĆ LUB ZABEZPIECZYĆ PO UŻYCIU!

header('Content-Type: application/json'); // Zawsze dobrze zwracać JSON

require_once __DIR__ . '/includes/db.php';   // Połączenie z bazą $pdo
require_once __DIR__ . '/classes/User.php';  // Klasa User
require_once __DIR__ . '/includes/functions.php'; // Dla sendJsonResponse

// --- Konfiguracja danych administratora ---
$adminEmail = 'admin@example.com';
$adminPassword = 'password123'; // Zmień na silniejsze, jeśli chcesz, ale to do testów
$adminName = 'Administrator Systemu';
// --- Koniec konfiguracji ---

if ($_SERVER['REQUEST_METHOD'] !== 'GET') { // Prosty sposób, by nie uruchomić przypadkiem inaczej
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Użyj metody GET do uruchomienia tego skryptu.']);
    exit;
}

echo "Próba utworzenia administratora...\n";

try {
    $user = new User($pdo);

    // Sprawdź, czy admin już istnieje
    if ($user->findByEmail($adminEmail)) {
        sendJsonResponse(200, ['status' => 'info', 'message' => "Użytkownik admin ({$adminEmail}) już istnieje."]);
        exit;
    }

    // Utwórz administratora
    $newUserId = $user->create($adminName, $adminEmail, $adminPassword, 'admin');

    if ($newUserId) {
        sendJsonResponse(201, [
            'status' => 'success',
            'message' => "Użytkownik admin ({$adminEmail}) został pomyślnie utworzony.",
            'userId' => $newUserId
        ]);
    } else {
        sendJsonResponse(500, ['status' => 'error', 'message' => "Nie udało się utworzyć użytkownika admin. Sprawdź logi serwera."]);
    }

} catch (PDOException $e) {
    error_log("Błąd PDO w temporary_create_admin.php: " . $e->getMessage());
    sendJsonResponse(500, ['status' => 'error', 'message' => "Błąd bazy danych: " . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Ogólny błąd w temporary_create_admin.php: " . $e->getMessage());
    sendJsonResponse(500, ['status' => 'error', 'message' => "Wystąpił błąd: " . $e->getMessage()]);
}

?>