<?php
// Plik: admin_dashboard.php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Potrzebne dla header.php i potencjalnie dla danych użytkownika
}

// UWAGA: Poniższa blokada PHP jest drugą linią obrony. 
// Główna logika przekierowania niezalogowanych/nieadminów jest w JS (checkLoginStatusAndUpdateNav).
// Ale dobrze jest mieć to też po stronie serwera.
require_once __DIR__ . '/includes/auth.php'; // Dla isAdmin() i getLoggedInUserId()
if (!isUserLoggedIn()) {
    header('Location: index.php?reason=not_logged_in');
    exit;
}
if (!isAdmin()) {
    // Jeśli zalogowany, ale nie admin, można przekierować np. do join_session.php
    // lub wyświetlić błąd. Na razie przekierujmy do strony głównej z informacją.
    // W bardziej rozbudowanej aplikacji nauczyciel mógłby mieć swój panel.
    header('Location: index.php?reason=not_admin');
    exit;
}

$adminName = $_SESSION['user_name'] ?? 'Administrator'; // Pobierz imię admina z sesji

include 'includes/header.php'; 
?>

<div class="admin-dashboard-container">
    <h2>Panel Dyrektora</h2>
    <p>Witaj, <?php echo htmlspecialchars($adminName); ?>!</p>

    <div id="dashboard-message" class="alert d-none"></div>

    <div class="card mt-4">
        <div class="card-header">
            <h3>Szybkie Akcje</h3>
        </div>
        <div class="card-body btn-group">
            <a href="create_session.php" class="btn btn-success">Utwórz Nową Sesję Rady</a>
            <a href="history.php" class="btn btn-info">Zobacz Historię Głosowań</a>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h3>Aktywne Sesje Głosowań</h3>
        </div>
        <div class="card-body" id="active-sessions-list">
            <p>Ładowanie listy aktywnych sesji...</p>
            <!-- Lista sesji będzie tutaj wstawiana przez JavaScript -->
        </div>
    </div>
    
    <div class="card mt-4">
        <div class="card-header">
            <h3>Ostatnio Zamknięte Sesje Głosowań</h3>
        </div>
        <div class="card-body" id="closed-sessions-list">
             <p>Ładowanie listy zamkniętych sesji...</p>
            <!-- Lista sesji będzie tutaj wstawiana przez JavaScript -->
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>