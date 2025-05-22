<?php
// Plik: logout.php
// Endpoint do wylogowywania użytkownika.

header('Content-Type: application/json');

require_once __DIR__ . '/includes/functions.php'; // Dla sendJsonResponse

// Rozpocznij sesję PHP, aby mieć do niej dostęp
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprawdź, czy użytkownik był w ogóle zalogowany
$isLoggedIn = isset($_SESSION['user_id']);

// Usuń wszystkie zmienne sesyjne
$_SESSION = array();

// Jeśli używane są ciasteczka sesyjne, zaleca się również usunięcie ciasteczka.
// Uwaga: To usunie ciasteczko, ale nie dane sesji na serwerze, jeśli sesja nie zostanie zniszczona.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Na koniec, zniszcz sesję.
session_destroy();

if ($isLoggedIn) {
    sendJsonResponse(200, ['status' => 'success', 'message' => 'Wylogowano pomyślnie.']);
} else {
    sendJsonResponse(200, ['status' => 'info', 'message' => 'Użytkownik nie był zalogowany lub sesja już wygasła.']);
}

?>