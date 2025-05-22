<?php
// Plik: api/admin/stop_resolution_voting.php
// Endpoint do zatrzymywania głosowania nad konkretną uchwałą (lub pomijania jej).

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';      
require_once __DIR__ . '/../../includes/functions.php'; 
require_once __DIR__ . '/../../classes/Resolution.php'; 
require_once __DIR__ . '/../../classes/Session.php'; 

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireAdminLogin(); 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano POST.']);
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

$resolutionId = filter_var($input['resolution_id'] ?? null, FILTER_VALIDATE_INT);
$sessionId = filter_var($input['session_id'] ?? null, FILTER_VALIDATE_INT); 

if (!$resolutionId || !$sessionId) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Pola resolution_id oraz session_id (numeryczne) są wymagane.']);
}

$votingSession = VotingSession::findById($pdo, $sessionId);
if (!$votingSession) {
    sendJsonResponse(404, ['status' => 'error', 'message' => "Sesja głosowania o ID {$sessionId} nie została znaleziona."]);
}
// Ważne: pozwalamy modyfikować statusy uchwał tylko jeśli główna sesja jest OTWARTA.
// Jeśli sesja jest zamknięta, statusy uchwał nie powinny być już zmieniane.
if ($votingSession->status !== 'open') {
    sendJsonResponse(403, ['status' => 'error', 'message' => 'Nie można zmodyfikować statusu uchwały. Główna sesja nie jest otwarta.']);
}

$resolution = Resolution::findById($pdo, $resolutionId);
if (!$resolution || $resolution->session_id !== $votingSession->session_id) {
    sendJsonResponse(404, ['status' => 'error', 'message' => "Uchwała o ID {$resolutionId} nie została znaleziona lub nie należy do tej sesji."]);
}

if ($resolution->voting_status === 'closed') {
    sendJsonResponse(200, ['status' => 'info', 'message' => 'Głosowanie nad tą uchwałą jest już zakończone/pominięte.']);
}

// updateVotingStatus zmieni status na 'closed'
if ($resolution->updateVotingStatus('closed')) {
    $actionVerb = ($resolution->voting_status === 'pending' || $resolution->voting_status === 'active') ? 'Zakończono/Pominięto' : 'Zaktualizowano status na "zakończone" dla';
    sendJsonResponse(200, [
        'status' => 'success', 
        'message' => "{$actionVerb} głosowanie nad uchwałą: \"{$resolution->text}\".",
        'resolution_id' => $resolution->resolution_id,
        'voting_status' => 'closed'
    ]);
} else {
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się zakończyć/pominąć głosowania nad uchwałą. Sprawdź logi.']);
}
?>