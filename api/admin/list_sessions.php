<?php
// Plik: api/admin/list_sessions.php
// Endpoint do listowania wszystkich sesji dla administratora.

header('Content-Type: application/json');

// Ścieżki są względne do api/admin/
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';      // requireAdmin()
require_once __DIR__ . '/../../includes/functions.php'; // sendJsonResponse()
require_once __DIR__ . '/../../classes/Session.php';    // VotingSession

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireAdmin(); // Tylko administrator może listować sesje

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano GET.']);
}

// Można dodać parametry filtrowania np. ?status=open lub ?status=closed
$filterStatus = $_GET['status'] ?? null; // np. 'open', 'closed', lub null dla wszystkich
$sessionsData = [];
$sessions = []; // Inicjalizacja tablicy

// Zakładamy, że VotingSession ma metody getActiveSessions, getClosedSessions, getAllSessions
if ($filterStatus === 'open') {
    $sessions = VotingSession::getActiveSessions($pdo);
} elseif ($filterStatus === 'closed') {
    $sessions = VotingSession::getClosedSessions($pdo);
} else {
    // Domyślnie (lub jeśli $filterStatus jest inny niż 'open'/'closed') pobierz wszystkie
    // Możesz zmodyfikować tę logikę, jeśli chcesz inaczej obsługiwać nieznane filtry
    $sessions = VotingSession::getAllSessions($pdo); 
}


foreach ($sessions as $session) {
    // Używamy getPublicData bez participantId, bo to lista dla admina
    $sessionDetails = $session->getPublicData(); 
    // Dodatkowe informacje, które mogą być przydatne dla admina
    $sessionDetails['created_by_user_id'] = $session->created_by; 
    // Można by dodać liczbę uczestników, jeśli metoda getPublicData tego nie zwraca a jest potrzebne
    // $sessionDetails['participants_count'] = count(Participant::getParticipantsBySession($pdo, $session->session_id));
    $sessionsData[] = $sessionDetails;
}

sendJsonResponse(200, [
    'status' => 'success',
    'message' => 'Lista sesji.',
    'filter' => $filterStatus, // Zwróć użyty filtr dla informacji
    'data' => $sessionsData,
    'count' => count($sessionsData)
]);
?>