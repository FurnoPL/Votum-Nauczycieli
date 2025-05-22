<?php
// Plik: history.php (strona frontendowa historii głosowań)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php'; 

if (!isUserLoggedIn()) {
    header('Location: index.php?reason=not_logged_in_for_history');
    exit;
}
if (!isAdmin()) {
    header('Location: index.php?reason=not_admin_for_history');
    exit;
}

include 'includes/header.php'; 
?>

<div class="history-container page-container">
    <h2>Historia Zakończonych Sesji Głosowań</h2>
    
    <div id="history-page-message" class="alert d-none"></div>
    
    <div id="history-sessions-list" class="mt-4">
        <p class="text-center">Ładowanie historii sesji...</p>
        <!-- Lista zamkniętych sesji będzie tutaj wstawiana przez JavaScript -->
    </div>

    <div class="mt-4">
        <a href="admin_dashboard.php" class="btn btn-secondary">Powrót do Panelu Administratora</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>