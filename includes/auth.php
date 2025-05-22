<?php
// Plik: includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    // Nie startuj tutaj, pozwól skryptom decydować
    // session_start(); 
}

require_once __DIR__ . '/functions.php'; // Dla sendJsonResponse

function isUserLoggedIn(): bool {
    // Sesja musi być aktywna, aby to sprawdzić
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isUserLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function requireLogin(): void {
    if (!isUserLoggedIn()) { // isUserLoggedIn samo uruchomi sesję, jeśli trzeba
        sendJsonResponse(401, ['status' => 'error', 'message' => 'Brak autoryzacji. Wymagane logowanie.']);
        // exit w sendJsonResponse
    }
}

function requireAdmin(): void {
    // requireLogin() już sprawdzi sesję i zalogowanie
    requireLogin(); 
    // isAdmin() też sprawdzi sesję
    if (!isAdmin()) {
        sendJsonResponse(403, ['status' => 'error', 'message' => 'Brak uprawnień. Wymagana rola administratora.']);
        // exit w sendJsonResponse
    }
}

function getLoggedInUserId(): ?int {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}
?>