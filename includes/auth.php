<?php
// Plik: includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Upewnij się, że sesja jest aktywna
}

require_once __DIR__ . '/functions.php'; // Dla sendJsonResponse

/**
 * Sprawdza, czy użytkownik jest zalogowany.
 *
 * @return bool True jeśli zalogowany, false w przeciwnym razie.
 */
function isUserLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

/**
 * Sprawdza, czy zalogowany użytkownik ma rolę administratora.
 *
 * @return bool True jeśli jest administratorem, false w przeciwnym razie.
 */
function isAdmin(): bool {
    return isUserLoggedIn() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

/**
 * Wymusza zalogowanie. Jeśli użytkownik nie jest zalogowany,
 * wysyła odpowiedź 401 Unauthorized i kończy skrypt.
 */
function requireLogin(): void {
    if (!isUserLoggedIn()) {
        sendJsonResponse(401, ['status' => 'error', 'message' => 'Brak autoryzacji. Wymagane logowanie.']);
    }
}

/**
 * Wymusza rolę administratora. Jeśli użytkownik nie jest zalogowany
 * lub nie jest administratorem, wysyła odpowiednią odpowiedź
 * (401 lub 403) i kończy skrypt.
 */
function requireAdmin(): void {
    if (!isUserLoggedIn()) {
        sendJsonResponse(401, ['status' => 'error', 'message' => 'Brak autoryzacji. Wymagane logowanie.']);
    }
    if (!isAdmin()) {
        sendJsonResponse(403, ['status' => 'error', 'message' => 'Brak uprawnień. Wymagana rola administratora.']);
    }
}

/**
 * Pobiera ID zalogowanego użytkownika.
 *
 * @return int|null ID użytkownika lub null, jeśli nie jest zalogowany.
 */
function getLoggedInUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}
?>