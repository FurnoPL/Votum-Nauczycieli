<?php
// Plik: temporary_create_teacher.php
// UWAGA: TEN SKRYPT NALEŻY USUNĄĆ LUB ZABEZPIECZYĆ PO JEDNORAZOWYM UŻYCIU!

header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';   // Połączenie z bazą $pdo
require_once __DIR__ . '/classes/User.php';  // Klasa User
require_once __DIR__ . '/includes/functions.php'; // Dla sendJsonResponse

// --- Konfiguracja danych nauczyciela ---
$teacherEmail = 'teacher1@example.com';     // Możesz zmienić na inny email
$teacherPassword = 'password123';           // Możesz zmienić, jeśli chcesz
$teacherName = 'Jan Nauczyciel';            // Możesz zmienić imię i nazwisko
$teacherRole = 'teacher';                   // Rola ustawiona na 'teacher'
// --- Koniec konfiguracji ---

// Proste zabezpieczenie, aby skrypt był wywoływany tylko przez GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Użyj metody GET do uruchomienia tego skryptu.']);
    exit;
}

echo "Próba utworzenia użytkownika nauczyciela...\n"; // Komunikat dla wywołania z przeglądarki

try {
    $user = new User($pdo); // Utwórz instancję klasy User, przekazując połączenie PDO

    // Sprawdź, czy użytkownik (nauczyciel) o podanym emailu już istnieje
    if ($user->findByEmail($teacherEmail)) {
        sendJsonResponse(200, ['status' => 'info', 'message' => "Użytkownik nauczyciel ({$teacherEmail}) już istnieje."]);
        exit;
    }

    // Utwórz nowego użytkownika (nauczyciela)
    $newUserId = $user->create($teacherName, $teacherEmail, $teacherPassword, $teacherRole);

    if ($newUserId) {
        sendJsonResponse(201, [
            'status' => 'success',
            'message' => "Użytkownik nauczyciel ({$teacherEmail}) został pomyślnie utworzony.",
            'userId' => $newUserId
        ]);
    } else {
        // Sprawdź, czy błąd nie wynikał z ponownego istnienia emaila (na wypadek wyścigu, mało prawdopodobne)
        if ($user->findByEmail($teacherEmail)) {
             sendJsonResponse(200, ['status' => 'info', 'message' => "Użytkownik nauczyciel ({$teacherEmail}) już istnieje (sprawdzone po nieudanej próbie utworzenia)."]);
        } else {
            sendJsonResponse(500, ['status' => 'error', 'message' => "Nie udało się utworzyć użytkownika nauczyciela. Sprawdź logi serwera PHP."]);
        }
    }

} catch (PDOException $e) {
    // Logowanie błędu PDO do logu serwera
    error_log("Błąd PDO w temporary_create_teacher.php: " . $e->getMessage());
    sendJsonResponse(500, ['status' => 'error', 'message' => "Błąd bazy danych podczas tworzenia nauczyciela: " . $e->getMessage()]);
} catch (Exception $e) {
    // Logowanie ogólnego błędu do logu serwera
    error_log("Ogólny błąd w temporary_create_teacher.php: " . $e->getMessage());
    sendJsonResponse(500, ['status' => 'error', 'message' => "Wystąpił nieoczekiwany błąd: " . $e->getMessage()]);
}

?>