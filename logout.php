<?php
// Plik: logout.php (strona frontendowa)
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Potrzebne dla header.php i dla JS do sprawdzenia statusu
}
include 'includes/header.php'; 
?>

<div class="page-container text-center">
    <h2>Wylogowywanie...</h2>
    <p id="logout-page-message">Trwa proces wylogowywania.</p>
    <div id="logout-spinner" class="mt-3" style="display: none;">
        <!-- Można tu dodać prosty spinner CSS jeśli chcesz -->
        <p>Proszę czekać...</p>
    </div>
    <a href="index.php" id="manual-redirect-link" class="btn btn-primary mt-3 d-none">Przejdź do strony głównej teraz</a>
</div>

<?php include 'includes/footer.php'; ?>