<?php 
// Tutaj byłaby logika sprawdzająca, czy admin jest zalogowany i ma dostęp do wyników tej sesji
// if (!isAdmin()) { header('Location: index.php'); exit; }
include 'includes/header.php'; 
?>

<h2>Podsumowanie Sesji Głosowania</h2>
<h3>Sesja: "Rada Pedagogiczna - Kwiecień 2024"</h3> <!-- Dynamicznie -->
<p><strong>Data przeprowadzenia:</strong> 2024-04-15</p> <!-- Dynamicznie -->
<p><strong>Liczba uczestników:</strong> 25</p> <!-- Dynamicznie -->


<div class="card mt-4">
    <div class="card-header">
        <h3>Wyniki Głosowań nad Uchwałami</h3>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Lp.</th>
                    <th>Nazwa Uchwały</th>
                    <th>Za</th>
                    <th>Przeciw</th>
                    <th>Wstrzymało się</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td data-label="Lp.">1</td>
                    <td data-label="Nazwa Uchwały">Uchwała w sprawie zatwierdzenia planu pracy szkoły</td>
                    <td data-label="Za">18</td>
                    <td data-label="Przeciw">5</td>
                    <td data-label="Wstrzymało się">2</td>
                    <td data-label="Status"><span class="badge badge-success">Przyjęta</span></td>
                </tr>
                <tr>
                    <td data-label="Lp.">2</td>
                    <td data-label="Nazwa Uchwały">Uchwała w sprawie zmian w statucie szkoły</td>
                    <td data-label="Za">10</td>
                    <td data-label="Przeciw">12</td>
                    <td data-label="Wstrzymało się">3</td>
                    <td data-label="Status"><span class="badge badge-danger">Odrzucona</span></td>
                </tr>
                <tr>
                    <td data-label="Lp.">3</td>
                    <td data-label="Nazwa Uchwały">Uchwała dotycząca organizacji wycieczki szkolnej</td>
                    <td data-label="Za">22</td>
                    <td data-label="Przeciw">1</td>
                    <td data-label="Wstrzymało się">2</td>
                    <td data-label="Status"><span class="badge badge-success">Przyjęta</span></td>
                </tr>
                <!-- Więcej wierszy dynamicznie -->
            </tbody>
        </table>
    </div>
</div>

<div class="mt-4 text-center">
    <button onclick="alert('Funkcjonalność generowania PDF wymaga implementacji backendu.')" class="btn btn-primary">Pobierz Raport PDF</button>
    <a href="admin_dashboard.php" class="btn btn-secondary">Powrót do Panelu</a>
</div>
<style>
/* Dodatkowe style dla badge (znaczników statusu) */
.badge {
    display: inline-block;
    padding: .35em .65em;
    font-size: .75em;
    font-weight: 700;
    line-height: 1;
    color: #fff;
    text-align: center;
    white-space: nowrap;
    vertical-align: baseline;
    border-radius: .25rem;
}
.badge-success { background-color: #28a745; }
.badge-danger { background-color: #dc3545; }
</style>

<?php include 'includes/footer.php'; ?>