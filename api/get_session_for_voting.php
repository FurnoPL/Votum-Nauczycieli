<?php
// Plik: api/get_session_for_voting.php
// Endpoint do pobierania danych aktualnej sesji głosowania dla zalogowanego uczestnika.

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Session.php';
// Participant.php i Vote.php są potrzebne, bo VotingSession->getPublicData ich używa

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireLogin(); // Użytkownik musi być zalogowany

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano GET.']);
}

// Sprawdź, czy ID sesji i ID uczestnika są w sesji PHP
if (!isset($_SESSION['current_voting_session_id']) || !isset($_SESSION['current_participant_id'])) {
    sendJsonResponse(403, [
        'status' => 'error', 
        'message' => 'Nie jesteś aktualnie w żadnej sesji głosowania. Dołącz najpierw do sesji.',
        'action' => 'redirect_to_join' // Wskazówka dla JS
    ]);
}

$sessionId = (int)$_SESSION['current_voting_session_id'];
$participantId = (int)$_SESSION['current_participant_id'];

$votingSession = VotingSession::findById($pdo, $sessionId);

if (!$votingSession) {
    // To nie powinno się zdarzyć, jeśli ID sesji jest poprawne w sesji PHP
    unset($_SESSION['current_voting_session_id']); // Wyczyść niepoprawne dane
    unset($_SESSION['current_participant_id']);
    unset($_SESSION['current_session_code']);
    sendJsonResponse(404, [
        'status' => 'error', 
        'message' => "Sesja głosowania (ID: {$sessionId}) nie została znaleziona. Spróbuj dołączyć ponownie.",
        'action' => 'redirect_to_join'
    ]);
}

if ($votingSession->status !== 'open') {
    $message = $votingSession->status === 'closed' ? 'Sesja głosowania została zamknięta.' : 'Sesja głosowania nie jest już otwarta.';
    // Można by pozwolić na oglądanie swoich głosów w zamkniętej sesji, ale na razie prościej
    sendJsonResponse(403, [
        'status' => 'info', // Użyjemy info, bo to nie błąd aplikacji, a zmiana stanu sesji
        'message' => $message,
        'session_status' => $votingSession->status,
        'action' => $votingSession->status === 'closed' ? 'show_results_or_redirect' : 'redirect_to_join'
        // Tutaj można by przekierować do strony z wynikami (jeśli istnieje) lub join_session
    ]);
}

// Pobierz dane sesji z perspektywy uczestnika
$sessionData = $votingSession->getPublicData($participantId);

// Dodatkowe dane o użytkowniku i uczestniku, jeśli potrzebne
$responseData = [
    'user_name' => $_SESSION['user_name'] ?? 'Uczestnik',
    'participant_id' => $participantId, // Dla pewności, jeśli JS będzie go potrzebował
    'session' => $sessionData
];

sendJsonResponse(200, [
    'status' => 'success',
    'message' => 'Dane sesji głosowania załadowane.',
    'data' => $responseData
]);
?>