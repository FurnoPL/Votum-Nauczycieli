<?php
// Plik: admin/list_sessions.php
// Endpoint do listowania wszystkich sesji dla administratora.

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';      // requireAdmin()
require_once __DIR__ . '/../includes/functions.php'; // sendJsonResponse()
require_once __DIR__ . '/../classes/Session.php';    // VotingSession

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireAdmin(); // Tylko administrator może listować sesje

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano GET.']);
}

// Można dodać parametry filtrowania np. ?status=open lub ?status=closed
$filterStatus = $_GET['status'] ?? null;
$sessionsData = [];

if ($filterStatus === 'open') {
    $sessions = VotingSession::getActiveSessions($pdo);
} elseif ($filterStatus === 'closed') {
    $sessions = VotingSession::getClosedSessions($pdo);
} else {
    // Domyślnie pobierz wszystkie sesje lub zwróć błąd jeśli filtr jest niepoprawny
    // Dla przykładu pobierzemy wszystkie
    $sessions = VotingSession::getAllSessions($pdo); // Załóżmy, że dodaliśmy tę metodę do VotingSession
}


foreach ($sessions as $session) {
    // Używamy getPublicData bez participantId, bo to lista dla admina, nie kontekst uczestnika
    // Można by stworzyć osobną metodę np. getAdminListData() w VotingSession jeśli potrzebne są inne pola
    $sessionDetails = $session->getPublicData(); 
    // Dodajmy created_by, bo admin może chcieć to wiedzieć
    $sessionDetails['created_by_user_id'] = $session->created_by;
    $sessionsData[] = $sessionDetails;
}

sendJsonResponse(200, [
    'status' => 'success',
    'message' => 'Lista sesji.',
    'data' => $sessionsData,
    'count' => count($sessionsData)
]);
?>