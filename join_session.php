<?php include 'includes/header.php'; ?>

<div class="join-session-container page-container">
    <h2 class="form-title">Dołącz do Sesji Głosowania</h2>

    <!-- Komunikat o błędzie (dynamicznie przez PHP/JS) -->
    <!-- <div class="alert alert-danger">Nieprawidłowy kod sesji lub sesja jest nieaktywna.</div> -->
    <!-- <div class="alert alert-success">Pomyślnie dołączono do sesji. Przekierowywanie...</div> -->

    <form action="vote.php" method="POST"> <!-- Akcja do zmiany na skrypt PHP obsługujący dołączanie -->
        <div class="form-group">
            <label for="session_code">Kod sesji (8 cyfr):</label>
            <input type="text" id="session_code" name="session_code" class="form-control" required pattern="\d{8}" title="Kod sesji musi składać się z 8 cyfr.">
        </div>
        <div class="form-group">
            <label for="teacher_name">Twoje imię i nazwisko (do celów technicznych):</label>
            <input type="text" id="teacher_name" name="teacher_name" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-block">Dołącz do Sesji</button>
    </form>
    <div class="text-center mt-3">
        <a href="index.php">Powrót do strony głównej</a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>