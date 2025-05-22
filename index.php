<?php 
// Plik: index.php (strona logowania - TYLKO DLA ADMINA)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'includes/header.php'; 
?>

<div class="page-wrapper">
    <div class="container">
        <div class="login-container content-card">
            <h2 class="page-title">Logowanie Administratora</h2>
            
            <div id="login-message" class="alert d-none"></div>

            <form id="login-form"> 
                <div class="form-group">
                    <label for="email">Email Administratora:</label>
                    <input type="email" id="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label for="password">Hasło:</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Zaloguj się</button>
            </form>
            <!-- Usunięto link dla nauczyciela -->
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>