<?php include 'includes/header.php'; ?>

<div class="login-container page-container">
    <h2 class="form-title">Logowanie do Panelu Administracyjnego</h2>
    
    <!-- Komunikat o błędzie (dynamicznie przez PHP/JS) -->
    <!-- <div class="alert alert-danger">Nieprawidłowy login lub hasło.</div> -->

    <form action="admin_dashboard.php" method="POST" id="create-session-form"> <!-- Akcja do zmiany na skrypt PHP obsługujący logowanie -->
        <div class="form-group">
            <label for="login">Login (Dyrektor/Wicedyrektor):</label>
            <input type="text" id="login" name="username" class="form-control" required>
        </div>
        <div class="form-group">
            <label for="password">Hasło:</label>
            <input type="password" id="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-block">Zaloguj się</button>
    </form>

    <hr class="mt-4 mb-3">

    <div class="text-center">
        <p>Jesteś nauczycielem?</p>
        <a href="join_session.php" class="btn btn-secondary">Dołącz do Sesji Głosowania</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>