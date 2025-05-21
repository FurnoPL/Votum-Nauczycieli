<?php 
// Tutaj byłaby logika sprawdzająca, czy admin jest zalogowany
// if (!isAdmin()) { header('Location: index.php'); exit; }
include 'includes/header.php'; 
?>

<h2>Historia Zakończonych Sesji Głosowań</h2>

<div class="card mt-4">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Nazwa Sesji</th>
                    <th>Data Zakończenia</th>
                    <th>Liczba Uczestników</th>
                    <th>Akcje</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td data-label="Nazwa Sesji">Rada Pedagogiczna - Luty 2024</td>
                    <td data-label="Data Zakończenia">2024-02-20</td>
                    <td data-label="Liczba Uczestników">28</td>
                    <td data-label="Akcje">
                        <a href="results.php?session_id=hist1" class="btn btn-sm btn-info">Szczegóły</a>
                        <button onclick="alert('Funkcjonalność generowania PDF wymaga implementacji backendu.')" class="btn btn-sm btn-primary">Pobierz PDF</button>
                    </td>
                </tr>
                <tr>
                    <td data-label="Nazwa Sesji">Sesja Nadzwyczajna - Grudzień 2023</td>
                    <td data-label="Data Zakończenia">2023-12-15</td>
                    <td data-label="Liczba Uczestników">25</td>
                    <td data-label="Akcje">
                        <a href="results.php?session_id=hist2" class="btn btn-sm btn-info">Szczegóły</a>
                        <button onclick="alert('Funkcjonalność generowania PDF wymaga implementacji backendu.')" class="btn btn-sm btn-primary">Pobierz PDF</button>
                    </td>
                </tr>
                <!-- Więcej wierszy dynamicznie -->
            </tbody>
        </table>
        <!-- Komunikat, jeśli brak historii -->
        <!-- <div class="alert alert-info">Brak zakończonych sesji głosowań.</div> -->
    </div>
</div>

<div class="mt-4">
    <a href="admin_dashboard.php" class="btn btn-secondary">Powrót do Panelu</a>
</div>

<?php include 'includes/footer.php'; ?>