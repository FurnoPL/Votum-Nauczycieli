<?php
// Plik: api/vote_endpoint.php 
// Endpoint do oddawania głosów przez anonimowego uczestnika

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
// require_once __DIR__ . '/../includes/auth.php'; // Nie używamy tu już funkcji z auth.php bezpośrednio
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../classes/Session.php';    
// Participant.php nie jest potrzebny
require_once __DIR__ . '/../classes/Resolution.php'; 
require_once __DIR__ . '/../classes/Vote.php';       

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprawdź, czy sesja PHP dołączyła do sesji głosowania
if (!isset($_SESSION['current_voting_session_id']) || !isset($_SESSION['current_php_session_id_for_voting'])) {
    sendJsonResponse(403, ['status' => 'error', 'message' => 'Nie dołączyłeś do żadnej sesji głosowania lub sesja wygasła.', 'action' => 'redirect_to_join']);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano POST.']);
}

$currentSessionIdFromPHP = (int)$_SESSION['current_voting_session_id'];
$currentSessionPhpIdForVote = $_SESSION['current_php_session_id_for_voting']; // ZMIANA

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Nieprawidłowy format JSON.']);
}

$resolutionId = filter_var($input['resolution_id'] ?? null, FILTER_VALIDATE_INT);
$choice = trim($input['choice'] ?? '');

if (!$resolutionId || empty($choice)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Pola resolution_id (numeryczne) oraz choice są wymagane.']);
}

if (!Vote::isValidChoice($choice)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Nieprawidłowa wartość dla pola choice. Dozwolone: ' . implode(', ', Vote::getAllowedChoices()) . '.']);
}

$votingSession = VotingSession::findById($pdo, $currentSessionIdFromPHP);

if (!$votingSession) {
    sendJsonResponse(404, ['status' => 'error', 'message' => "Sesja głosowania (ID: {$currentSessionIdFromPHP}) nie istnieje lub została usunięta.", 'action' => 'redirect_to_join']);
}
if ($votingSession->status !== 'open') {
    sendJsonResponse(403, ['status' => 'error', 'message' => 'Nie można głosować. Sesja nie jest otwarta.', 'action' => 'redirect_to_join']);
}

$resolutionFoundInSession = false;
foreach ($votingSession->getResolutions() as $sessionResolution) {
    if ($sessionResolution->resolution_id === $resolutionId) {
        $resolutionFoundInSession = true;
        break;
    }
}
if (!$resolutionFoundInSession) {
    sendJsonResponse(404, ['status' => 'error', 'message' => "Rezolucja o ID {$resolutionId} nie należy do sesji o ID {$currentSessionIdFromPHP} lub nie istnieje."]);
}

$voteHandler = new Vote($pdo);
$savedOrUpdatedVote = $voteHandler->castOrUpdateVote($resolutionId, $currentSessionPhpIdForVote, $choice); // ZMIANA

if ($savedOrUpdatedVote) {
    sendJsonResponse(200, [ 
        'status' => 'success',
        'message' => "Głos został pomyślnie zapisany/zaktualizowany.",
        'data' => [ // Zwracane dane pozostają podobne, ale participant_id to teraz session_php_id
            'vote_id' => $savedOrUpdatedVote->vote_id,
            'resolution_id' => $savedOrUpdatedVote->resolution_id,
            'session_php_id' => $savedOrUpdatedVote->session_php_id, 
            'choice' => $savedOrUpdatedVote->choice,
            'voted_at' => $savedOrUpdatedVote->voted_at
        ]
    ]);
} else {
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się zapisać/zaktualizować głosu. Sprawdź logi serwera.']);
}
?>