<?php
// W rzeczywistej aplikacji ten skrypt PHP zniszczyłby sesję użytkownika
// session_start();
// session_unset();
// session_destroy();
// header('Location: index.php');
// exit;
?>
<?php include 'includes/header.php'; ?>

<div class="page-container text-center">
    <h2>Wylogowywanie...</h2>
    <p>Zostałeś pomyślnie wylogowany.</p>
    <div class="alert alert-info mt-3">
        Za chwilę zostaniesz przekierowany na stronę główną.
    </div>
    <a href="index.php" class="btn btn-primary mt-3">Przejdź do strony głównej teraz</a>
</div>

<script>
    setTimeout(function() {
        window.location.href = 'index.php';
    }, 3000); // Przekierowanie po 3 sekundach
</script>

<?php include 'includes/footer.php'; ?>