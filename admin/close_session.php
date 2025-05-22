<?php
// Plik: admin/close_session.php
// Endpoint do zamykania sesji głosowania przez administratora.

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';      // requireAdmin()
require_once __DIR__ . '/../includes/functions.php'; // sendJsonResponse()
require_once __DIR__ . '/../classes/Session.php';    // VotingSession

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireAdmin(); // Tylko administrator może zamykać sesje

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano POST.']);
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Nieprawidłowy format JSON.']);
}

$sessionId = filter_var($input['session_id'] ?? null, FILTER_VALIDATE_INT);

if (!$sessionId) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Pole session_id (numeryczne) jest wymagane.']);
}

$votingSession = VotingSession::findById($pdo, $sessionId);

if (!$votingSession) {
    sendJsonResponse(404, ['status' => 'error', 'message' => "Sesja o ID {$sessionId} nie została znaleziona."]);
}

if ($votingSession->status === 'closed') {
    sendJsonResponse(200, [ // Można uznać za sukces, bo cel osiągnięty
        'status' => 'success', 
        'message' => "Sesja o ID {$sessionId} jest już zamknięta.",
        'session' => $votingSession->getPublicData() // Zwróć dane sesji
    ]);
}

if ($votingSession->close()) {
    sendJsonResponse(200, [
        'status' => 'success',
        'message' => "Sesja o ID {$sessionId} została pomyślnie zamknięta.",
        'session' => $votingSession->getPublicData() // Zwróć zaktualizowane dane sesji
    ]);
} else {
    sendJsonResponse(500, ['status' => 'error', 'message' => "Nie udało się zamknąć sesji o ID {$sessionId}. Sprawdź logi serwera."]);
}
?>