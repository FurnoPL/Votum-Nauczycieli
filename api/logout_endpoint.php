<?php
// Plik: api/logout_endpoint.php
// Endpoint do wylogowywania użytkownika.

header('Content-Type: application/json');

// functions.php jest potrzebne dla sendJsonResponse
require_once __DIR__ . '/../includes/functions.php'; 

// Rozpocznij sesję PHP, aby mieć do niej dostęp
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sprawdź, czy użytkownik był w ogóle zalogowany
$isLoggedIn = isset($_SESSION['user_id']);

// Usuń wszystkie zmienne sesyjne
$_SESSION = array();

// Jeśli używane są ciasteczka sesyjne, zaleca się również usunięcie ciasteczka.
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
    // Nawet jeśli nie był zalogowany, sesja została wyczyszczona, więc można to uznać za sukces
    // lub informację, że nie było czego wylogowywać.
    sendJsonResponse(200, ['status' => 'success', 'message' => 'Sesja wyczyszczona. Użytkownik nie był zalogowany lub sesja już wygasła.']);
}
?>