<?php
// Plik: create_session.php

// Ustawienie Content-Type na JSON dla wszystkich odpowiedzi z tego skryptu
header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';         // Połączenie z bazą $pdo
require_once __DIR__ . '/includes/auth.php';       // Funkcje autoryzacyjne
require_once __DIR__ . '/includes/functions.php';  // Dla sendJsonResponse
require_once __DIR__ . '/classes/Session.php';     // Klasa VotingSession

// Rozpocznij sesję PHP, jeśli jeszcze nie jest aktywna (auth.php może to już robić)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Wymagaj zalogowania i roli administratora
requireAdmin(); // Ta funkcja zakończy skrypt, jeśli warunki nie są spełnione

// Sprawdź, czy metoda żądania to POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano POST.']);
}

// Odczytaj dane JSON z ciała żądania
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE); // TRUE zwraca tablicę asocjacyjną

// Walidacja danych wejściowych
if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Nieprawidłowy format JSON.']);
}

$title = $input['title'] ?? null;
$resolutionsTexts = $input['resolutions'] ?? null; // Powinna być tablicą stringów

if (empty(trim($title))) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Tytuł sesji jest wymagany.']);
}

if (!is_array($resolutionsTexts) || empty($resolutionsTexts)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Lista rezolucji jest wymagana i nie może być pusta.']);
}

foreach ($resolutionsTexts as $resText) {
    if (!is_string($resText) || empty(trim($resText))) {
        sendJsonResponse(400, ['status' => 'error', 'message' => 'Każda rezolucja musi być niepustym ciągiem znaków.']);
    }
}

// Pobierz ID zalogowanego administratora
$creatorId = getLoggedInUserId();
if ($creatorId === null) {
    // To nie powinno się zdarzyć, jeśli requireAdmin() działa poprawnie, ale dla pewności
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się zidentyfikować administratora.']);
}

// Utwórz nową sesję głosowania
$votingSession = new VotingSession($pdo); // Przekaż obiekt PDO
$createdSession = $votingSession->create(trim($title), $creatorId, $resolutionsTexts);

if ($createdSession) {
    // Przygotuj dane odpowiedzi - nie chcemy wysyłać całego obiektu z PDO w środku
    $responseData = [
        'session_id' => $createdSession->session_id,
        'code' => $createdSession->code,
        'title' => $createdSession->title,
        'status' => $createdSession->status,
        'created_at' => $createdSession->created_at
        // Możesz dodać więcej pól, jeśli potrzebujesz
    ];
    sendJsonResponse(201, ['status' => 'success', 'message' => 'Sesja głosowania została pomyślnie utworzona.', 'data' => $responseData]);
} else {
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się utworzyć sesji głosowania. Sprawdź logi serwera.']);
}

?>