<?php
// Plik: vote.php (strona frontendowa panelu głosowania - ANONIMOWO)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php'; 

if (!isParticipantInVotingSession()) { 
    header('Location: join_session.php?reason=not_joined_to_vote_page_anon');
    exit;
}

include 'includes/header.php'; 
?>

<div class="page-wrapper">
    <div class="container">
        <div class="vote-panel-container content-card">
            <h2 id="vote-session-title" class="page-title">Panel Głosowania</h2> 
            <p class="text-center lead" id="vote-participant-greeting">Witaj!</p>

            <div id="vote-page-message" class="alert d-none mt-3 mb-3"></div>

            <div class="text-center mb-3">
                <button id="refresh-vote-data-btn" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sync-alt"></i> Odśwież widok uchwał
                </button>
            </div>

            <div id="resolutions-voting-area">
                <p class="text-center">Ładowanie uchwał lub oczekiwanie na rozpoczęcie głosowania przez dyrektora...</p>
                <!-- 
                    Jeśli żadna uchwała nie jest 'active', można tu wyświetlić:
                    "Obecnie żadna uchwała nie jest aktywna do głosowania. Odśwież stronę, aby sprawdzić."
                -->
            </div>

            <div class="mt-4 text-center">
                <button id="finish-voting-btn" class="btn btn-secondary d-none">Opuść Panel Głosowania</button>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>