<?php 
// Tutaj byłaby logika sprawdzająca, czy admin jest zalogowany (auth.php)
// if (!isAdmin()) { header('Location: index.php'); exit; }
include 'includes/header.php'; 
?>

<h2>Panel Dyrektora</h2>
<p>Witaj, [Imię Dyrektora]!</p> <!-- Dynamicznie wstawione imię -->

<div class="card mt-4">
    <div class="card-header">
        <h3>Szybkie Akcje</h3>
    </div>
    <div class="card-body">
        <a href="create_session.php" class="btn btn-success mr-2">Utwórz Nową Sesję Rady</a>
        <a href="history.php" class="btn btn-info">Zobacz Historię Głosowań</a>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h3>Aktywne / Zaplanowane Sesje</h3>
    </div>
    <div class="card-body">
        <!-- Przykład aktywnej sesji (dynamicznie z bazy danych) -->
        <div class="session-item card mb-3">
            <h4>Sesja Rady Pedagogicznej - Kwiecień 2024</h4>
            <p><strong>Status:</strong> Zaplanowana (2024-04-15 10:00)</p>
            <p><strong>Liczba uchwał:</strong> 3</p>
            <button id="start-selected-session-btn" class="btn btn-primary">Rozpocznij tę Sesję</button>
            <a href="create_session.php?edit_id=1" class="btn btn-secondary">Edytuj</a> <!-- Przykładowy link edycji -->
            <div id="generated-session-code-display" class="d-none">
                <!-- Tutaj JS wstawi kod sesji -->
            </div>
        </div>

        <div class="session-item card mb-3">
            <h4>Sesja nadzwyczajna - Budżet</h4>
            <p><strong>Status:</strong> Aktywna (Kod: 83920174)</p>
            <p><strong>Liczba uchwał:</strong> 1</p>
            <p><strong>Głosujących nauczycieli:</strong> 15</p> <!-- Dynamicznie -->
            <a href="results.php?session_id=2" class="btn btn-primary">Zarządzaj Głosowaniem / Zobacz Wyniki</a>
            <button class="btn btn-warning">Zakończ Radę</button> <!-- Przycisk do zakończenia całej rady -->
        </div>
        
        <!-- Komunikat, jeśli brak sesji -->
        <!-- <div class="alert alert-info">Obecnie nie ma żadnych aktywnych ani zaplanowanych sesji głosowań.</div> -->
    </div>
</div>


<?php include 'includes/footer.php'; ?>