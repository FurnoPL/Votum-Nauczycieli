<?php
// Plik: api/admin/start_resolution_voting.php
// Endpoint do rozpoczynania głosowania nad konkretną uchwałą.

header('Content-Type: application/json');

require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';      
require_once __DIR__ . '/../../includes/functions.php'; 
require_once __DIR__ . '/../../classes/Session.php';    
require_once __DIR__ . '/../../classes/Resolution.php'; 

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
$sessionId = filter_var($input['session_id'] ?? null, FILTER_VALIDATE_INT); // Potrzebne do weryfikacji

if (!$resolutionId || !$sessionId) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Pola resolution_id oraz session_id (numeryczne) są wymagane.']);
}

$votingSession = VotingSession::findById($pdo, $sessionId);
if (!$votingSession) {
    sendJsonResponse(404, ['status' => 'error', 'message' => "Sesja głosowania o ID {$sessionId} nie została znaleziona."]);
}
if ($votingSession->status !== 'open') {
    sendJsonResponse(403, ['status' => 'error', 'message' => 'Nie można rozpocząć głosowania nad uchwałą. Główna sesja nie jest otwarta.']);
}

$resolution = Resolution::findById($pdo, $resolutionId);
if (!$resolution || $resolution->session_id !== $votingSession->session_id) {
    sendJsonResponse(404, ['status' => 'error', 'message' => "Uchwała o ID {$resolutionId} nie została znaleziona lub nie należy do tej sesji."]);
}

if ($resolution->voting_status === 'active') {
    sendJsonResponse(200, ['status' => 'info', 'message' => 'Głosowanie nad tą uchwałą jest już aktywne.']);
}
if ($resolution->voting_status === 'closed') {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Głosowanie nad tą uchwałą zostało już zakończone i nie można go ponownie otworzyć.']);
}

// updateVotingStatus w Resolution dba o to, by tylko jedna uchwała była 'active' w sesji
if ($resolution->updateVotingStatus('active')) {
    sendJsonResponse(200, [
        'status' => 'success', 
        'message' => "Rozpoczęto głosowanie nad uchwałą: \"{$resolution->text}\".",
        'resolution_id' => $resolution->resolution_id,
        'voting_status' => 'active'
    ]);
} else {
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się rozpocząć głosowania nad uchwałą. Sprawdź logi.']);
}
?>