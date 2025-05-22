<?php
// Plik: join_session.php - endpoint dołączania do sesji głosowania

header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';      // requireLogin(), getLoggedInUserId()
require_once __DIR__ . '/includes/functions.php'; // sendJsonResponse()
require_once __DIR__ . '/classes/Session.php';    // VotingSession
require_once __DIR__ . '/classes/Participant.php';// Participant
// Nie potrzebujemy Resolution.php bezpośrednio tutaj, bo VotingSession->getPublicData() sobie z tym radzi

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Wymagaj zalogowania użytkownika
requireLogin();

// 2. Sprawdź, czy metoda żądania to POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano POST.']);
}

// 3. Odczytaj dane JSON z ciała żądania
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Nieprawidłowy format JSON.']);
}

$sessionCode = trim($input['session_code'] ?? '');

if (empty($sessionCode)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Kod sesji jest wymagany.']);
}

// 4. Znajdź sesję głosowania po kodzie
$votingSession = VotingSession::findByCode($pdo, $sessionCode);

if (!$votingSession) {
    sendJsonResponse(404, ['status' => 'error', 'message' => 'Sesja o podanym kodzie nie istnieje.']);
}

// 5. Sprawdź status sesji
if ($votingSession->status !== 'open') {
    $statusMessage = ($votingSession->status === 'closed') ? 'Sesja jest już zamknięta.' : 'Sesja nie jest aktualnie otwarta.';
    sendJsonResponse(403, ['status' => 'error', 'message' => $statusMessage, 'session_status' => $votingSession->status]);
}

// 6. Pobierz ID zalogowanego użytkownika
$userId = getLoggedInUserId();
if ($userId === null) { // Dodatkowe zabezpieczenie, choć requireLogin() powinno to załatwić
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się zidentyfikować użytkownika.']);
}


// 7. Sprawdź, czy użytkownik już nie dołączył / dołącz go
$participant = Participant::findByUserAndSession($pdo, $userId, $votingSession->session_id);
$message = 'Już jesteś uczestnikiem tej sesji.';

if (!$participant) {
    // Użytkownik jeszcze nie dołączył, próbujemy go dodać
    $participantHandler = new Participant($pdo);
    $participant = $participantHandler->joinSession($userId, $votingSession->session_id);
    
    if (!$participant) {
        // Błąd podczas próby dodania do tabeli participants
        // Może to być np. wyścig i inny proces dodał go w międzyczasie,
        // lub inny błąd SQL.
        // Spróbujmy jeszcze raz go pobrać, na wypadek gdyby jednak został dodany
        $participant = Participant::findByUserAndSession($pdo, $userId, $votingSession->session_id);
        if (!$participant) {
             sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się dołączyć do sesji. Spróbuj ponownie później.']);
        }
    } else {
        $message = 'Pomyślnie dołączono do sesji.';
    }
}

// Jeśli dotarliśmy tutaj, $participant powinien być obiektem (albo istniał, albo został dodany)
if ($participant && $participant->participant_id) {
    sendJsonResponse(200, [
        'status' => 'success',
        'message' => $message,
        'data' => $votingSession->getPublicData($participant->participant_id)
    ]);
} else {
    // Ostateczny fallback, jeśli coś poszło bardzo nie tak
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Wystąpił nieoczekiwany błąd podczas przetwarzania dołączenia do sesji.']);
}

?>