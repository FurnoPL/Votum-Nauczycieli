<?php
// Plik: index.php
// Główny punkt wejścia - obsługa logowania i sprawdzania statusu sesji.

// Ustawienie Content-Type na JSON dla wszystkich odpowiedzi z tego skryptu
header('Content-Type: application/json');

// Wymagane pliki
require_once __DIR__ . '/includes/db.php';       // Połączenie z bazą $pdo
require_once __DIR__ . '/classes/User.php';      // Klasa User
require_once __DIR__ . '/includes/functions.php';// Dla sendJsonResponse

// Rozpocznij sesję PHP, jeśli jeszcze nie jest aktywna
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = new User($pdo); // Utwórz instancję klasy User

// Obsługa różnych metod HTTP
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    // --- OBSŁUGA LOGOWANIA ---
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE); // TRUE zwraca tablicę asocjacyjną

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
        // Logowanie pomyślne - zapisz dane w sesji
        $_SESSION['user_id'] = $loggedInUser['user_id']; // Zakładam, że login() zwraca tablicę z danymi użytkownika
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
            ]
        ]);
    } else {
        // Błąd logowania
        sendJsonResponse(401, ['status' => 'error', 'message' => 'Nieprawidłowy email lub hasło.']);
    }

} elseif ($method === 'GET') {
    // --- OBSŁUGA SPRAWDZANIA STATUSU SESJI ---
    if (isset($_SESSION['user_id'])) {
        // Użytkownik jest zalogowany
        sendJsonResponse(200, [
            'status' => 'success',
            'loggedIn' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'] ?? null, // Dodaj '?? null' dla bezpieczeństwa
                'email' => $_SESSION['user_email'] ?? null,
                'role' => $_SESSION['user_role'] ?? null
            ]
        ]);
    } else {
        // Użytkownik nie jest zalogowany
        sendJsonResponse(200, [ // Można też użyć 401, ale 200 z 'loggedIn: false' jest też popularne
            'status' => 'success',
            'loggedIn' => false,
            'message' => 'Użytkownik nie jest zalogowany.'
        ]);
    }

} else {
    // --- NIEWSPIERANA METODA HTTP ---
    sendJsonResponse(405, ['status' => 'error', 'message' => 'Niedozwolona metoda. Obsługiwane są tylko GET i POST.']);
}

?>