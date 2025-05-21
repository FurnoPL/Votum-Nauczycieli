<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Votum Nauczycieli</title>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header class="app-header">
        <div class="container header-container">
            <h1><a href="index.php" class="logo-link">Votum Nauczycieli</a></h1>
            <nav class="main-nav">
                <?php
                // Logika nawigacji byłaby tutaj zarządzana przez PHP w zależności od stanu sesji i roli użytkownika
                // Przykładowa statyczna nawigacja dla celów demonstracyjnych frontendu:
                $current_page = basename($_SERVER['PHP_SELF']);

                if (strpos($current_page, 'admin_') === 0 || $current_page === 'create_session.php' || $current_page === 'results.php' || $current_page === 'history.php') {
                    // Jesteśmy w panelu admina
                    if ($current_page !== 'index.php') { // Załóżmy, że index.php to logowanie, a admin_dashboard.php to główny panel po zalogowaniu
                         echo '<a href="admin_dashboard.php">Panel Dyrektora</a>';
                         echo '<a href="history.php">Historia Głosowań</a>';
                         echo '<a href="logout.php" class="nav-link-logout">Wyloguj</a>';
                    }
                } else if ($current_page === 'join_session.php' || $current_page === 'vote.php') {
                    // Nauczyciel dołącza lub głosuje
                    // Można dodać link "Opuść sesję" (co byłoby obsługiwane przez backend)
                } else {
                    // Strona logowania lub główna
                    // echo '<a href="join_session.php">Dołącz do Sesji</a>';
                }
                ?>
            </nav>
        </div>
    </header>
    <main class="main-content">
        <div class="container page-container">