<?php
// Plik: api/create_session_endpoint.php

header('Content-Type: application/json');

// Ścieżki względne do /api/
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Session.php'; 
require_once __DIR__ . '/../classes/Resolution.php'; // VotingSession->create może jej potrzebować wewnętrznie

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

requireAdminLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano POST.']);
}

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, TRUE); 

if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Nieprawidłowy format JSON.']);
}

$title = trim($input['title'] ?? ''); 
$resolutionsTexts = $input['resolutions'] ?? null; 

if (empty($title)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Tytuł sesji jest wymagany.']);
}

if (!is_array($resolutionsTexts) || empty($resolutionsTexts)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Lista rezolucji (tablica tekstów) jest wymagana i nie może być pusta.']);
}

foreach ($resolutionsTexts as $resText) {
    if (!is_string($resText) || empty(trim($resText))) {
        sendJsonResponse(400, ['status' => 'error', 'message' => 'Każda rezolucja musi być niepustym ciągiem znaków.']);
    }
}

$creatorId = getLoggedInUserId();
if ($creatorId === null) {
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się zidentyfikować administratora.']);
}

$votingSession = new VotingSession($pdo);
$createdSession = $votingSession->create($title, $creatorId, $resolutionsTexts);

if ($createdSession) {
    $responseData = $createdSession->getPublicData(); 
    sendJsonResponse(201, [
        'status' => 'success', 
        'message' => 'Sesja głosowania została pomyślnie utworzona.', 
        'data' => $responseData,
        // ZMIANA TUTAJ:
        'redirectUrl' => 'results.php?session_id=' . $createdSession->session_id . '&created=true' 
    ]);
} else {
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się utworzyć sesji głosowania. Sprawdź logi serwera.']);
}
?>