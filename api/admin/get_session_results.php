<?php
// Plik: api/admin/get_session_results.php

header('Content-Type: application/json');
// ... (require_once bez zmian) ...
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/auth.php';      
require_once __DIR__ . '/../../includes/functions.php'; 
require_once __DIR__ . '/../../classes/Session.php';    
require_once __DIR__ . '/../../classes/Resolution.php'; 
require_once __DIR__ . '/../../classes/Vote.php';       


if (session_status() === PHP_SESSION_NONE) { session_start(); }
requireAdminLogin(); 
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano GET.']); }
$sessionId = filter_var($_GET['session_id'] ?? null, FILTER_VALIDATE_INT);
if (!$sessionId) { sendJsonResponse(400, ['status' => 'error', 'message' => 'Parametr URL session_id (numeryczny) jest wymagany.']); }
$votingSession = VotingSession::findById($pdo, $sessionId);
if (!$votingSession) { sendJsonResponse(404, ['status' => 'error', 'message' => "Sesja o ID {$sessionId} nie została znaleziona."]); }

$fullSessionPublicData = $votingSession->getPublicData(); 

// Sprawdź, czy wszystkie uchwały są zamknięte
$allResolutionsClosed = true;
if (isset($fullSessionPublicData['resolutions']) && count($fullSessionPublicData['resolutions']) > 0) {
    foreach ($fullSessionPublicData['resolutions'] as $resolution) {
        if ($resolution['voting_status'] !== 'closed') {
            $allResolutionsClosed = false;
            break;
        }
    }
} else {
    $allResolutionsClosed = false; // Jeśli nie ma uchwał, to nie są "wszystkie zamknięte" w kontekście wyświetlania wyników
}


if ($votingSession->status === 'open' && !$allResolutionsClosed) { // SESJA OTWARTA I NIE WSZYSTKIE UCHWAŁY ZAMKNIĘTE -> POKAŻ POSTĘP
    $progressData = $votingSession->getSessionProgress();
    if ($progressData === false) {
        sendJsonResponse(500, ['status' => 'error', 'message' => "Nie udało się pobrać postępów dla sesji o ID {$sessionId}."]);
    }
    sendJsonResponse(200, [
        'status' => 'success',
        'type' => 'progress',
        'message' => "Postępy dla otwartej sesji o ID {$sessionId}.",
        'session_info' => $fullSessionPublicData, 
        'progress_details' => $progressData 
    ]);
} elseif ($votingSession->status === 'closed' || ($votingSession->status === 'open' && $allResolutionsClosed)) { // SESJA ZAMKNIĘTA LUB OTWARTA ALE WSZYSTKIE UCHWAŁY ZAMKNIĘTE -> POKAŻ WYNIKI
    $resultsData = $votingSession->getResults(); 
    if ($resultsData === false) {
        sendJsonResponse(500, ['status' => 'error', 'message' => "Nie udało się pobrać wyników dla sesji o ID {$sessionId}."]);
    }
    sendJsonResponse(200, [
        'status' => 'success',
        'type' => 'results', // Zawsze zwracaj typ 'results' w tym przypadku
        'message' => "Wyniki dla sesji o ID {$sessionId}.",
        'data' => $resultsData 
    ]);
} else { // Inne, nieoczekiwane stany (np. sesja open, brak uchwał)
    sendJsonResponse(500, [
        'status' => 'error', 
        'message' => "Sesja ma nieobsługiwany lub niekompletny stan: {$votingSession->status}.",
        'session_info' => $fullSessionPublicData
    ]);
}
?>