<?php
// Plik: api/admin/get_session_results.php
// Endpoint do pobierania wyników (dla zamkniętej sesji) lub postępów (dla otwartej sesji).

header('Content-Type: application/json');

// Ścieżki względne do /api/admin/
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';      // requireAdmin()
require_once __DIR__ . '/../../includes/functions.php'; // sendJsonResponse()
require_once __DIR__ . '/../../classes/Session.php';    // VotingSession
// Klasy Participant i Vote mogą być potrzebne, jeśli getSessionProgress lub getResults ich używają

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireAdminLogin(); // Tylko administrator może pobierać wyniki/postępy

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano GET.']);
}

$sessionId = filter_var($_GET['session_id'] ?? null, FILTER_VALIDATE_INT);

if (!$sessionId) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Parametr URL session_id (numeryczny) jest wymagany.']);
}

$votingSession = VotingSession::findById($pdo, $sessionId);

if (!$votingSession) {
    sendJsonResponse(404, ['status' => 'error', 'message' => "Sesja o ID {$sessionId} nie została znaleziona."]);
}

// Przygotuj podstawowe informacje o sesji do zwrócenia zawsze
$sessionBaseInfo = [
    'session_id' => $votingSession->session_id,
    'title' => $votingSession->title,
    'code' => $votingSession->code,
    'status' => $votingSession->status,
    'created_at' => $votingSession->created_at,
];


if ($votingSession->status === 'open') {
    // Sesja jest otwarta, pokaż postępy
    $progressData = $votingSession->getSessionProgress(); // Metoda zdefiniowana w VotingSession
    if ($progressData === false) {
        sendJsonResponse(500, ['status' => 'error', 'message' => "Nie udało się pobrać postępów dla sesji o ID {$sessionId}."]);
    }
    sendJsonResponse(200, [
        'status' => 'success',
        'type' => 'progress', // Dodajemy typ odpowiedzi
        'message' => "Postępy dla otwartej sesji o ID {$sessionId}.",
        'session_info' => $sessionBaseInfo,
        'data' => $progressData 
    ]);
} elseif ($votingSession->status === 'closed') {
    // Sesja jest zamknięta, pokaż wyniki
    $resultsData = $votingSession->getResults(); // Metoda zdefiniowana w VotingSession
    if ($resultsData === false) {
        sendJsonResponse(500, ['status' => 'error', 'message' => "Nie udało się pobrać wyników dla sesji o ID {$sessionId}."]);
    }
    // getResults() już zwraca informacje o sesji, więc nie musimy dublować $sessionBaseInfo,
    // chyba że chcemy ujednolicić strukturę odpowiedzi. 
    // Dla spójności, załóżmy, że dane sesji są w $resultsData['session_info'] lub bezpośrednio w $resultsData
    // Twój obecny VotingSession::getResults() zwraca pełne dane sesji w głównym obiekcie.
    sendJsonResponse(200, [
        'status' => 'success',
        'type' => 'results', // Dodajemy typ odpowiedzi
        'message' => "Wyniki dla zamkniętej sesji o ID {$sessionId}.",
        // 'session_info' => $sessionBaseInfo, // Można usunąć, jeśli $resultsData już to zawiera
        'data' => $resultsData // $resultsData to obiekt z 'session_id', 'title', 'resolutions_results' etc.
    ]);
} else {
    // Inny, nieoczekiwany status
    sendJsonResponse(500, [
        'status' => 'error', 
        'message' => "Sesja ma nieobsługiwany status: {$votingSession->status}.",
        'session_info' => $sessionBaseInfo
    ]);
}
?>