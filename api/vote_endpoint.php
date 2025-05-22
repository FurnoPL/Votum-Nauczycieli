<?php
// Plik: api/vote_endpoint.php 
// Endpoint do oddawania głosów

header('Content-Type: application/json');

// Ścieżki względne do /api/
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';      // Dla requireLogin, getLoggedInUserId
require_once __DIR__ . '/../includes/functions.php'; // Dla sendJsonResponse
require_once __DIR__ . '/../classes/Session.php';    // Dla VotingSession::findById
require_once __DIR__ . '/../classes/Participant.php';// Dla Participant::findByUserAndSession
require_once __DIR__ . '/../classes/Resolution.php'; // Dla weryfikacji, czy rezolucja należy do sesji
require_once __DIR__ . '/../classes/Vote.php';       // Dla Vote::isValidChoice, Vote::castOrUpdateVote

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin(); // Użytkownik musi być zalogowany

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano POST.']);
}

// Sprawdź, czy ID sesji i ID uczestnika są w sesji PHP
if (!isset($_SESSION['current_voting_session_id']) || !isset($_SESSION['current_participant_id'])) {
    sendJsonResponse(403, ['status' => 'error', 'message' => 'Nie jesteś aktualnie w żadnej sesji głosowania. Dołącz najpierw do sesji.']);
}

$currentSessionIdFromPHP = (int)$_SESSION['current_voting_session_id'];
$currentParticipantIdFromPHP = (int)$_SESSION['current_participant_id'];

// Odczytaj dane z ciała żądania
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Nieprawidłowy format JSON.']);
}

// Oczekujemy resolution_id i choice. Session_id jest pobierane z sesji PHP.
$resolutionId = filter_var($input['resolution_id'] ?? null, FILTER_VALIDATE_INT);
$choice = trim($input['choice'] ?? '');

if (!$resolutionId || empty($choice)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Pola resolution_id (numeryczne) oraz choice są wymagane.']);
}

if (!Vote::isValidChoice($choice)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Nieprawidłowa wartość dla pola choice. Dozwolone: ' . implode(', ', Vote::getAllowedChoices()) . '.']);
}

// Sprawdź, czy sesja nadal istnieje i jest otwarta
$votingSession = VotingSession::findById($pdo, $currentSessionIdFromPHP);

if (!$votingSession) {
    sendJsonResponse(404, ['status' => 'error', 'message' => "Sesja głosowania (ID: {$currentSessionIdFromPHP}) nie istnieje lub została usunięta."]);
}
if ($votingSession->status !== 'open') {
    sendJsonResponse(403, ['status' => 'error', 'message' => 'Nie można głosować. Sesja nie jest otwarta.']);
}

// Weryfikacja, czy podana rezolucja należy do tej sesji
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

// Participant ID jest już zweryfikowane z sesji PHP ($currentParticipantIdFromPHP)
// Nie ma potrzeby ponownego pobierania obiektu Participant, chyba że chcemy zweryfikować,
// czy user_id z sesji PHP pasuje do participant_id, ale to już załatwił join_session_endpoint.

$voteHandler = new Vote($pdo);
// Metoda castOrUpdateVote jest bardziej odpowiednia niż castVote, jeśli zezwalamy na zmianę głosu
$savedOrUpdatedVote = $voteHandler->castOrUpdateVote($resolutionId, $currentParticipantIdFromPHP, $choice);

if ($savedOrUpdatedVote) {
    // Zwracamy zaktualizowane dane dla tej konkretnej rezolucji w kontekście tego uczestnika
    // To pomoże JS odświeżyć tylko ten fragment UI
    sendJsonResponse(200, [ 
        'status' => 'success',
        'message' => "Głos został pomyślnie zapisany/zaktualizowany.",
        'data' => [
            'vote_id' => $savedOrUpdatedVote->vote_id,
            'resolution_id' => $savedOrUpdatedVote->resolution_id,
            'participant_id' => $savedOrUpdatedVote->participant_id, // Zwróć dla spójności
            'choice' => $savedOrUpdatedVote->choice,
            'voted_at' => $savedOrUpdatedVote->voted_at
        ]
    ]);
} else {
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się zapisać/zaktualizować głosu. Sprawdź logi serwera.']);
}
?>