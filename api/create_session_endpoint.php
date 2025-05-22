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

requireAdmin(); 

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
    $responseData = $createdSession->getPublicData(); // Użyj getPublicData dla spójności
    /*
    $responseData = [ // Można też ręcznie budować, jeśli getPublicData daje za dużo/za mało
        'session_id' => $createdSession->session_id,
        'code' => $createdSession->code,
        'title' => $createdSession->title,
        'status' => $createdSession->status,
        'created_at' => $createdSession->created_at,
        'resolutions' => [] // Można dodać uproszczone dane uchwał, jeśli potrzebne od razu
    ];
    $resolutions = Resolution::getBySessionId($pdo, $createdSession->session_id);
    foreach ($resolutions as $res) {
        $responseData['resolutions'][] = ['resolution_id' => $res->resolution_id, 'text' => $res->text, 'number' => $res->number];
    }
    */
    sendJsonResponse(201, [
        'status' => 'success', 
        'message' => 'Sesja głosowania została pomyślnie utworzona.', 
        'data' => $responseData, // Zwróć pełniejsze dane sesji
        'redirectUrl' => 'admin_dashboard.php?session_created=' . $createdSession->session_id 
    ]);
} else {
    // Warto by było, gdyby $votingSession->create zwracało jakiś komunikat błędu
    // lub logowało go, aby można było łatwiej debugować.
    // Na razie ogólny komunikat:
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się utworzyć sesji głosowania. Sprawdź logi serwera.']);
}
?>