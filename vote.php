<?php
// Plik: vote.php - endpoint do oddawania głosów

header('Content-Type: application/json');

require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/Session.php';
require_once __DIR__ . '/classes/Participant.php';
require_once __DIR__ . '/classes/Resolution.php';
require_once __DIR__ . '/classes/Vote.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano POST.']);
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Nieprawidłowy format JSON.']);
}

$sessionId = filter_var($input['session_id'] ?? null, FILTER_VALIDATE_INT);
$resolutionId = filter_var($input['resolution_id'] ?? null, FILTER_VALIDATE_INT);
$choice = trim($input['choice'] ?? '');

if (!$sessionId || !$resolutionId || empty($choice)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Pola session_id (numeryczne), resolution_id (numeryczne) oraz choice są wymagane.']);
}

if (!Vote::isValidChoice($choice)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Nieprawidłowa wartość dla pola choice. Dozwolone: ' . implode(', ', Vote::getAllowedChoices()) . '.']);
}

$userId = getLoggedInUserId();
$votingSession = VotingSession::findById($pdo, $sessionId);

if (!$votingSession) {
    sendJsonResponse(404, ['status' => 'error', 'message' => "Sesja głosowania o ID {$sessionId} nie istnieje."]);
}
if ($votingSession->status !== 'open') {
    sendJsonResponse(403, ['status' => 'error', 'message' => 'Nie można głosować. Sesja nie jest otwarta.']);
}

$participant = Participant::findByUserAndSession($pdo, $userId, $sessionId);
if (!$participant) {
    sendJsonResponse(403, ['status' => 'error', 'message' => 'Nie jesteś uczestnikiem tej sesji głosowania.']);
}
$participantId = $participant->participant_id;

$resolutionFoundInSession = false;
foreach ($votingSession->getResolutions() as $sessionResolution) {
    if ($sessionResolution->resolution_id === $resolutionId) {
        $resolutionFoundInSession = true;
        break;
    }
}
if (!$resolutionFoundInSession) {
    sendJsonResponse(404, ['status' => 'error', 'message' => "Rezolucja o ID {$resolutionId} nie należy do sesji o ID {$sessionId} lub nie istnieje."]);
}

// Usunięto sprawdzanie istniejącego głosu tutaj, ponieważ klasa Vote sobie z tym poradzi (INSERT lub UPDATE)
// $existingVote = Vote::getVoteByParticipantAndResolution($pdo, $participantId, $resolutionId);
// if ($existingVote) { ... }

$voteHandler = new Vote($pdo);
// Używamy metody castVote, która teraz wewnętrznie obsługuje INSERT lub UPDATE
$savedOrUpdatedVote = $voteHandler->castVote($resolutionId, $participantId, $choice);

if ($savedOrUpdatedVote) {
    // Sprawdź, czy vote_id jest ustawione, aby odróżnić nowy od zaktualizowanego jeśli trzeba
    // Ale na razie ogólny sukces jest wystarczający
    $message = "Głos został pomyślnie zapisany/zaktualizowany.";
    // Status 200 OK, jeśli aktualizujemy, 201 Created, jeśli tworzymy nowy.
    // Można by to rozróżnić, ale 200 jest bezpieczne dla obu przypadków jako "sukces".
    // Jeśli chcemy być precyzyjni, musielibyśmy sprawdzić, czy $savedOrUpdatedVote->voted_at
    // różni się od czasu przed wywołaniem metody, lub czy vote_id było już znane.
    // Dla uproszczenia, użyjemy 200 OK.
    http_response_code(200); // Ustawiamy kod odpowiedzi na 200 OK
    sendJsonResponse(200, [ // Drugi argument funkcji sendJsonResponse to dane, kod jest ustawiany przez http_response_code
        'status' => 'success',
        'message' => $message,
        'data' => [
            'vote_id' => $savedOrUpdatedVote->vote_id,
            'resolution_id' => $savedOrUpdatedVote->resolution_id,
            'choice' => $savedOrUpdatedVote->choice,
            'voted_at' => $savedOrUpdatedVote->voted_at
        ]
    ]);
} else {
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się zapisać/zaktualizować głosu. Sprawdź logi serwera.']);
}
?>