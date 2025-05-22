<?php 
// Plik: create_session.php (strona frontendowa)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Serwerowa weryfikacja dostępu dla admina
require_once __DIR__ . '/includes/auth.php';
if (!isUserLoggedIn()) {
    header('Location: index.php?reason=not_logged_in_for_create_session');
    exit;
}
if (!isAdmin()) {
    header('Location: index.php?reason=not_admin_for_create_session');
    exit;
}

include 'includes/header.php'; 
?>

<h2>Tworzenie Nowej Sesji Rady Pedagogicznej</h2>

<div id="create-session-message" class="alert d-none"></div>

<form id="create-session-form-page"> 
    <div class="card mb-4">
        <div class="card-header">
            <h3>Podstawowe Informacje o Sesji</h3>
        </div>
        <div class="card-body">
            <div class="form-group">
                <label for="session_title">Tytuł sesji:</label>
                <input type="text" id="session_title" name="session_title" class="form-control" required value="Rada Pedagogiczna - <?php echo date('F Y'); ?>">
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3>Uchwały do Głosowania</h3>
        </div>
        <div class="card-body">
            <div id="resolutions-list">
                <!-- Uchwały będą dynamicznie dodawane przez JS. -->
            </div>
            <button type="button" id="add-resolution-btn" class="btn btn-secondary mt-2">Dodaj kolejną uchwałę</button>
        </div>
    </div>

    <div class="mt-4">
        <button type="submit" class="btn btn-success">Utwórz Sesję</button>
        <a href="admin_dashboard.php" class="btn">Anuluj</a>
    </div>
</form>

<?php include 'includes/footer.php'; ?>