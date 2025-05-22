<?php
// Plik: api/check_session.php
// Endpoint do sprawdzania statusu sesji użytkownika.

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php'; // Dla sendJsonResponse

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) { // Dodano sprawdzenie user_role
    sendJsonResponse(200, [
        'status' => 'success',
        'loggedIn' => true,
        'user' => [
            'id' => $_SESSION['user_id'],
            'name' => $_SESSION['user_name'] ?? null,
            'email' => $_SESSION['user_email'] ?? null,
            'role' => $_SESSION['user_role']
        ],
        'dashboardUrl' => ($_SESSION['user_role'] === 'admin') ? 'admin_dashboard.php' : 'join_session.php'
    ]);
} else {
    sendJsonResponse(200, [ // Nadal 200, bo to jest poprawna odpowiedź o stanie "niezalogowany"
        'status' => 'success', // Można by to zmienić na 'info' jeśli chcemy rozróżnić
        'loggedIn' => false,
        'message' => 'Użytkownik nie jest zalogowany.'
    ]);
}
?>