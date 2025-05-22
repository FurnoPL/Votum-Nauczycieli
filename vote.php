<?php
// Plik: vote.php (strona frontendowa panelu głosowania)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php'; // Dla requireLogin itp.

// Podstawowa ochrona na poziomie serwera
if (!isUserLoggedIn()) {
    header('Location: index.php?reason=not_logged_in_for_vote');
    exit;
}
// Sprawdź, czy użytkownik dołączył do jakiejś sesji (czy zmienne sesyjne są ustawione)
if (!isset($_SESSION['current_voting_session_id']) || !isset($_SESSION['current_participant_id'])) {
    // Jeśli nie ma danych o sesji, przekieruj do strony dołączania
    // Można dodać komunikat, np. "Musisz najpierw dołączyć do sesji."
    header('Location: join_session.php?reason=session_not_joined');
    exit;
}

// Można by tu pobrać podstawowe dane sesji z PHP, aby uniknąć pierwszego "pustego" renderowania
// ale JS i tak załaduje wszystko dynamicznie, więc na razie zostawmy to JS-owi.
// $sessionId = $_SESSION['current_voting_session_id'];
// $participantId = $_SESSION['current_participant_id'];
// Tutaj moglibyśmy pobrać obiekt VotingSession i przekazać jego tytuł do JS,
// ale cała logika ładowania danych sesji i głosowania będzie w JS.

include 'includes/header.php'; 
?>

<div class="vote-panel-container page-container">
    <h2 id="vote-session-title">Panel Głosowania</h2> 
    <p>Witaj, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Nauczycielu'); ?>!</p>

    <div id="vote-page-message" class="alert d-none"></div>

    <div id="resolutions-voting-area">
        <p class="text-center">Ładowanie uchwał...</p>
        <!-- Uchwały i opcje głosowania będą tutaj wstawiane przez JavaScript -->
    </div>

    <div class="mt-4 text-center">
        <button id="finish-voting-btn" class="btn btn-primary d-none">Zakończono Głosowanie (Opuść Panel)</button>
        <!-- Ten przycisk może np. przekierowywać do join_session.php lub index.php po wyczyszczeniu danych sesji głosowania -->
    </div>
</div>

<?php include 'includes/footer.php'; ?>