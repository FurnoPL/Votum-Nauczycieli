<?php
// Plik: includes/auth.php

if (session_status() === PHP_SESSION_NONE) {
    // Nie startuj tutaj, pozwól skryptom decydować
}

require_once __DIR__ . '/functions.php'; // Dla sendJsonResponse

function isUserLoggedIn(): bool { // Dla admina
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

function isAdmin(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isUserLoggedIn() && $_SESSION['user_role'] === 'admin';
}

function requireAdminLogin(): void {
    if (!isUserLoggedIn()) { 
        sendJsonResponse(401, ['status' => 'error', 'message' => 'Brak autoryzacji. Wymagane logowanie administratora.']);
    }
    if (!isAdmin()) {
        sendJsonResponse(403, ['status' => 'error', 'message' => 'Brak uprawnień. Wymagana rola administratora.']);
    }
}

// Sprawdza, czy sesja PHP dołączyła do sesji głosowania
function isParticipantInVotingSession(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    // Kluczowe są te dwie zmienne sesyjne
    return isset($_SESSION['current_voting_session_id']) && isset($_SESSION['current_php_session_id_for_voting']);
}

function requireParticipantVotingSession(): void {
    if (!isParticipantInVotingSession()) {
        sendJsonResponse(403, ['status' => 'error', 'message' => 'Nie dołączyłeś do żadnej sesji głosowania lub sesja wygasła.', 'action' => 'redirect_to_join']);
    }
}

function getLoggedInUserId(): ?int { // Dla admina
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function getCurrentVotingSessionId(): ?int { // ID sesji głosowania, do której dołączyła sesja PHP
    if (session_status() === PHP_SESSION_NONE) session_start();
    return isset($_SESSION['current_voting_session_id']) ? (int)$_SESSION['current_voting_session_id'] : null;
}

function getCurrentPhpSessionIdForVoting(): ?string { // ID sesji PHP (przeglądarki)
    if (session_status() === PHP_SESSION_NONE) session_start();
    return $_SESSION['current_php_session_id_for_voting'] ?? null;
}
?>