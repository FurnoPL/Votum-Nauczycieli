<?php 
// Plik: index.php (strona logowania - frontend)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'includes/header.php'; 
?>

<div class="login-container page-container">
    <h2 class="form-title">Logowanie</h2>
    
    <div id="login-message" class="alert d-none"></div>

    <form id="login-form"> 
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="password">Hasło:</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-block">Zaloguj się</button>
    </form>

    <hr class="mt-4 mb-3">

    <div class="text-center">
        <p>Jesteś nauczycielem i chcesz dołączyć do głosowania?</p>
        <a href="join_session.php" class="btn btn-secondary">Dołącz do Sesji Głosowania</a>
    </div>
</div>

<?php include 'includes/footer.php'; // Footer dołącza scripts.js ?>