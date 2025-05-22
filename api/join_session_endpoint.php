<?php
// Plik: api/join_session_endpoint.php 
// Endpoint dołączania do sesji głosowania przez nauczyciela

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';
// require_once __DIR__ . '/../includes/auth.php'; // Nie potrzebujemy już requireLogin stąd
require_once __DIR__ . '/../includes/functions.php'; 
require_once __DIR__ . '/../classes/Session.php';    
require_once __DIR__ . '/../classes/Participant.php';
require_once __DIR__ . '/../classes/User.php'; // Dla stworzenia "anonimowego" użytkownika-nauczyciela

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
$participantName = trim($input['participant_name'] ?? ''); // NOWE POLE

if (empty($sessionCode)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Kod sesji jest wymagany.']);
}
if (empty($participantName)) {
    sendJsonResponse(400, ['status' => 'error', 'message' => 'Twoje imię lub identyfikator jest wymagane.']);
}

$votingSession = VotingSession::findByCode($pdo, $sessionCode);

if (!$votingSession) {
    sendJsonResponse(404, ['status' => 'error', 'message' => 'Sesja o podanym kodzie nie istnieje.']);
}

if ($votingSession->status !== 'open') {
    $statusMessage = ($votingSession->status === 'closed') ? 'Sesja jest już zamknięta.' : 'Sesja nie jest aktualnie otwarta.';
    sendJsonResponse(403, ['status' => 'error', 'message' => $statusMessage, 'session_status' => $votingSession->status]);
}

// Stwórz lub znajdź "użytkownika" dla tego nauczyciela.
// Użyjemy emaila jako unikalnego identyfikatora, generując go na podstawie imienia i kodu sesji,
// aby uniknąć konfliktów, jeśli to samo imię pojawi się w różnych sesjach.
// Role 'teacher' nie będą miały hasła i nie będą mogły się logować tradycyjnie.
// To uproszczenie. Można by też generować unikalny token dla nauczyciela i przechowywać go w sesji.

$userHandler = new User($pdo);
// Generujemy unikalny "email" dla tego uczestnika, aby móc go zapisać w tabeli users
// To jest uproszczenie, aby pasowało do istniejącej struktury Participant->user_id
// Można by rozważyć modyfikację tabeli participants, aby nie wymagała user_id
// lub przechowywać tylko participant_name bez tworzenia rekordu w users.
// Na razie, dla minimalnych zmian w strukturze:
$pseudoEmail = strtolower(str_replace(' ', '_', $participantName)) . '@session.' . $sessionCode . '.teacher';

$userId = null;
$existingUser = $userHandler->findByEmail($pseudoEmail);

if ($existingUser) {
    $userId = $existingUser['user_id'];
    // Można sprawdzić, czy rola to 'teacher', jeśli mamy bardziej złożony system
} else {
    // Utwórz nowego "użytkownika" typu teacher bez hasła (lub z losowym, nieużywanym)
    // Klasa User wymaga hasła, więc podamy jakieś losowe.
    $randomPasswordForTeacher = bin2hex(random_bytes(16)); 
    $newUserId = $userHandler->create($participantName, $pseudoEmail, $randomPasswordForTeacher, 'teacher');
    if ($newUserId) {
        $userId = $newUserId;
    } else {
        sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się zarejestrować uczestnika. Spróbuj ponownie.']);
    }
}

if (!$userId) {
     sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się zidentyfikować ani utworzyć uczestnika.']);
}


// Dołącz uczestnika (z user_id) do sesji
$participant = Participant::findByUserAndSession($pdo, $userId, $votingSession->session_id);
$message = 'Pomyślnie dołączono do sesji. Przekierowywanie do panelu głosowania...';
$isNewParticipant = false;

if (!$participant) {
    $participantHandler = new Participant($pdo);
    $newlyJoinedParticipant = $participantHandler->joinSession($userId, $votingSession->session_id);
    
    if (!$newlyJoinedParticipant) {
        $participant = Participant::findByUserAndSession($pdo, $userId, $votingSession->session_id);
        if (!$participant) {
             sendJsonResponse(500, ['status' => 'error', 'message' => 'Nie udało się dołączyć do sesji. Spróbuj ponownie później.']);
        }
        $message = 'Już jesteś uczestnikiem tej sesji (dołączono w międzyczasie). Przekierowywanie...';
    } else {
        $participant = $newlyJoinedParticipant;
        $isNewParticipant = true;
    }
} else {
    $message = 'Już jesteś uczestnikiem tej sesji. Przekierowywanie do panelu głosowania...';
}

if ($participant && $participant->participant_id) {
    $_SESSION['current_voting_session_id'] = $votingSession->session_id;
    $_SESSION['current_participant_id'] = $participant->participant_id;
    $_SESSION['current_session_code'] = $sessionCode;
    $_SESSION['current_participant_name'] = $participantName; // Zapisz imię dla wyświetlania

    sendJsonResponse(200, [
        'status' => 'success',
        'message' => $message,
        'is_new_participant' => $isNewParticipant,
        'data' => $votingSession->getPublicData($participant->participant_id),
        'redirectUrl' => 'vote.php'
    ]);
} else {
    sendJsonResponse(500, ['status' => 'error', 'message' => 'Wystąpił nieoczekiwany błąd podczas przetwarzania dołączenia do sesji.']);
}
?>