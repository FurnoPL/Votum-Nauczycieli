<?php
// Plik: join_session.php (strona dołączania dla nauczyciela - ANONIMOWO)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Jeśli nauczyciel ma już aktywną sesję głosowania (sprawdzane przez JS przy ładowaniu vote.php),
// powinien zostać tam przekierowany.
include 'includes/header.php'; 
?>

<div class="page-wrapper">
    <div class="container">
        <div class="join-session-container content-card">
            <h2 class="page-title">Dołącz do Sesji Głosowania</h2>
            <p class="text-center text-muted mb-4">Wprowadź kod sesji otrzymany od prowadzącego, aby wziąć udział w głosowaniu.</p>

            <div id="join-session-message" class="alert d-none"></div>

            <form id="join-session-form">
                <div class="form-group">
                    <label for="session_code">Kod sesji:</label>
                    <input type="text" id="session_code" name="session_code" class="form-control form-control-lg text-center" required pattern="[A-Z0-9]{6,8}" title="Kod sesji składa się z 6-8 wielkich liter i cyfr." placeholder="ABCDEF">
                </div>
                <!-- Usunięto pole participant_name -->
                <button type="submit" class="btn btn-primary btn-lg btn-block mt-4">Dołącz do Sesji</button>
            </form>
            
            <div class="mt-4 text-center">
                <small class="text-muted">Jesteś administratorem? <a href="index.php">Zaloguj się tutaj</a>.</small>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>