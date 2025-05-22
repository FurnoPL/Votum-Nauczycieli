<?php
// Plik: join_session.php (strona frontendowa)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Tutaj nie ma potrzeby sprawdzania roli admina, ale użytkownik powinien być zalogowany
// jako nauczyciel. JS `checkLoginStatusAndUpdateNav` powinien go tu skierować, 
// jeśli jest nauczycielem i nie jest na stronie logowania.
// Można dodać serwerowe sprawdzenie, czy użytkownik jest zalogowany, ale niekoniecznie czy jest nauczycielem,
// bo endpoint API zweryfikuje, czy sesja jest otwarta itp.
require_once __DIR__ . '/includes/auth.php';
// if (!isUserLoggedIn()) { // Można dodać przekierowanie, jeśli nie jest zalogowany
//    header('Location: index.php?reason=not_logged_in_for_join');
//    exit;
// }


include 'includes/header.php'; 
?>

<div class="join-session-container page-container">
    <h2 class="form-title">Dołącz do Sesji Głosowania</h2>

    <div id="join-session-message" class="alert d-none"></div>

    <form id="join-session-form">
        <div class="form-group">
            <label for="session_code">Kod sesji:</label>
            <input type="text" id="session_code" name="session_code" class="form-control" required pattern="[A-Z0-9]{6,8}" title="Kod sesji składa się z 6-8 wielkich liter i cyfr.">
            <!-- Zmieniono pattern, bo nasz generator tworzy kody o długości 6 (domyślnie), a nie 8 cyfr. -->
            <!-- Zakładam, że kod może mieć różną długość (np. 6). Dostosuj pattern, jeśli trzeba. -->
        </div>
        <button type="submit" class="btn btn-block">Dołącz do Sesji</button>
    </form>
    <div class="text-center mt-3">
        <?php 
        // Link powrotu w zależności od roli (jeśli admin tu trafił przez pomyłkę)
        $backLink = 'index.php'; // Domyślnie
        if (isUserLoggedIn()) {
            if (isAdmin()) {
                $backLink = 'admin_dashboard.php';
            }
            // Dla nauczyciela nie ma specjalnego "panelu" przed dołączeniem, więc index.php jest ok
        }
        ?>
        <a href="<?php echo $backLink; ?>">Powrót</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>