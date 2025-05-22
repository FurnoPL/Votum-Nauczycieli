<?php
// Plik: results.php (strona frontendowa do zarządzania sesją i wyświetlania postępów/wyników)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php'; 

if (!isUserLoggedIn()) {
    header('Location: index.php?reason=not_logged_in_for_results');
    exit;
}
if (!isAdmin()) {
    header('Location: index.php?reason=not_admin_for_results');
    exit;
}

$session_id = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);

if (!$session_id) {
    header('Location: admin_dashboard.php?error=missing_session_id_for_results');
    exit;
}

include 'includes/header.php'; 
?>

<div class="results-container page-container"> <!-- Użyj .page-container dla spójności z nowym CSS -->
    <div class="content-card"> <!-- Dodatkowy .content-card dla paddingu i tła -->
        <h2 id="results-session-title" class="page-title">Zarządzanie Sesją Głosowania (ID: <?php echo htmlspecialchars($session_id); ?>)</h2>
        
        <div id="results-page-message" class="alert d-none"></div>

        <div id="session-status-info" class="mb-3">
            <!-- Informacje o statusie sesji (otwarta/zamknięta) -->
        </div>
        
        <!-- Sekcja zarządzania uchwałami -->
        <div id="resolutions-management-area" class="mb-4">
            <h3 class="mb-3">Uchwały w Sesji:</h3>
            <div id="resolutions-list-admin">
                <p class="text-center">Ładowanie listy uchwał...</p>
            </div>
        </div>
        <hr>
        <!-- Sekcja postępów/wyników (jak wcześniej) -->
        <div id="session-data-area">
            <h3 class="mb-3" id="progress-results-header">Postęp / Wyniki Ogólne:</h3>
            <p class="text-center">Ładowanie danych...</p>
        </div>

        <div class="mt-4">
            <a href="admin_dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Powrót do Panelu</a>
            <button id="refresh-results-btn" class="btn btn-info ml-2"><i class="fas fa-sync-alt"></i> Odśwież Dane</button>
            <button id="close-this-session-btn" class="btn btn-danger ml-2 d-none" data-session-id="<?php echo htmlspecialchars($session_id); ?>"><i class="fas fa-lock"></i> Zamknij Całą Sesję</button>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>