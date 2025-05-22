<?php
// Plik: api/join_session_endpoint.php 
// Endpoint dołączania do sesji głosowania przez nauczyciela (ANONIMOWO)

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
// require_once __DIR__ . '/../includes/auth.php'; // auth.php nie jest już tak potrzebne tutaj
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../classes/Session.php';    
// Participant.php i User.php nie są już potrzebne tutaj

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano POST.']);
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE);

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Nieprawidłowy format JSON.']);
}

$sessionCode = trim($input['session_code'] ?? '');
// Usunięto participant_name, bo jest anonimowe

if (empty($sessionCode)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Kod sesji jest wymagany.']);
}

$votingSession = VotingSession::findByCode($pdo, $sessionCode);

if (!$votingSession) {
    sendJsonResponse(404, ['status' => 'error', 'message' => 'Sesja o podanym kodzie nie istnieje.']);
}

if ($votingSession->status !== 'open') {
    $statusMessage = ($votingSession->status === 'closed') ? 'Sesja jest już zamknięta.' : 'Sesja nie jest aktualnie otwarta.';
    sendJsonResponse(403, ['status' => 'error', 'message' => $statusMessage, 'session_status' => $votingSession->status]);
}

// Zapisz informacje o dołączeniu do sesji PHP
// Klucz w $_SESSION będzie specyficzny dla danej sesji głosowania,
// aby jedna sesja przeglądarki mogła dołączyć do wielu sesji głosowania (choć nie jednocześnie w tym modelu)
$sessionParticipationKey = 'voting_participation_s' . $votingSession->session_id;

$alreadyJoined = isset($_SESSION[$sessionParticipationKey]);

$_SESSION[$sessionParticipationKey] = true; // Oznacz, że ta sesja PHP dołączyła
$_SESSION['current_voting_session_id'] = $votingSession->session_id; // ID sesji głosowania
$_SESSION['current_session_code'] = $sessionCode;
// Nie ma już participant_id ani participant_name do zapisania w sesji w ten sposób
// current_participant_id będzie teraz ID sesji PHP
$_SESSION['current_php_session_id_for_voting'] = session_id(); // Użyjemy tego jako identyfikatora głosującego


$message = $alreadyJoined ? 
    'Odświeżono status dołączenia do sesji. Przekierowywanie...' : 
    'Pomyślnie dołączono do sesji. Przekierowywanie do panelu głosowania...';

// getPublicData teraz nie przyjmuje argumentu
$sessionViewData = $votingSession->getPublicData(); 

sendJsonResponse(200, [
    'status' => 'success',
    'message' => $message,
    'data' => $sessionViewData, // Dane sesji z perspektywy anonimowego uczestnika
    'redirectUrl' => 'vote.php'
]);
?>