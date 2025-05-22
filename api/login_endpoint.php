<?php
// Plik: api/login_endpoint.php
// Endpoint do obsługi logowania użytkownika.

header('Content-Type: application/json');

// Ścieżki są względne do lokalizacji tego pliku (api/)
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../includes/functions.php'; // Dla sendJsonResponse

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User($pdo); // $pdo jest z db.php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);

    if ($input === null && json_last_error() !== JSON_ERROR_NONE) {
        sendJsonResponse(400, ['status' => 'error', 'message' => 'Nieprawidłowy format JSON.']);
        exit;
    }

    $email = $input['email'] ?? '';
    $password = $input['password'] ?? '';

    if (empty($email) || empty($password)) {
        sendJsonResponse(400, ['status' => 'error', 'message' => 'Email i hasło są wymagane.']);
        exit;
    }

    $loggedInUser = $user->login($email, $password);

    if ($loggedInUser) {
        $_SESSION['user_id'] = $loggedInUser['user_id'];
        $_SESSION['user_name'] = $loggedInUser['name'];
        $_SESSION['user_email'] = $loggedInUser['email'];
        $_SESSION['user_role'] = $loggedInUser['role'];

        sendJsonResponse(200, [
            'status' => 'success',
            'message' => 'Logowanie pomyślne.',
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'role' => $_SESSION['user_role']
            ],
            'redirectUrl' => $loggedInUser['role'] === 'admin' ? 'admin_dashboard.php' : 'join_session.php' 
        ]);
    } else {
        sendJsonResponse(401, ['status' => 'error', 'message' => 'Nieprawidłowy email lub hasło.']);
    }
} else {
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Oczekiwano POST.']);
}
?>