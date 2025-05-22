<?php
// Plik: api/get_session_for_voting.php
// Endpoint do pobierania danych aktualnej sesji głosowania dla anonimowego uczestnika.

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php'; // Dla funkcji pomocniczych, jeśli jeszcze jakieś są używane
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Session.php'; // VotingSession
// Vote.php jest potrzebny, bo VotingSession->getPublicData go używa do sprawdzania głosu dla session_php_id

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprawdź, czy sesja PHP dołączyła do sesji głosowania
if (!isset($_SESSION['current_voting_session_id']) || !isset($_SESSION['current_php_session_id_for_voting'])) {
    sendJsonResponse(403, [
        'status' => 'error', 
        'message' => 'Nie dołączyłeś do żadnej sesji głosowania lub sesja wygasła.',
        'action' => 'redirect_to_join' 
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano GET.']);
}

$sessionId = (int)$_SESSION['current_voting_session_id'];
// participantId nie jest już używane w ten sposób, session_php_id jest w sesji

$votingSession = VotingSession::findById($pdo, $sessionId);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php'; 
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Session.php'; 
require_once __DIR__ . '/../classes/Vote.php'; // Potrzebne dla getPublicData

requireParticipantVotingSession(); 
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano GET.']); }
$sessionId = getCurrentVotingSessionId();
if (!$sessionId) { sendJsonResponse(403, ['status' => 'error', 'message' => 'Brak ID sesji głosowania w sesji PHP.', 'action' => 'redirect_to_join']); }
$votingSession = VotingSession::findById($pdo, $sessionId);
// ... (obsługa błędów dla $votingSession i statusu sesji bez zmian) ...
if (!$votingSession) {
    unset($_SESSION['current_voting_session_id']); unset($_SESSION['current_php_session_id_for_voting']); unset($_SESSION['current_session_code']);
    sendJsonResponse(404, ['status' => 'error', 'message' => "Sesja głosowania (ID: {$sessionId}) nie została znaleziona.", 'action' => 'redirect_to_join']);
}
if ($votingSession->status !== 'open') {
    $message = $votingSession->status === 'closed' ? 'Sesja głosowania została całkowicie zamknięta.' : 'Sesja głosowania nie jest już otwarta.';
    sendJsonResponse(403, ['status' => 'info', 'message' => $message, 'session_status' => $votingSession->status, 'action' => 'redirect_to_join']);
}


// Pobierz pełne dane sesji, w tym WSZYSTKIE uchwały
$fullSessionData = $votingSession->getPublicData(); 

// Przefiltruj uchwały - pokaż tylko 'active' lub 'closed'
$visibleResolutions = [];
if (isset($fullSessionData['resolutions']) && is_array($fullSessionData['resolutions'])) {
    foreach ($fullSessionData['resolutions'] as $resolution) {
        if ($resolution['voting_status'] === 'active' || $resolution['voting_status'] === 'closed') {
            $visibleResolutions[] = $resolution;
        }
    }
}
$fullSessionData['resolutions'] = $visibleResolutions; // Zastąp listę uchwał przefiltrowaną


$responseData = [
    'display_identifier' => 'Uczestnik ' . substr(($_SESSION['current_php_session_id_for_voting'] ?? session_id()), 0, 8),
    'session' => $fullSessionData // Zwróć zmodyfikowane dane sesji
];

sendJsonResponse(200, [
    'status' => 'success',
    'message' => 'Dane sesji głosowania załadowane.',
    'data' => $responseData
]);
?>