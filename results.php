<?php
// Plik: results.php (strona frontendowa do wyświetlania postępów/wyników)
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

// Pobierz ID sesji z parametru URL
$session_id = filter_input(INPUT_GET, 'session_id', FILTER_VALIDATE_INT);

if (!$session_id) {
    // Jeśli brak ID sesji, przekieruj do panelu admina lub wyświetl błąd
    header('Location: admin_dashboard.php?error=missing_session_id_for_results');
    exit;
}

// Można by tu wstępnie pobrać tytuł sesji z PHP, aby strona nie była "goła" przed załadowaniem JS
// Ale na razie polegamy na JS

include 'includes/header.php'; 
?>

<div class="results-container page-container">
    <h2 id="results-session-title">Postęp / Wyniki Sesji Głosowania (ID: <?php echo htmlspecialchars($session_id); ?>)</h2>
    
    <div id="results-page-message" class="alert d-none"></div>

    <div id="session-status-info" class="mb-3">
        <!-- Informacje o statusie sesji (otwarta/zamknięta) będą tutaj -->
    </div>
    
    <div id="session-data-area">
        <p class="text-center">Ładowanie danych sesji...</p>
        <!-- Tutaj będą wyświetlane postępy lub wyniki -->
    </div>

    <div class="mt-4">
        <a href="admin_dashboard.php" class="btn btn-secondary">Powrót do Panelu Administratora</a>
        <button id="refresh-results-btn" class="btn btn-info ml-2">Odśwież Dane</button>
        <button id="close-this-session-btn" class="btn btn-danger ml-2 d-none" data-session-id="<?php echo htmlspecialchars($session_id); ?>">Zamknij Tę Sesję</button>
        <!-- Przycisk generowania PDF może być tu później dodany -->
        <!-- <button id="generate-pdf-btn" class="btn btn-primary ml-2 d-none">Generuj Raport PDF</button> -->
    </div>
</div>

<?php include 'includes/footer.php'; ?>