<?php
// Plik: admin/get_session_results.php
// Endpoint do pobierania wyników (dla zamkniętej sesji) lub postępów (dla otwartej sesji).

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Session.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireAdmin();

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

if ($votingSession->status === 'open') {
    // Sesja jest otwarta, pokaż postępy
    $progressData = $votingSession->getSessionProgress();
    if ($progressData === false) {
        sendJsonResponse(500, ['status' => 'error', 'message' => "Nie udało się pobrać postępów dla sesji o ID {$sessionId}."]);
    }
    sendJsonResponse(200, [
        'status' => 'success',
        'type' => 'progress', // Dodajemy typ odpowiedzi
        'message' => "Postępy dla otwartej sesji o ID {$sessionId}.",
        'data' => $progressData
    ]);
} elseif ($votingSession->status === 'closed') {
    // Sesja jest zamknięta, pokaż wyniki
    $resultsData = $votingSession->getResults();
    if ($resultsData === false) {
        sendJsonResponse(500, ['status' => 'error', 'message' => "Nie udało się pobrać wyników dla sesji o ID {$sessionId}."]);
    }
    sendJsonResponse(200, [
        'status' => 'success',
        'type' => 'results', // Dodajemy typ odpowiedzi
        'message' => "Wyniki dla zamkniętej sesji o ID {$sessionId}.",
        'data' => $resultsData
    ]);
} else {
    // Inny, nieoczekiwany status
    sendJsonResponse(500, ['status' => 'error', 'message' => "Sesja ma nieobsługiwany status: {$votingSession->status}."]);
}
?>